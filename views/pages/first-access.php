<?php defined('CONTROLADORIA') || exit; ?>
<main class="flex min-h-screen items-center justify-center px-5 py-12">
    <div class="w-full max-w-md">
        <div class="mb-8 flex items-center gap-3"><?= brand_mark('size-9') ?><span class="display text-xl font-extrabold">Controladoria</span></div>
        <div class="card p-6 sm:p-8">
            <span class="grid size-11 place-items-center rounded-xl bg-warn/12 text-warn"><?= icon('key-round', 'size-5') ?></span>
            <h1 class="display mt-5 text-[32px] leading-none font-extrabold">Crie sua senha</h1>
            <p class="mt-2 text-sm text-muted">Olá, <?= e(explode(' ', trim($user['name']))[0]) ?>. Você entrou com uma senha temporária. Antes de continuar, defina uma senha só sua.</p>

            <?= form_open('/primeiro-acesso', 'mt-6 space-y-4') ?>
                <div>
                    <label for="current" class="label">Senha temporária</label>
                    <input id="current" name="current" type="password" autocomplete="current-password" required class="input h-11">
                </div>
                <div>
                    <label for="password" class="label">Nova senha</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required class="input h-11">
                    <p class="hint">Pelo menos 10 caracteres, com letras e números.</p>
                </div>
                <div>
                    <label for="confirm" class="label">Confirme a nova senha</label>
                    <input id="confirm" name="confirm" type="password" autocomplete="new-password" minlength="10" maxlength="72" required class="input h-11">
                </div>
                <?php if ($error): ?>
                    <p class="flex items-start gap-2 text-sm text-danger" role="alert"><?= icon('circle-alert', 'mt-0.5 size-4 shrink-0') ?><?= e($error) ?></p>
                <?php endif; ?>
                <?= submit_button('Salvar e entrar', 'btn btn-primary h-11 w-full', 'Salvando…') ?>
            </form>
        </div>
    </div>
</main>
