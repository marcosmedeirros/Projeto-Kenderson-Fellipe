<?php
defined('CONTROLADORIA') || exit;

$connections = [
    'open' => ['Conectado', 'ok', 'O número do bot está online.'],
    'connecting' => ['Conectando', 'warn', 'O Evolution está reconectando o número.'],
    'close' => ['Desconectado', 'danger', 'Leia o QR code no Evolution para reconectar o número.'],
    'nao_configurado' => ['Não configurado', 'neutral', 'Cadastre o Evolution em Integrações.'],
    'erro' => ['Sem resposta', 'danger', 'Não foi possível falar com o Evolution. Confira a URL e a chave.'],
];
[$connectionLabel, $connectionTone, $connectionHint] = $connections[$connection] ?? $connections['erro'];
$originLabels = ['whatsapp' => ['WhatsApp', 'info'], 'simulador' => ['Simulador', 'neutral'], 'automacao' => ['Automático', 'warn']];
?>
<?= page_header('Bot do WhatsApp', 'Automação', 'O bot responde aos comandos no grupo com os mesmos dados deste painel.') ?>

<div class="grid gap-4 md:grid-cols-3">
    <div class="card p-5">
        <div class="flex items-center justify-between"><span class="eyebrow">Número do bot</span><?= icon('smartphone', 'size-4 text-dim') ?></div>
        <div class="mt-3"><?= badge($connectionLabel, $connectionTone, 'px-2.5 py-1 text-xs') ?></div>
        <p class="mt-2 text-[13px] text-muted"><?= e($connectionHint) ?></p>
    </div>
    <div class="card p-5">
        <div class="flex items-center justify-between"><span class="eyebrow">Grupo autorizado</span><?= icon('users', 'size-4 text-dim') ?></div>
        <p class="mt-3 font-semibold"><?= e($bot['grupoNome'] !== '' ? $bot['grupoNome'] : 'Sem nome') ?></p>
        <p class="mt-1 truncate font-mono text-xs text-muted"><?= e($bot['grupoId'] !== '' ? $bot['grupoId'] : 'ID do grupo não definido') ?></p>
        <?= !$bot['ativo'] ? badge('Bot desligado', 'warn', 'mt-2') : '' ?>
    </div>
    <div class="card p-5">
        <div class="flex items-center justify-between"><span class="eyebrow">Webhook</span><?= icon('link-2', 'size-4 text-dim') ?></div>
        <p class="mt-3 truncate font-mono text-xs text-ink" title="<?= e($webhookUrl) ?>"><?= e($webhookUrl) ?></p>
        <p class="mt-2 text-[13px] <?= $hasToken ? 'text-muted' : 'text-warn' ?>">
            <?= $hasToken ? 'Token definido no arquivo de configuração.' : 'Defina evolution_webhook_token no arquivo de configuração.' ?>
        </p>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_400px]">
    <section class="card">
        <?= card_header('Testar comandos', 'Veja a resposta antes de usar no grupo', 'message-square') ?>
        <div class="p-5">
            <div class="overflow-hidden rounded-xl border border-line" data-simulator>
                <div class="flex items-center gap-3 border-b border-line bg-panel-2 px-4 py-3">
                    <span class="grid size-9 place-items-center rounded-full bg-accent text-accent-ink"><?= icon('bot') ?></span>
                    <div class="leading-tight">
                        <p class="text-sm font-bold"><?= e($bot['grupoNome'] !== '' ? $bot['grupoNome'] : 'Grupo do canal') ?></p>
                        <p class="text-xs text-muted">Simulador · usa os dados reais do painel, não envia nada no WhatsApp</p>
                    </div>
                </div>
                <div class="h-[420px] space-y-2.5 overflow-y-auto bg-[#0c1318] p-4" data-simulator-log aria-live="polite">
                    <p class="mx-auto mt-24 max-w-xs text-center text-sm text-dim" data-simulator-empty>Toque em um comando abaixo para ver exatamente o que o bot responderia no grupo.</p>
                </div>
                <div class="border-t border-line bg-panel p-3">
                    <div class="mb-2.5 flex flex-wrap gap-1.5">
                        <?php foreach ($enabledCommands as $command): ?>
                            <button type="button" class="btn btn-secondary btn-sm font-mono text-xs" data-simulator-command="/<?= e($command) ?>">/<?= e($command) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <form class="flex gap-2" data-simulator-form>
                        <input name="text" maxlength="200" placeholder="Digite um comando…" class="input" aria-label="Mensagem" autocomplete="off">
                        <button type="submit" class="btn btn-primary px-3" aria-label="Enviar"><?= icon('send') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="card h-fit">
        <?= card_header('Configurações', '', 'settings') ?>
        <?php if ($canConfigure): ?>
            <?= form_open('/bot/config', 'space-y-5 p-5') ?>
                <label class="flex items-center justify-between gap-3 rounded-lg border border-line bg-panel-2 px-3 py-2.5">
                    <span>
                        <span class="block text-sm font-semibold">Bot ligado</span>
                        <span class="block text-xs text-muted">Desligado, ele ignora comandos e não envia alertas.</span>
                    </span>
                    <input type="checkbox" name="ativo" <?= $bot['ativo'] ? 'checked' : '' ?> class="checkbox">
                </label>
                <div>
                    <label for="grupoNome" class="label">Nome do grupo</label>
                    <input id="grupoNome" name="grupoNome" value="<?= e($bot['grupoNome']) ?>" maxlength="80" class="input">
                </div>
                <div>
                    <label for="grupoId" class="label">ID do grupo</label>
                    <input id="grupoId" name="grupoId" value="<?= e($bot['grupoId']) ?>" maxlength="80" class="input font-mono" placeholder="120363000000000000@g.us">
                    <p class="hint">O bot só responde nesse grupo. O ID aparece no Evolution, na lista de grupos.</p>
                </div>
                <fieldset>
                    <legend class="label">Comandos ativos</legend>
                    <div class="divide-y divide-line rounded-lg border border-line">
                        <?php foreach (BOT_COMMANDS as $command): ?>
                            <label class="flex items-start gap-3 px-3 py-2.5">
                                <input type="checkbox" name="cmd_<?= e($command) ?>" <?= !empty($bot['comandos'][$command]) ? 'checked' : '' ?> class="checkbox mt-0.5">
                                <span>
                                    <span class="block font-mono text-sm font-semibold"><?= e(COMMAND_INFO[$command]['label']) ?></span>
                                    <span class="block text-xs text-muted"><?= e(COMMAND_INFO[$command]['description']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?= submit_button('Salvar configurações', 'btn btn-primary w-full', 'Salvando…') ?>
            </form>
        <?php else: ?>
            <p class="p-5 text-sm text-muted">Só administradores e editores podem alterar as configurações do bot.</p>
        <?php endif; ?>
    </section>
</div>

<section class="card mt-6">
    <?= card_header('Histórico', 'Últimas 30 mensagens do bot', 'bot') ?>
    <?php if ($messages): ?>
        <div class="overflow-x-auto">
            <table class="table-base min-w-[640px]">
                <thead><tr><th>Quando</th><th>Origem</th><th>Quem</th><th>Mensagem</th></tr></thead>
                <tbody>
                <?php foreach ($messages as $message): [$originLabel, $originTone] = $originLabels[$message['origin']] ?? ['Outro', 'neutral']; ?>
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap text-muted"><?= e(fmt_datetime($message['created_at'])) ?></td>
                        <td><?= badge($originLabel, $originTone) ?></td>
                        <td class="text-sm whitespace-nowrap"><?= e($message['direction'] === 'entrada' ? ($message['sender'] ?? 'Grupo') : 'Bot') ?></td>
                        <td class="max-w-md">
                            <p class="truncate text-sm text-muted"><?= e(str_replace('*', '', strtok($message['text'], "\n"))) ?></p>
                            <?= !$message['delivered'] ? badge('Não entregue', 'danger', 'mt-1') : '' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= empty_state('bot', 'Nenhuma mensagem ainda') ?>
    <?php endif; ?>
</section>
