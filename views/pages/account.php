<?php defined('CONTROLADORIA') || exit; ?>
<?= page_header('Minha conta', 'Conta', 'Seus dados de acesso, senha, verificação em 2 etapas e aparelhos conectados.') ?>

<div class="grid gap-6 xl:grid-cols-2">
    <section class="card">
        <?= card_header('Perfil', '', 'user-round') ?>
        <div class="p-5">
            <?= key_value([
                ['Nome', e($user['name'])],
                ['E-mail', e($user['email'])],
                ['Perfil', '<span class="font-semibold">' . e(ROLE_LABELS[$user['role']] ?? $user['role']) . '</span> <span class="text-muted">· ' . e(ROLE_DESCRIPTIONS[$user['role']] ?? '') . '</span>'],
            ]) ?>
        </div>
    </section>

    <section class="card">
        <?= card_header('Trocar senha', '', 'key-round') ?>
        <?= form_open('/conta/senha', 'grid gap-4 p-5 sm:grid-cols-3') ?>
            <div>
                <label for="current" class="label">Senha atual</label>
                <input id="current" name="current" type="password" autocomplete="current-password" required class="input">
            </div>
            <div>
                <label for="password" class="label">Nova senha</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required class="input">
            </div>
            <div>
                <label for="confirm" class="label">Confirmar</label>
                <input id="confirm" name="confirm" type="password" autocomplete="new-password" minlength="10" maxlength="72" required class="input">
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-3">
                <p class="text-xs text-muted">10+ caracteres, com letras e números. Os outros aparelhos serão desconectados.</p>
                <?= submit_button('Trocar senha', 'btn btn-secondary', 'Salvando…') ?>
            </div>
        </form>
    </section>
</div>

<section class="card mt-6">
    <?= card_header('Verificação em 2 etapas', 'Além da senha, pede um código do app autenticador no celular', 'shield-check', $user['totp_enabled'] ? badge('Ativa', 'ok') : badge('Desativada', 'warn')) ?>
    <div class="p-5">
        <?php if ($user['totp_enabled']): ?>
            <?= form_open('/conta/2fa/desativar', 'flex max-w-lg flex-wrap items-end gap-3') ?>
                <div class="min-w-[220px] flex-1">
                    <label for="disable-password" class="label">Para desativar, confirme sua senha</label>
                    <input id="disable-password" name="password" type="password" autocomplete="current-password" required class="input">
                </div>
                <?= submit_button('Desativar', 'btn btn-danger', 'Desativando…') ?>
            </form>
        <?php else: ?>
            <p class="mb-4 max-w-2xl text-sm text-muted">Recomendado para todos, principalmente administradores. Mesmo que alguém descubra sua senha, não consegue entrar sem o seu celular.</p>
            <div data-totp>
                <button type="button" class="btn btn-primary" data-totp-start><?= icon('shield-check') ?><span>Ativar verificação em 2 etapas</span></button>
                <p class="mt-2 text-sm text-danger" data-totp-error hidden></p>
                <div class="grid gap-6 sm:grid-cols-[auto_1fr]" data-totp-setup hidden>
                    <div class="grid size-[216px] place-items-center rounded-lg bg-white p-2" data-totp-qr aria-label="QR code para o app autenticador"></div>
                    <div class="space-y-4 text-sm">
                        <ol class="list-decimal space-y-1.5 pl-4 text-muted">
                            <li>Abra o Google Authenticator, Microsoft Authenticator ou 1Password.</li>
                            <li>Leia o QR code ao lado.</li>
                            <li>Digite o código de 6 dígitos que aparecer.</li>
                        </ol>
                        <div>
                            <p class="text-xs text-dim">Não consegue ler? Digite esta chave no app:</p>
                            <code class="mt-1 block font-mono text-sm break-all text-ink" data-totp-secret></code>
                        </div>
                        <?= form_open('/conta/2fa/confirmar', 'max-w-xs') ?>
                            <label for="totp-code" class="label">Código do app</label>
                            <div class="flex gap-2">
                                <input id="totp-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required class="input font-mono tracking-[0.3em]" placeholder="000000">
                                <?= submit_button('Confirmar', 'btn btn-primary', '…') ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card mt-6">
    <?= card_header('Aparelhos conectados', 'Sessões expiram após 12 horas sem uso ou 7 dias no total', 'monitor',
        count($sessions) > 1 ? form_open('/conta/sessoes/encerrar-outras') . submit_button('Encerrar as outras', 'btn btn-ghost btn-sm') . '</form>' : '') ?>
    <ul class="divide-y divide-line">
        <?php foreach ($sessions as $session): $isCurrent = $session['id'] === $currentSessionId; ?>
            <li class="flex flex-wrap items-center gap-4 px-5 py-3.5">
                <?= icon('monitor', 'size-4 text-dim') ?>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold"><?= e(describe_user_agent($session['user_agent'])) ?> <?= $isCurrent ? badge('Este aparelho', 'ok', 'ml-1') : '' ?></p>
                    <p class="text-xs text-muted"><?= e('IP ' . ($session['ip'] ?? '—') . ' · entrou em ' . fmt_full_date(from_db($session['created_at'])) . ' · ativo ' . fmt_ago(from_db($session['last_seen_at']))) ?></p>
                </div>
                <?php if (!$isCurrent): ?>
                    <?= form_open('/conta/sessoes/encerrar') ?>
                        <input type="hidden" name="sessionId" value="<?= e($session['id']) ?>">
                        <?= submit_button('Encerrar', 'btn btn-danger btn-sm') ?>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
