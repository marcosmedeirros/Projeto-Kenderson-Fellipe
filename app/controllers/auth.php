<?php

defined('CONTROLADORIA') || exit;

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
        log_audit(null, 'login.bloqueado', ['email' => $email]);
        $fail('Muitas tentativas seguidas. Aguarde 15 minutos e tente de novo.', 429);
    }

    $user = db_one('SELECT id, password_hash, active, totp_enabled FROM users WHERE email = ? LIMIT 1', [$email]);
    $valid = false;
    if ($user !== null && $user['active']) {
        $valid = verify_password($user['password_hash'], $password);
    } else {
        dummy_verify($password);
    }

    if ($user === null || !$valid) {
        record_login_attempt($email, $ip, false);
        log_audit($user['id'] ?? null, 'login.falha', ['email' => $email]);
        $fail('E-mail ou senha incorretos.', 401);
    }

    record_login_attempt($email, $ip, true);

    if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), $user['id']]);
    }

    if ($user['totp_enabled']) {
        create_session($user['id'], true);
        render_bare('login', 'Entrar', ['step' => '2fa', 'error' => null, 'email' => '']);
    }

    finish_login($user['id']);
    redirect('/');
}

function action_login_two_factor(): void
{
    $session = current_session();
    if ($session === null || !$session['two_factor_pending']) {
        render_bare('login', 'Entrar', ['step' => 'expirado', 'error' => 'O tempo para digitar o código acabou. Entre novamente.', 'email' => '']);
    }
    if ($session['two_factor_attempts'] >= 5) {
        delete_current_session();
        render_bare('login', 'Entrar', ['step' => 'expirado', 'error' => 'Muitos códigos incorretos. Entre novamente.', 'email' => '']);
    }

    $userId = $session['user']['id'];
    $user = db_one('SELECT totp_secret, totp_last_step FROM users WHERE id = ?', [$userId]);
    $step = null;
    if ($user !== null && $user['totp_secret'] !== null) {
        $step = totp_verify(decrypt_secret($user['totp_secret']), post_string('code', 10), $user['totp_last_step'] !== null ? (int) $user['totp_last_step'] : null);
    }

    if ($step === null) {
        db_exec('UPDATE sessions SET two_factor_attempts = two_factor_attempts + 1 WHERE id = ?', [$session['id']]);
        log_audit($userId, 'login.2fa_falha');
        http_response_code(401);
        render_bare('login', 'Entrar', ['step' => '2fa', 'error' => 'Código incorreto. Confira o app autenticador e tente de novo.', 'email' => '']);
    }

    db_exec('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $userId]);
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
        return 'A senha atual está incorreta.';
    }
    db_exec('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [hash_password($new), $user['id']]);
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
    $error = change_own_password($user, post_raw('current'), post_raw('password'), post_raw('confirm'));
    if ($error !== null) {
        http_response_code(422);
        render_bare('first-access', 'Primeiro acesso', ['user' => $user, 'error' => $error]);
    }
    redirect('/');
}
