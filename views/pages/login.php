<?php
defined('CONTROLADORIA') || exit;

$clips = [[6, 9, 'bg-accent'], [19, 7, 'bg-accent'], [31, 8, 'bg-warn'], [45, 10, 'bg-accent'], [61, 6, 'bg-accent/50']];
$errorHtml = static fn (?string $message): string => $message
    ? '<p class="flex items-start gap-2 rounded-lg border border-danger/30 bg-danger/[0.07] px-3 py-2.5 text-sm text-danger" role="alert">' . icon('circle-alert', 'mt-0.5 size-4 shrink-0') . e($message) . '</p>'
    : '';
?>
<main class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
    <section class="relative hidden overflow-hidden border-r border-line bg-panel lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="pointer-events-none absolute inset-0 opacity-60" style="background:radial-gradient(700px 380px at 85% 0%,rgba(45,184,155,.14),transparent 60%)"></div>
        <div class="relative flex items-center gap-3"><?= brand_mark('size-9') ?><span class="display text-xl font-extrabold">Controladoria</span></div>

        <div class="relative">
            <p class="eyebrow mb-4">Canal Luiz Jordão</p>
            <h1 class="display max-w-md text-[56px] leading-[0.92] font-extrabold text-balance">O canal inteiro <span class="text-accent">num lugar só.</span></h1>
            <p class="mt-5 max-w-md text-[17px] text-muted">Vídeos programados, capas, desempenho e ideias de pauta, integrados ao bot do WhatsApp.</p>

            <div class="mt-10 max-w-lg rounded-xl border border-line bg-bg/60 p-4" aria-hidden="true">
                <div class="mb-3 flex justify-between font-mono text-[10px] tracking-[0.1em] text-dim uppercase"><span>Estoque</span><span>próximos 20 dias</span></div>
                <div class="relative h-12 overflow-hidden rounded-md bg-panel-2">
                    <div class="absolute inset-y-0 right-0 left-[72%]" style="background-image:repeating-linear-gradient(135deg,rgba(233,162,59,.18) 0 6px,transparent 6px 12px)"></div>
                    <?php foreach ($clips as [$left, $width, $tone]): ?>
                        <div class="absolute inset-y-2 rounded <?= $tone ?>" style="left:<?= $left ?>%;width:<?= $width ?>%"></div>
                    <?php endforeach; ?>
                    <div class="absolute inset-y-0 left-[2%] w-0.5 bg-danger"></div>
                    <div class="absolute inset-y-0 left-[72%] border-l-2 border-dashed border-warn"></div>
                </div>
            </div>
        </div>

        <p class="relative font-mono text-[11px] text-dim">Acesso restrito · conexão protegida</p>
    </section>

    <section class="flex items-center justify-center px-5 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center gap-3 lg:hidden"><?= brand_mark('size-9') ?><span class="display text-xl font-extrabold">Controladoria</span></div>

            <?php if ($step === '2fa'): ?>
                <span class="grid size-11 place-items-center rounded-xl bg-accent/12 text-accent"><?= icon('smartphone', 'size-5') ?></span>
                <h2 class="display mt-5 text-[32px] leading-none font-extrabold">Verificação em 2 etapas</h2>
                <p class="mt-2 text-sm text-muted">Digite o código de 6 dígitos que aparece no seu app autenticador.</p>
                <?= form_open('/login/2fa', 'mt-6 space-y-4') ?>
                    <div>
                        <label for="code" class="label">Código</label>
                        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus
                               class="input h-12 text-center font-mono text-2xl tracking-[0.4em]" placeholder="000000">
                    </div>
                    <?= $errorHtml($error) ?>
                    <?= submit_button('Confirmar', 'btn btn-primary h-11 w-full', 'Verificando…') ?>
                </form>
                <?= form_open('/login/cancelar', 'mt-3') ?>
                    <button type="submit" class="btn btn-ghost w-full"><?= icon('arrow-left') ?>Usar outra conta</button>
                </form>

            <?php elseif ($step === 'expirado'): ?>
                <span class="grid size-11 place-items-center rounded-xl bg-danger/12 text-danger"><?= icon('clock', 'size-5') ?></span>
                <h2 class="display mt-5 text-[32px] leading-none font-extrabold">Tempo esgotado</h2>
                <div class="mt-6 space-y-4">
                    <?= $errorHtml($error) ?>
                    <?= form_open('/login/cancelar') ?>
                        <button type="submit" class="btn btn-secondary w-full">Voltar para o login</button>
                    </form>
                </div>

            <?php else: ?>
                <span class="grid size-11 place-items-center rounded-xl bg-panel-2 text-muted"><?= icon('lock-keyhole', 'size-5') ?></span>
                <h2 class="display mt-5 text-[36px] leading-none font-extrabold">Entrar no painel</h2>
                <p class="mt-2 text-sm text-muted">Use o e-mail e a senha que o administrador cadastrou para você.</p>
                <?= form_open('/login', 'mt-7 space-y-4') ?>
                    <div>
                        <label for="email" class="label">E-mail</label>
                        <input id="email" name="email" type="email" autocomplete="username" required autofocus value="<?= e($email) ?>" class="input h-11">
                    </div>
                    <div>
                        <label for="password" class="label">Senha</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="input h-11">
                    </div>
                    <?= $errorHtml($error) ?>
                    <?= submit_button('Entrar', 'btn btn-primary h-11 w-full', 'Entrando…') ?>
                </form>
                <p class="mt-6 text-xs text-dim">Esqueceu a senha? Peça ao administrador para redefinir. Por segurança, o acesso é bloqueado por 15 minutos após 5 tentativas erradas.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
