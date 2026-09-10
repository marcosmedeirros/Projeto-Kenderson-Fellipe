<?php
defined('CONTROLADORIA') || exit;

$sectionStart = static fn (string $iconName, string $title, string $description): string =>
    '<div class="grid gap-4 border-b border-line px-4 py-5 sm:px-5 md:grid-cols-[240px_1fr]"><div class="flex gap-3">' . icon($iconName, 'mt-0.5 size-4 shrink-0 text-dim')
    . '<div><p class="text-sm font-bold">' . e($title) . '</p><p class="mt-0.5 text-xs text-muted">' . e($description) . '</p></div></div><div class="grid gap-4 sm:grid-cols-2">';
$sectionEnd = '</div></div>';
$sentAt = static fn (?string $value): string => $value ? fmt_full_date(from_db($value)) : 'nunca';

$blockers = [];
if (!$bot['ativo']) {
    $blockers[] = 'O bot está desligado.';
}
if ($bot['grupoId'] === '') {
    $blockers[] = 'Defina o ID do grupo do WhatsApp na página do bot.';
}
if (!$evolutionOn) {
    $blockers[] = 'Cadastre o Evolution em Integrações.';
}
?>
<?= page_header('Alertas automáticos', 'Automação', 'O bot avisa o grupo sozinho quando algo precisa de atenção, sem ninguém precisar perguntar.') ?>

<?php if ($blockers): ?>
    <?= notice('warn', 'triangle-alert', 'Os alertas ainda não estão sendo enviados.', e(implode(' ', $blockers)),
        !$evolutionOn && $bot['ativo'] && $bot['grupoId'] !== '' ? '<a href="/integracoes" class="btn btn-secondary btn-sm">Integrações</a>' : '<a href="/bot" class="btn btn-secondary btn-sm">Configurar bot</a>', 'mb-6') ?>
<?php endif; ?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <section class="card min-w-0">
        <?= form_open('/alertas') ?>
            <fieldset <?= $editable ? '' : 'disabled' ?>>
                <?= $sectionStart('calendar-clock', 'Estoque baixo', 'Aviso diário quando os vídeos programados não cobrem o mínimo de dias.') ?>
                    <div>
                        <label for="estoqueMinimoDias" class="label">Mínimo de dias de estoque</label>
                        <input id="estoqueMinimoDias" name="estoqueMinimoDias" type="number" min="1" max="90" value="<?= (int) $alerts['estoqueMinimoDias'] ?>" class="input">
                    </div>
                    <div>
                        <label for="alertaEstoqueHora" class="label">Horário da verificação</label>
                        <input id="alertaEstoqueHora" name="alertaEstoqueHora" type="time" max="23:55" value="<?= e($alerts['alertaEstoqueHora']) ?>" class="input">
                    </div>
                <?= $sectionEnd ?>

                <?= $sectionStart('image-off', 'Capa pendente', 'Aviso quando um vídeo está perto de publicar e ainda não tem thumbnail.') ?>
                    <div>
                        <label for="capaAvisoDias" class="label">Avisar com quantos dias de antecedência</label>
                        <input id="capaAvisoDias" name="capaAvisoDias" type="number" min="1" max="30" value="<?= (int) $alerts['capaAvisoDias'] ?>" class="input">
                        <p class="hint">Enviado no mesmo horário da verificação de estoque.</p>
                    </div>
                <?= $sectionEnd ?>

                <?= $sectionStart('message-square', 'Resumo semanal', 'O mesmo conteúdo do /resumo, enviado no dia e horário escolhidos.') ?>
                    <label class="flex items-center gap-3 sm:col-span-2">
                        <input type="checkbox" name="resumoSemanalAtivo" <?= $alerts['resumoSemanalAtivo'] ? 'checked' : '' ?> class="checkbox">
                        <span class="text-sm font-semibold">Enviar resumo semanal</span>
                    </label>
                    <div>
                        <label for="resumoSemanalDia" class="label">Dia</label>
                        <select id="resumoSemanalDia" name="resumoSemanalDia" class="input">
                            <?php foreach (WEEKDAY_LABELS as $index => $label): ?>
                                <option value="<?= $index ?>" <?= (int) $alerts['resumoSemanalDia'] === $index ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="resumoSemanalHora" class="label">Horário</label>
                        <input id="resumoSemanalHora" name="resumoSemanalHora" type="time" max="23:55" value="<?= e($alerts['resumoSemanalHora']) ?>" class="input">
                    </div>
                <?= $sectionEnd ?>

                <?= $sectionStart('bell-ring', 'Relatório mensal', 'Todo dia 1º: views, horas, inscritos e os mais vistos do mês anterior.') ?>
                    <label class="flex items-center gap-3 sm:col-span-2">
                        <input type="checkbox" name="relatorioMensalAtivo" <?= $alerts['relatorioMensalAtivo'] ? 'checked' : '' ?> class="checkbox">
                        <span class="text-sm font-semibold">Enviar relatório mensal</span>
                    </label>
                    <div>
                        <label for="relatorioMensalHora" class="label">Horário</label>
                        <input id="relatorioMensalHora" name="relatorioMensalHora" type="time" max="23:55" value="<?= e($alerts['relatorioMensalHora']) ?>" class="input">
                    </div>
                <?= $sectionEnd ?>

                <?= $sectionStart('flame', 'Vídeo em alta', 'Quantas vezes acima da mediana do canal um vídeo precisa ir para ser considerado viral.') ?>
                    <div>
                        <label for="viralMultiplicador" class="label">Multiplicador</label>
                        <input id="viralMultiplicador" name="viralMultiplicador" inputmode="decimal" value="<?= e(fmt_decimal_trim((float) $alerts['viralMultiplicador'])) ?>" class="input">
                        <p class="hint">Ex.: 2 = o dobro das views de um vídeo típico em 7 dias.</p>
                    </div>
                <?= $sectionEnd ?>

                <?php if ($editable): ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-5">
                        <p class="text-xs text-dim">Horários entre 00:00 e 23:55, no fuso de São Paulo.</p>
                        <?= submit_button('Salvar alertas', 'btn btn-primary w-full sm:w-auto', 'Salvando…') ?>
                    </div>
                <?php endif; ?>
            </fieldset>
        </form>
    </section>

    <div class="min-w-0 space-y-6">
        <section class="card">
            <?= card_header('Prévia do alerta de estoque', 'Com os dados de agora', 'message-square') ?>
            <div class="bg-[#0c1318] p-4 sm:p-5">
                <div class="max-w-[92%] rounded-lg rounded-tl-sm bg-panel-3 px-3 py-2 text-sm break-words whitespace-pre-wrap"><p class="mb-0.5 text-xs font-bold text-accent">Controladoria</p><?= whatsapp_html($preview) ?></div>
                <?php if ($stock['days_covered'] >= $alerts['estoqueMinimoDias']): ?>
                    <p class="mt-3 text-xs text-dim">Hoje o estoque está acima do mínimo, então esse alerta não seria enviado.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <?= card_header('Últimos envios', 'Só conta quando o WhatsApp confirmou a entrega', 'bell-ring') ?>
            <div class="p-5">
                <?= key_value([
                    ['Estoque baixo', e($sentAt($state['alertaEstoqueEnviadoEm']))],
                    ['Capa pendente', e($sentAt($state['alertaCapaEnviadoEm']))],
                    ['Resumo semanal', e($sentAt($state['resumoSemanalEnviadoEm']))],
                    ['Relatório mensal', e($sentAt($state['relatorioMensalEnviadoEm']))],
                ]) ?>
                <p class="mt-4 text-xs text-dim">Se um envio falhar, o sistema tenta de novo 30 minutos depois.</p>
            </div>
        </section>

        <section class="card">
            <?= card_header('Como os envios rodam', '', 'clock') ?>
            <div class="space-y-2 p-5 text-sm text-muted">
                <p>Na Hostinger, crie um <strong class="text-ink">Cron Job</strong> do tipo PHP a cada 5 minutos apontando para <code class="font-mono break-all text-ink">public_html/bin/cron.php</code>.</p>
                <p class="text-xs text-dim"><?= $hasCronToken
                    ? 'Também dá para chamar POST /api/cron enviando o token no cabeçalho X-Cron-Token.'
                    : 'Para usar POST /api/cron, defina cron_token no arquivo de configuração.' ?></p>
            </div>
        </section>
    </div>
</div>
