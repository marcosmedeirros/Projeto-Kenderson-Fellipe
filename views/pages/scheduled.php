<?php
defined('CONTROLADORIA') || exit;

$warnDays = (int) $alerts['capaAvisoDias'];
if ($stock['total']) {
    $description = e(plural($stock['total'], 'agendado', 'agendados') . ', cobrindo até ') . '<strong class="text-ink">' . e(fmt_date($stock['last_date'])) . '</strong>' . e(' (' . plural($stock['days_covered'], 'dia', 'dias') . ').');
    if ($stock['days_covered'] < $alerts['estoqueMinimoDias']) {
        $description .= ' <span class="text-warn">' . e('Abaixo do mínimo de ' . plural((int) $alerts['estoqueMinimoDias'], 'dia', 'dias') . '.') . '</span>';
    }
} else {
    $description = 'Nenhum vídeo agendado no momento.';
}
$actions = $canManage ? '<a href="/videos/novo" class="btn btn-primary">' . icon('plus') . 'Novo vídeo</a>' : '';

$videoActions = static function (array $video) use ($canManage): string {
    $html = '';
    if ($canManage && $video['thumbnail_status'] !== 'ok') {
        $html .= '<a href="/capas/biblioteca?video=' . e(rawurlencode($video['id'])) . '#enviar" class="btn btn-ghost btn-sm">' . icon('upload', 'size-3.5') . 'Capa</a>';
    }
    if ($video['youtube_id']) {
        $html .= '<a href="https://studio.youtube.com/video/' . e(rawurlencode($video['youtube_id'])) . '/edit" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm" aria-label="Abrir no YouTube Studio">'
            . icon('external-link', 'size-3.5') . 'Studio</a>';
    }
    if ($canManage) {
        $html .= '<a href="/videos/editar?id=' . e(rawurlencode($video['id'])) . '" class="btn btn-secondary btn-sm">' . icon('pencil', 'size-3.5') . 'Editar</a>';
    }
    return $html;
};
?>
<?= page_header('Vídeos programados', 'Canal', $description, $actions) ?>

<section class="card">
    <?= card_header('Linha do tempo · 45 dias', '', 'calendar-clock') ?>
    <div class="p-4 sm:p-5"><?= stock_timeline($videos, 45, (int) $alerts['estoqueMinimoDias']) ?></div>
</section>

<section class="card mt-6">
    <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
        <nav class="-mx-1 flex min-w-0 gap-1 overflow-x-auto px-1" aria-label="Filtrar vídeos">
            <?php foreach (SCHEDULED_FILTERS as $key => $label): ?>
                <a href="<?= $key === 'todos' ? '/programados' : '/programados?filtro=' . e($key) ?>"
                   class="btn btn-sm shrink-0 <?= $filter === $key ? 'bg-panel-3 text-ink' : 'btn-ghost' ?>" <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <span class="shrink-0 font-mono text-xs text-dim"><?= count($filtered) ?> de <?= count($videos) ?></span>
    </div>

    <?php if ($filtered): ?>
        <ul class="divide-y divide-line md:hidden">
            <?php foreach ($filtered as $video): $days = days_until($video['publish_at']); $actionsHtml = $videoActions($video); ?>
                <li class="flex gap-3 px-4 py-3.5">
                    <?= video_thumb_html($video, 'w-24') ?>
                    <div class="min-w-0 flex-1">
                        <p class="leading-snug font-semibold"><?= e($video['title']) ?></p>
                        <p class="mt-1 text-xs text-muted">
                            <?= e(fmt_weekday($video['publish_at']) . ' ' . fmt_date($video['publish_at']) . ' · ' . fmt_time($video['publish_at'])) ?>
                            · <span class="<?= $days <= $warnDays ? 'text-warn' : '' ?>"><?= e(fmt_in_days($days)) ?></span>
                        </p>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <?= $video['thumbnail_status'] === 'ok' ? badge('Capa ok', 'ok') : badge('Sem capa', 'warn') ?>
                            <?= $video['is_short'] ? badge('Short', 'info') : '' ?>
                        </div>
                        <?php if ($actionsHtml !== ''): ?><div class="mt-2 flex flex-wrap gap-1"><?= $actionsHtml ?></div><?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="hidden overflow-x-auto md:block">
            <table class="table-base">
                <thead><tr><th>Publicação</th><th>Vídeo</th><th>Duração</th><th>Capa</th><th class="text-right">Ações</th></tr></thead>
                <tbody>
                <?php foreach ($filtered as $video): $days = days_until($video['publish_at']); ?>
                    <tr class="hover:bg-panel-2/50">
                        <td class="whitespace-nowrap">
                            <span class="font-semibold tabular-nums"><?= e(fmt_weekday($video['publish_at']) . ' ' . fmt_date($video['publish_at'])) ?></span>
                            <span class="ml-2 font-mono text-xs text-muted"><?= e(fmt_time($video['publish_at'])) ?></span>
                            <div class="text-xs <?= $days <= $warnDays ? 'text-warn' : 'text-dim' ?>"><?= e(fmt_in_days($days)) ?></div>
                        </td>
                        <td>
                            <div class="flex items-center gap-3">
                                <?= video_thumb_html($video, 'w-16') ?>
                                <span class="font-semibold"><?= e($video['title']) ?></span>
                                <?= $video['is_short'] ? badge('Short', 'info') : '' ?>
                            </div>
                        </td>
                        <td class="font-mono text-xs text-muted tabular-nums"><?= e(fmt_duration($video['duration_sec'])) ?></td>
                        <td><?= $video['thumbnail_status'] === 'ok' ? badge('Capa ok', 'ok') : badge('Sem capa', 'warn') ?></td>
                        <td><div class="flex justify-end gap-1"><?= $videoActions($video) ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= empty_state('calendar-clock', 'Nenhum vídeo neste filtro', $canManage ? 'Cadastre um vídeo agendado para ele aparecer aqui.' : '', $canManage ? '<a href="/videos/novo" class="btn btn-secondary btn-sm">' . icon('plus', 'size-3.5') . 'Novo vídeo</a>' : '') ?>
    <?php endif; ?>
</section>
