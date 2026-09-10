<?php
defined('CONTROLADORIA') || exit;

$sectionStart = static fn (string $iconName, string $title, string $description): string =>
    '<div class="grid gap-4 border-b border-line px-5 py-5 md:grid-cols-[240px_1fr]"><div class="flex gap-3">' . icon($iconName, 'mt-0.5 size-4 shrink-0 text-dim')
    . '<div><p class="text-sm font-bold">' . e($title) . '</p><p class="mt-0.5 text-xs text-muted">' . e($description) . '</p></div></div><div class="grid gap-4 sm:grid-cols-2">';
$sectionEnd = '</div></div>';
$formatDay = static fn (?string $value): string => $value ? implode('/', array_reverse(explode('-', $value))) : 'nunca';
?>
<?= page_header('Alertas automáticos', 'Automação', 'O bot avisa o grupo sozinho quando algo precisa de atenção, sem ninguém precisar perguntar.') ?>

<?php if (!$bot['ativo'] || $bot['grupoId'] === ''): ?>
    <?= notice('warn', 'triangle-alert', 'Os alertas ainda não estão sendo enviados.',
        e($bot['ativo'] ? 'Defina o ID do grupo do WhatsApp na página do bot.' : 'O bot está desligado.'),
        '<a href="/bot" class="btn btn-secondary btn-sm">Configurar bot</a>', 'mb-6') ?>
<?php endif; ?>

<div class="grid gap-6 xl:grid-cols-[1fr_360px]">
    <section class="card">
        <?= form_open('/alertas') ?>
            <fieldset <?= $editable ? '' : 'disabled' ?>>
                <?= $sectionStart('calendar-clock', 'Estoque baixo', 'Aviso diário quando os vídeos programados não cobrem o mínimo de dias.') ?>
                    <div>
                        <label for="estoqueMinimoDias" class="label">Mínimo de dias de estoque</label>
                        <input id="estoqueMinimoDias" name="estoqueMinimoDias" type="number" min="1" max="90" value="<?= (int) $alerts['estoqueMinimoDias'] ?>" class="input">
                    </div>
                    <div>
                        <label for="alertaEstoqueHora" class="label">Horário da verificação</label>
                        <input id="alertaEstoqueHora" name="alertaEstoqueHora" type="time" value="<?= e($alerts['alertaEstoqueHora']) ?>" class="input">
                    </div>
                <?= $sectionEnd ?>

                <?= $sectionStart('image-off', 'Capa pendente', 'Aviso quando um vídeo está perto de publicar e ainda não tem thumbnail.') ?>
                    <div>
                        <label for="capaAvisoDias" class="label">Avisar com quantos dias de antecedência</label>
                        <input id="capaAvisoDias" name="capaAvisoDias" type="number" min="1" max="30" value="<?= (int) $alerts['capaAvisoDias'] ?>" class="input">
                        <p class="hint">Enviado junto com a verificação de estoque.</p>
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
                        <input id="resumoSemanalHora" name="resumoSemanalHora" type="time" value="<?= e($alerts['resumoSemanalHora']) ?>" class="input">
                    </div>
                <?= $sectionEnd ?>

                <?= $sectionStart('bell-ring', 'Relatório mensal', 'Todo dia 1º: views, horas, inscritos e os mais vistos do mês anterior.') ?>
                    <label class="flex items-center gap-3 sm:col-span-2">
                        <input type="checkbox" name="relatorioMensalAtivo" <?= $alerts['relatorioMensalAtivo'] ? 'checked' : '' ?> class="checkbox">
                        <span class="text-sm font-semibold">Enviar relatório mensal</span>
                    </label>
                    <div>
                        <label for="relatorioMensalHora" class="label">Horário</label>
                        <input id="relatorioMensalHora" name="relatorioMensalHora" type="time" value="<?= e($alerts['relatorioMensalHora']) ?>" class="input">
                    </div>
                <?= $sectionEnd ?>

                <?= $sectionStart('flame', 'Vídeo em alta', 'Quantas vezes acima da mediana do canal um vídeo precisa ir para ser considerado viral.') ?>
                    <div>
                        <label for="viralMultiplicador" class="label">Multiplicador</label>
                        <input id="viralMultiplicador" name="viralMultiplicador" inputmode="decimal" value="<?= e(fmt_decimal((float) $alerts['viralMultiplicador'])) ?>" class="input">
                        <p class="hint">Ex.: 2 = o dobro das views de um vídeo típico em 7 dias.</p>
                    </div>
                <?= $sectionEnd ?>

                <?php if ($editable): ?>
                    <div class="flex justify-end px-5 py-4"><?= submit_button('Salvar alertas', 'btn btn-primary', 'Salvando…') ?></div>
                <?php endif; ?>
            </fieldset>
        </form>
    </section>

    <div class="space-y-6">
        <section class="card">
            <?= card_header('Prévia do alerta de estoque', 'Com os dados de agora', 'message-square') ?>
            <div class="bg-[#0c1318] p-5">
                <div class="max-w-[92%] rounded-lg rounded-tl-sm bg-panel-3 px-3 py-2 text-sm whitespace-pre-wrap"><p class="mb-0.5 text-xs font-bold text-accent">Controladoria</p><?= whatsapp_html($preview) ?></div>
                <?php if ($stock['days_covered'] >= $alerts['estoqueMinimoDias']): ?>
                    <p class="mt-3 text-xs text-dim">Hoje o estoque está acima do mínimo, então esse alerta não seria enviado.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <?= card_header('Últimos envios', '', 'bell-ring') ?>
            <div class="p-5">
                <?= key_value([
                    ['Verificação diária', e($formatDay($state['ultimoAlertaEstoque']))],
                    ['Alerta de capa', e($formatDay($state['ultimoAlertaCapa']))],
                    ['Resumo semanal', e($formatDay($state['ultimoResumoSemanal']))],
                    ['Relatório mensal', e($state['ultimoRelatorioMensal'] ?? 'nunca')],
                ]) ?>
                <p class="mt-4 text-xs text-dim">Todos os horários seguem o fuso de São Paulo.</p>
            </div>
        </section>

        <section class="card">
            <?= card_header('Como os envios rodam', '', 'clock') ?>
            <div class="space-y-2 p-5 text-sm text-muted">
                <p>Na Hostinger, crie um <strong class="text-ink">Cron Job</strong> do tipo PHP a cada 5 minutos apontando para <code class="font-mono text-ink">public_html/bin/cron.php</code>.</p>
                <p class="text-xs text-dim"><?= $hasCronToken ? 'Também dá para usar a URL /api/cron com o token do arquivo de configuração.' : 'Para usar a URL /api/cron, defina cron_token no arquivo de configuração.' ?></p>
            </div>
        </section>
    </div>
</div>
