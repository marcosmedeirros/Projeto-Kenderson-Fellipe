<?php
defined('CONTROLADORIA') || exit;

if ($stock['total']) {
    $description = e($stock['total'] . ' agendados, cobrindo até ') . '<strong class="text-ink">' . e(fmt_date($stock['last_date'])) . '</strong>' . e(' (' . plural($stock['days_covered'], 'dia', 'dias') . ').');
    if ($stock['days_covered'] < $alerts['estoqueMinimoDias']) {
        $description .= ' <span class="text-warn">' . e('Abaixo do mínimo de ' . $alerts['estoqueMinimoDias'] . ' dias.') . '</span>';
    }
} else {
    $description = 'Nenhum vídeo agendado no momento.';
}
?>
<?= page_header('Vídeos programados', 'Canal', $description) ?>

<section class="card">
    <?= card_header('Linha do tempo · 45 dias', '', 'calendar-clock') ?>
    <div class="p-5"><?= stock_timeline($videos, 45, (int) $alerts['estoqueMinimoDias']) ?></div>
</section>

<section class="card mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
        <nav class="flex flex-wrap gap-1" aria-label="Filtrar vídeos">
            <?php foreach (SCHEDULED_FILTERS as $key => $label): ?>
                <a href="<?= $key === 'todos' ? '/programados' : '/programados?filtro=' . e($key) ?>"
                   class="btn btn-sm <?= $filter === $key ? 'bg-panel-3 text-ink' : 'btn-ghost' ?>" <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <span class="font-mono text-xs text-dim"><?= count($filtered) ?> de <?= count($videos) ?></span>
    </div>

    <?php if ($filtered): ?>
        <div class="overflow-x-auto">
            <table class="table-base min-w-[720px]">
                <thead><tr><th>Publicação</th><th>Vídeo</th><th>Duração</th><th>Capa</th><th class="text-right">Studio</th></tr></thead>
                <tbody>
                <?php foreach ($filtered as $video): $days = days_until($video['publish_at']); ?>
                    <tr class="hover:bg-panel-2/50">
                        <td class="whitespace-nowrap">
                            <span class="font-semibold tabular-nums"><?= e(fmt_weekday($video['publish_at']) . ' ' . fmt_date($video['publish_at'])) ?></span>
                            <span class="ml-2 font-mono text-xs text-muted"><?= e(fmt_time($video['publish_at'])) ?></span>
                            <div class="text-xs <?= $days <= 3 ? 'text-warn' : 'text-dim' ?>"><?= e(fmt_in_days($days)) ?></div>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold"><?= e($video['title']) ?></span>
                                <?= $video['is_short'] ? badge('Short', 'info') : '' ?>
                            </div>
                        </td>
                        <td class="font-mono text-xs text-muted tabular-nums"><?= e(fmt_duration($video['duration_sec'])) ?></td>
                        <td><?= $video['thumbnail_status'] === 'ok' ? badge('Capa ok', 'ok') : badge('Sem capa', 'warn') ?></td>
                        <td class="text-right">
                            <?php if (str_starts_with($video['id'], 'demo-')): ?>
                                <span class="text-xs text-dim">exemplo</span>
                            <?php else: ?>
                                <a href="https://studio.youtube.com/video/<?= e(rawurlencode($video['id'])) ?>/edit" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm">Abrir <?= icon('external-link', 'size-3.5') ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= empty_state('calendar-clock', 'Nenhum vídeo neste filtro') ?>
    <?php endif; ?>
</section>
