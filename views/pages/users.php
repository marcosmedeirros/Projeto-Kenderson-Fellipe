<?php defined('CONTROLADORIA') || exit; ?>
<?= page_header('Usuários', 'Administração', 'Quem acessa o painel e o que cada pessoa pode fazer.') ?>

<div class="grid gap-6 xl:grid-cols-[1fr_340px]">
    <section class="card min-w-0">
        <?= card_header(plural(count($users), 'usuário', 'usuários'), '', 'users') ?>
        <ul class="divide-y divide-line">
            <?php foreach ($users as $person): $self = $person['id'] === $admin['id']; ?>
                <li class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_auto]">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="font-semibold"><?= e($person['name']) ?></span>
                            <?= $self ? badge('Você', 'info') : '' ?>
                            <?= !$person['active'] ? badge('Desativado', 'danger') : '' ?>
                            <?= $person['must_change_password'] && $person['active'] ? badge('Aguardando 1º acesso', 'warn') : '' ?>
                            <?= $person['totp_enabled'] ? badge('2 etapas', 'ok', '', 'shield-check') : badge('Sem 2 etapas') ?>
                        </div>
                        <p class="mt-0.5 truncate text-sm text-muted"><?= e($person['email']) ?></p>
                        <p class="mt-0.5 text-xs text-dim"><?= e($person['last_login_at'] ? 'Último acesso ' . fmt_ago(from_db($person['last_login_at'])) : 'Nunca acessou') ?></p>
                    </div>

                    <?php if ($self): ?>
                        <p class="self-center text-sm text-muted"><?= e(ROLE_LABELS[$person['role']] ?? $person['role']) ?></p>
                    <?php else: ?>
                        <div class="flex flex-wrap items-start gap-2">
                            <?= form_open('/usuarios/perfil', 'flex gap-2') ?>
                                <input type="hidden" name="userId" value="<?= e($person['id']) ?>">
                                <select name="role" class="input h-8 w-auto py-0 text-[13px]" aria-label="Perfil de <?= e($person['name']) ?>">
                                    <?php foreach (ROLE_LABELS as $role => $label): ?>
                                        <option value="<?= e($role) ?>" <?= $person['role'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= submit_button('Salvar', 'btn btn-secondary btn-sm') ?>
                            </form>
                            <?= form_open('/usuarios/senha', '', 'data-confirm="Gerar uma nova senha temporária e encerrar as sessões deste usuário?"') ?>
                                <input type="hidden" name="userId" value="<?= e($person['id']) ?>">
                                <?= submit_button('Redefinir senha', 'btn btn-ghost btn-sm', 'Gerando…') ?>
                            </form>
                            <?= form_open('/usuarios/status', '', $person['active'] ? 'data-confirm="Desativar o acesso deste usuário?"' : '') ?>
                                <input type="hidden" name="userId" value="<?= e($person['id']) ?>">
                                <?= submit_button($person['active'] ? 'Desativar' : 'Reativar', $person['active'] ? 'btn btn-danger btn-sm' : 'btn btn-secondary btn-sm') ?>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="space-y-6">
        <section class="card">
            <?= card_header('Novo usuário', '', 'user-plus') ?>
            <?= form_open('/usuarios/novo', 'space-y-4 p-5') ?>
                <div>
                    <label for="name" class="label">Nome</label>
                    <input id="name" name="name" required maxlength="80" class="input">
                </div>
                <div>
                    <label for="email" class="label">E-mail</label>
                    <input id="email" name="email" type="email" required class="input">
                </div>
                <div>
                    <label for="role" class="label">Perfil</label>
                    <select id="role" name="role" class="input">
                        <?php foreach (ROLE_LABELS as $role => $label): ?>
                            <option value="<?= e($role) ?>" <?= $role === 'editor' ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?= submit_button('Cadastrar e gerar senha', 'btn btn-primary w-full', 'Cadastrando…') ?>
            </form>
        </section>

        <section class="card">
            <?= card_header('Perfis', '', 'shield-check') ?>
            <dl class="space-y-3 p-5 text-sm">
                <?php foreach (ROLE_LABELS as $role => $label): ?>
                    <div><dt class="font-semibold"><?= e($label) ?></dt><dd class="text-muted"><?= e(ROLE_DESCRIPTIONS[$role]) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>
    </div>
</div>
