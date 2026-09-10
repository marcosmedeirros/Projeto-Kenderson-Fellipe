<?php

defined('CONTROLADORIA') || exit;

const LOGIN_BLOCKED_MESSAGE = 'Muitas tentativas seguidas. Aguarde 15 minutos e tente de novo.';

function page_login(): void
{
    $session = current_session();
    if ($session !== null && !$session['two_factor_pending']) {
        redirect('/');
    }
    bootstrap_admin();
    render_bare('login', 'Entrar', ['step' => $session !== null ? '2fa' : 'senha', 'error' => null, 'email' => '']);
}

function finish_login(string $userId): void
{
    create_session($userId);
    db_exec('UPDATE users SET last_login_at = ? WHERE id = ?', [to_db(utc_now()), $userId]);
    log_audit($userId, 'login.sucesso');
}

function action_login(): void
{
    $email = mb_strtolower(post_string('email', 254));
    $password = post_raw('password');
    $fail = static function (string $error, int $status = 200) use ($email): void {
        http_response_code($status);
        render_bare('login', 'Entrar', ['step' => 'senha', 'error' => $error, 'email' => $email]);
    };

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $fail('Informe um e-mail válido e a senha.', 422);
    }

    $ip = client_ip();
    if (is_login_blocked($email, $ip)) {
        // Sem auditoria aqui: o bloqueio já foi registrado quando começou.
        $fail(LOGIN_BLOCKED_MESSAGE, 429);
    }

    $user = db_one(
        'SELECT id, password_hash, active, totp_enabled, must_change_password, temp_password_expires_at FROM users WHERE email = ? LIMIT 1',
        [$email]
    );
    $valid = false;
    if ($user !== null && $user['active']) {
        $valid = verify_password($user['password_hash'], $password);
    } else {
        dummy_verify($password);
    }

    if (!$valid) {
        $blockedNow = record_login_attempt($email, $ip, false);
        // Só e-mails cadastrados geram registro de falha, para a auditoria não virar lixo de robôs.
        if ($blockedNow) {
            log_audit($user['id'] ?? null, 'login.bloqueado', ['email' => $email]);
        } elseif ($user !== null) {
            log_audit($user['id'], 'login.falha');
        }
        $fail($blockedNow ? LOGIN_BLOCKED_MESSAGE : 'E-mail ou senha incorretos.', $blockedNow ? 429 : 401);
    }

    if (temporary_password_expired($user)) {
        log_audit($user['id'], 'login.senha_expirada');
        $fail('Sua senha temporária expirou. Peça ao administrador uma nova.', 401);
    }

    if (password_needs_upgrade($user['password_hash'])) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), $user['id']]);
    }

    if ($user['totp_enabled']) {
        // As tentativas erradas só são zeradas depois do código de 2 etapas:
        // acertar a senha de novo não dá novas chances de adivinhar o código.
        create_session($user['id'], true);
        render_bare('login', 'Entrar', ['step' => '2fa', 'error' => null, 'email' => '']);
    }

    record_login_attempt($email, $ip, true);
    finish_login($user['id']);
    redirect('/');
}

function action_login_two_factor(): void
{
    $expired = static function (string $message, int $status = 200): void {
        http_response_code($status);
        render_bare('login', 'Entrar', ['step' => 'expirado', 'error' => $message, 'email' => '']);
    };

    $session = current_session();
    if ($session === null || !$session['two_factor_pending']) {
        $expired('O tempo para digitar o código acabou. Entre novamente.');
    }

    $userId = $session['user']['id'];
    $email = $session['user']['email'];
    $ip = client_ip();

    if ($session['two_factor_attempts'] >= 5 || is_login_blocked($email, $ip)) {
        delete_current_session();
        $expired('Muitas tentativas seguidas. Aguarde 15 minutos e entre novamente.', 429);
    }

    $user = db_one('SELECT totp_secret, totp_last_step FROM users WHERE id = ?', [$userId]);
    $step = null;
    if ($user !== null && $user['totp_secret'] !== null) {
        $step = totp_verify(decrypt_secret($user['totp_secret']), post_string('code', 10), $user['totp_last_step'] !== null ? (int) $user['totp_last_step'] : null);
    }

    if ($step === null) {
        db_exec('UPDATE sessions SET two_factor_attempts = two_factor_attempts + 1 WHERE id = ?', [$session['id']]);
        $blockedNow = record_login_attempt($email, $ip, false);
        // Errar o código depois de acertar a senha é sinal forte de senha vazada: sempre registra.
        log_audit($userId, $blockedNow ? 'login.bloqueado' : 'login.2fa_falha', $blockedNow ? ['email' => $email, 'etapa' => '2fa'] : null);
        if ($blockedNow || $session['two_factor_attempts'] + 1 >= 5) {
            delete_current_session();
            $expired('Muitas tentativas seguidas. Aguarde 15 minutos e entre novamente.', 429);
        }
        http_response_code(401);
        render_bare('login', 'Entrar', ['step' => '2fa', 'error' => 'Código incorreto. Confira o app autenticador e tente de novo.', 'email' => '']);
    }

    db_exec('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $userId]);
    record_login_attempt($email, $ip, true);
    delete_current_session();
    finish_login($userId);
    redirect('/');
}

function action_login_cancel(): void
{
    delete_current_session();
    redirect('/login');
}

function action_logout(): void
{
    $session = current_session();
    if ($session !== null) {
        log_audit($session['user']['id'], 'logout');
    }
    delete_current_session();
    redirect('/login');
}

/** Troca a senha do usuário logado. Devolve a mensagem de erro, ou null se deu certo. */
function change_own_password(array $user, string $current, string $new, string $confirm): ?string
{
    if (sensitive_action_blocked($user)) {
        return LOGIN_BLOCKED_MESSAGE;
    }
    $error = validate_new_password($new);
    if ($error !== null) {
        return $error;
    }
    if (!hash_equals($new, $confirm)) {
        return 'A confirmação não é igual à nova senha.';
    }
    if (hash_equals($new, $current)) {
        return 'A nova senha precisa ser diferente da atual.';
    }
    $hash = (string) db_value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    if (!verify_password($hash, $current)) {
        register_sensitive_failure($user);
        return 'A senha atual está incorreta.';
    }
    db_exec(
        'UPDATE users SET password_hash = ?, must_change_password = 0, temp_password_expires_at = NULL WHERE id = ?',
        [hash_password($new), $user['id']]
    );
    revoke_user_sessions($user['id'], current_session()['id'] ?? null);
    log_audit($user['id'], 'senha.alterada');
    return null;
}

function page_first_access(): void
{
    $user = require_user(true);
    if (!$user['must_change_password']) {
        redirect('/');
    }
    render_bare('first-access', 'Primeiro acesso', ['user' => $user, 'error' => null]);
}

function action_first_access(): void
{
    $user = require_user(true);
    if (!$user['must_change_password']) {
        redirect('/');
    }
    $error = change_own_password($user, post_raw('current'), post_raw('password'), post_raw('confirm'));
    if ($error !== null) {
        http_response_code(422);
        render_bare('first-access', 'Primeiro acesso', ['user' => $user, 'error' => $error]);
    }
    redirect('/');
}
