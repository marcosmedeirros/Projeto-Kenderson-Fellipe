<?php

defined('CONTROLADORIA') || exit;

function page_account(): void
{
    $user = require_user();
    $resumeTotp = query_string('2fa') === 'pendente' && !$user['totp_enabled']
        && db_value('SELECT totp_secret FROM users WHERE id = ?', [$user['id']]) !== null;
    render_page('account', 'Minha conta', [
        'user' => $user,
        'sessions' => list_user_sessions($user['id']),
        'currentSessionId' => current_session()['id'],
        'resumeTotp' => $resumeTotp,
        '_scripts' => ['vendor/qrcode.js'],
    ]);
}

function action_account_password(): void
{
    $user = require_user();
    $error = change_own_password($user, post_raw('current'), post_raw('password'), post_raw('confirm'));
    flash($error === null ? 'ok' : 'erro', $error ?? 'Senha alterada. As sessões em outros aparelhos foram encerradas.');
    redirect('/conta');
}

function action_totp_start(): void
{
    $session = current_session();
    if ($session === null || $session['two_factor_pending'] || $session['user']['must_change_password']) {
        json_response(['error' => 'Sessão expirada. Entre novamente.'], 401);
    }
    $user = $session['user'];
    if ($user['totp_enabled']) {
        json_response(['error' => 'A verificação em 2 etapas já está ativa.'], 409);
    }

    // "reusar" mantém o mesmo segredo depois de um código errado: o que já foi lido no app continua valendo.
    $secret = null;
    if (post_string('reusar', 1) === '1') {
        $stored = db_value('SELECT totp_secret FROM users WHERE id = ?', [$user['id']]);
        $secret = $stored !== null ? decrypt_secret((string) $stored) : null;
    }
    if ($secret === null) {
        $secret = totp_generate_secret();
        db_exec('UPDATE users SET totp_secret = ?, totp_last_step = NULL WHERE id = ?', [encrypt_secret($secret), $user['id']]);
    }
    json_response(['uri' => totp_uri($secret, $user['email']), 'secret' => trim(chunk_split($secret, 4, ' '))]);
}

function action_totp_confirm(): void
{
    $user = require_user();
    $secret = db_value('SELECT totp_secret FROM users WHERE id = ?', [$user['id']]);
    if (!$secret) {
        flash('erro', 'Comece a configuração de novo.');
        redirect('/conta');
    }
    $step = totp_verify(decrypt_secret((string) $secret), post_string('code', 10));
    if ($step === null) {
        flash('erro', 'Código incorreto. Confira se o horário do celular está automático e digite o código que aparece agora.');
        redirect('/conta?2fa=pendente');
    }
    db_exec('UPDATE users SET totp_enabled = 1, totp_last_step = ? WHERE id = ?', [$step, $user['id']]);
    revoke_user_sessions($user['id'], current_session()['id']);
    log_audit($user['id'], '2fa.ativado');
    flash('ok', 'Verificação em 2 etapas ativada. As sessões em outros aparelhos foram encerradas.');
    redirect('/conta');
}

function action_totp_disable(): void
{
    $user = require_user();
    if (sensitive_action_blocked($user)) {
        flash('erro', 'Muitas tentativas seguidas. Aguarde 15 minutos e tente de novo.');
        redirect('/conta');
    }
    $row = db_one('SELECT password_hash, totp_secret, totp_last_step FROM users WHERE id = ?', [$user['id']]);
    if ($row === null || !verify_password($row['password_hash'], post_raw('password'))) {
        register_sensitive_failure($user);
        flash('erro', 'Senha incorreta.');
        redirect('/conta');
    }
    $step = $row['totp_secret'] !== null
        ? totp_verify(decrypt_secret($row['totp_secret']), post_string('code', 10), $row['totp_last_step'] !== null ? (int) $row['totp_last_step'] : null)
        : null;
    if ($step === null) {
        register_sensitive_failure($user);
        flash('erro', 'Código de verificação incorreto. Para desativar, confirme com o código do app autenticador.');
        redirect('/conta');
    }
    db_exec('UPDATE users SET totp_enabled = 0, totp_secret = NULL, totp_last_step = NULL WHERE id = ?', [$user['id']]);
    log_audit($user['id'], '2fa.desativado');
    flash('ok', 'Verificação em 2 etapas desativada.');
    redirect('/conta');
}

function action_session_revoke(): void
{
    $user = require_user();
    $sessionId = post_string('sessionId', 64);
    if (preg_match('/^[a-f0-9]{64}$/', $sessionId)) {
        db_exec('DELETE FROM sessions WHERE id = ? AND user_id = ?', [$sessionId, $user['id']]);
        log_audit($user['id'], 'sessao.encerrada');
        flash('ok', 'Sessão encerrada.');
    }
    redirect('/conta');
}

function action_sessions_revoke_others(): void
{
    $user = require_user();
    revoke_user_sessions($user['id'], current_session()['id']);
    log_audit($user['id'], 'sessao.encerrada', ['todas' => true]);
    flash('ok', 'As sessões nos outros aparelhos foram encerradas.');
    redirect('/conta');
}
