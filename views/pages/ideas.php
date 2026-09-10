<?php
defined('CONTROLADORIA') || exit;

$sourceLabels = IDEA_SOURCES;
$statusTone = ['nova' => 'neutral', 'aprovada' => 'ok', 'gravada' => 'info', 'descartada' => 'danger'];
$nextActions = [
    'nova' => [['aprovada', 'Aprovar', 'check', true], ['descartada', 'Descartar', 'x', false]],
    'aprovada' => [['gravada', 'Marcar como gravada', 'clapperboard', true], ['nova', 'Voltar para novas', 'rotate-ccw', false]],
    'gravada' => [['aprovada', 'Reabrir', 'rotate-ccw', false]],
    'descartada' => [['nova', 'Restaurar', 'rotate-ccw', false]],
];
$total = array_sum($counts);
$returnUrl = '/ideias?status=' . $tab;

$generateHtml = $canOperate
    ? form_open('/ideias/gerar') . submit_button(icon('sparkles') . ($geminiOn ? 'Gerar ideias com IA' : 'Gerar ideias de exemplo'), 'btn btn-primary', 'Gerando ideias…') . '</form>'
    : '';
?>
<?= page_header('Ideias', 'Pauta', 'Sugestões de vídeo baseadas no que o público busca e no que performou bem.', $generateHtml) ?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
    <div class="min-w-0">
        <nav class="-mx-1 mb-4 flex gap-1 overflow-x-auto px-1" aria-label="Filtrar por status">
            <?php foreach (IDEA_TABS as $key => $label): ?>
                <a href="/ideias?status=<?= e($key) ?>" class="btn btn-sm shrink-0 <?= $tab === $key ? 'bg-panel-3 text-ink' : 'btn-ghost' ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                    <?= e($label) ?> <span class="font-mono text-[11px] text-dim"><?= $key === 'todas' ? $total : ($counts[$key] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($ideas): ?>
            <ul class="space-y-3">
                <?php foreach ($ideas as $idea): ?>
                    <li class="card flex gap-3 p-4 sm:gap-4 sm:p-5">
                        <div class="grid size-10 shrink-0 place-items-center rounded-xl font-display text-base font-extrabold tabular-nums sm:size-12 sm:text-lg <?= $idea['score'] >= 75 ? 'bg-accent/12 text-accent' : ($idea['score'] >= 50 ? 'bg-info/12 text-info' : 'bg-panel-3 text-muted') ?>" title="Potencial estimado (0 a 100)">
                            <?= (int) $idea['score'] ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <?= badge($sourceLabels[$idea['source']] ?? $idea['source']) ?>
                                <?= $idea['generated_by'] === 'ia' ? badge('IA', 'info', '', 'sparkles') : '' ?>
                                <?= $idea['generated_by'] === 'exemplo' ? badge('Exemplo') : '' ?>
                                <?= $tab === 'todas' ? badge(IDEA_TABS[$idea['status']] ?? $idea['status'], $statusTone[$idea['status']] ?? 'neutral') : '' ?>
                                <span class="font-mono text-[11px] text-dim"><?= e(fmt_ago($idea['created_at'])) ?></span>
                            </div>
                            <h3 class="mt-2 text-[15px] font-bold break-words text-balance"><?= e($idea['title']) ?></h3>
                            <p class="mt-1 text-sm break-words text-muted"><?= e($idea['rationale']) ?></p>
                            <?php if ($canOperate): ?>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <?= form_open('/ideias/status', 'flex flex-wrap gap-2') ?>
                                        <input type="hidden" name="id" value="<?= (int) $idea['id'] ?>">
                                        <input type="hidden" name="_voltar" value="<?= e($returnUrl) ?>">
                                        <?php foreach ($nextActions[$idea['status']] ?? [] as [$status, $label, $iconName, $primary]): ?>
                                            <?= submit_button(icon($iconName, 'size-3.5') . e($label), 'btn btn-sm ' . ($primary ? 'btn-secondary' : 'btn-ghost'), null, 'status', $status) ?>
                                        <?php endforeach; ?>
                                    </form>
                                    <?php if (in_array($idea['status'], ['nova', 'aprovada'], true)): ?>
                                        <a href="/videos/novo?ideia=<?= (int) $idea['id'] ?>" class="btn btn-ghost btn-sm"><?= icon('film', 'size-3.5') ?>Virar vídeo</a>
                                    <?php endif; ?>
                                    <a href="/ideias/editar?id=<?= (int) $idea['id'] ?>" class="btn btn-ghost btn-sm"><?= icon('pencil', 'size-3.5') ?>Editar</a>
                                    <?php if ($canDelete): ?>
                                        <?= form_open('/ideias/excluir', 'sm:ml-auto', 'data-confirm="Excluir esta ideia? Não dá para desfazer."') ?>
                                            <input type="hidden" name="id" value="<?= (int) $idea['id'] ?>">
                                            <input type="hidden" name="_voltar" value="<?= e($returnUrl) ?>">
                                            <?= submit_button(icon('trash-2', 'size-3.5') . 'Excluir', 'btn btn-ghost btn-sm text-danger') ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <section class="card">
                <?= empty_state('lightbulb', 'Nenhuma ideia aqui', $tab === 'nova' ? 'Gere sugestões com o botão acima ou cadastre uma ideia manualmente.' : 'Nenhuma ideia com este status.') ?>
            </section>
        <?php endif; ?>
    </div>

    <div class="min-w-0 space-y-6">
        <?php if ($canOperate): ?>
            <section class="card">
                <?= card_header('Cadastrar ideia', '', 'plus') ?>
                <?= form_open('/ideias/nova', 'space-y-3 p-4 sm:p-5') ?>
                    <div>
                        <label for="title" class="label">Título</label>
                        <input id="title" name="title" required minlength="5" maxlength="200" class="input" placeholder="Ex.: Respondendo quem pediu parte 2">
                    </div>
                    <div>
                        <label for="rationale" class="label">Por que vale gravar</label>
                        <textarea id="rationale" name="rationale" required minlength="5" maxlength="1000" rows="3" class="input h-auto py-2"></textarea>
                    </div>
                    <?= submit_button('Salvar ideia', 'btn btn-secondary w-full', 'Salvando…') ?>
                </form>
            </section>
        <?php endif; ?>

        <section class="card">
            <?= card_header('Buscas em alta', 'Termos que mais cresceram este mês', 'search') ?>
            <div class="p-4 sm:p-5">
                <?php if ($rising): ?>
                    <?= bar_list(array_map(static fn ($term) => [
                        'labelHtml' => e($term['term']),
                        'value' => $term['views'],
                        'tone' => 'info',
                        'metaHtml' => e($term['growth'] === null ? 'novo neste mês' : ($term['growth'] >= 0 ? '▲ ' : '▼ ') . abs((int) round($term['growth'] * 100)) . '%'),
                    ], $rising)) ?>
                <?php else: ?>
                    <p class="text-sm text-muted">Sem dados de busca ainda.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
