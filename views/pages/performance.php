<?php
defined('CONTROLADORIA') || exit;

$delta = static fn (int $now, int $before): ?string => $before > 0 && $month !== $current
    ? ($now >= $before ? '▲ ' : '▼ ') . abs((int) round(($now - $before) / $before * 100)) . '% vs mês anterior'
    : null;
$chartPoints = array_map(static fn ($day) => ['label' => day_label($day['day']), 'value' => $day['views']], $series);
$multiplier = (float) $alerts['viralMultiplicador'];

$monthNav = '<div class="flex items-center gap-1 rounded-lg border border-line bg-panel p-1">'
    . '<a href="/desempenho?mes=' . e(shift_month($month, -1)) . '" class="btn btn-ghost btn-sm px-2" aria-label="Mês anterior">' . icon('chevron-left') . '</a>'
    . '<span class="min-w-[128px] text-center sm:min-w-[150px] text-sm font-semibold">' . e(month_label($month)) . '</span>'
    . ($month < $current
        ? '<a href="/desempenho?mes=' . e(shift_month($month, 1)) . '" class="btn btn-ghost btn-sm px-2" aria-label="Próximo mês">' . icon('chevron-right') . '</a>'
        : '<span class="btn btn-sm px-2 text-dim opacity-40">' . icon('chevron-right') . '</span>')
    . '</div>';
?>
<?= page_header('Desempenho', 'Canal', 'Views, retenção, vídeos em alta e o que as pessoas buscam para chegar ao canal.', $monthNav) ?>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?= stat_card(['label' => 'Views', 'icon' => 'eye', 'valueHtml' => e(fmt_compact($totals['views'])), 'hintHtml' => e($delta($totals['views'], $previous['views']) ?? $totals['days'] . ' dias com dados')]) ?>
    <?= stat_card(['label' => 'Horas assistidas', 'icon' => 'clock', 'valueHtml' => e(fmt_compact(round($totals['watch_minutes'] / 60))), 'hintHtml' => e($delta($totals['watch_minutes'], $previous['watch_minutes']) ?? '')]) ?>
    <?= stat_card(['label' => 'Inscritos', 'icon' => 'user-plus', 'valueHtml' => '+' . e(fmt_compact($totals['subscribers'])), 'hintHtml' => e($delta($totals['subscribers'], $previous['subscribers']) ?? '')]) ?>
    <?= stat_card(['label' => 'Retenção média', 'icon' => 'percent', 'valueHtml' => e((string) round($retention)) . '%', 'hintHtml' => 'ponderada pelas views']) ?>
</div>

<section class="card mt-6">
    <?= card_header('Views por dia · ' . mb_strtolower(month_label($month)), '', 'trending-up') ?>
    <div class="p-5"><?= area_chart($chartPoints, 'fmt_compact', 200) ?></div>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card">
        <?= card_header('Mais vistos no mês', '', 'eye') ?>
        <div class="p-5">
            <?php if ($top): ?>
                <?= bar_list(array_map(static fn ($video) => [
                    'labelHtml' => e($video['title']) . ($video['is_short'] ? ' ' . badge('Short', 'info', 'ml-1') : ''),
                    'value' => $video['views'],
                    'metaHtml' => e('Retenção ' . round($video['avg_view_pct']) . '% · +' . fmt_number($video['subscribers_gained']) . ' inscritos'),
                ], $top)) ?>
            <?php else: ?>
                <?= empty_state('eye', 'Sem dados neste mês') ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <?= card_header('O que o público buscou', 'Termos de busca do YouTube que trouxeram views', 'search') ?>
        <div class="p-5">
            <?php if ($rising): ?>
                <?= bar_list(array_map(static fn ($term) => [
                    'labelHtml' => e($term['term']),
                    'value' => $term['views'],
                    'tone' => $term['growth'] !== null && $term['growth'] > 0.3 ? 'warn' : 'info',
                    'metaHtml' => e($term['growth'] === null ? 'novo neste mês' : ($term['growth'] >= 0 ? '▲ ' : '▼ ') . abs((int) round($term['growth'] * 100)) . '% vs mês anterior'),
                ], $rising)) ?>
            <?php else: ?>
                <?= empty_state('search', 'Sem termos de busca neste mês') ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="card mt-6">
    <?= card_header('Vídeos em alta', e('Views nos 7 primeiros dias acima de ' . fmt_decimal_trim($multiplier) . 'x a mediana do canal (últimos 45 dias)'), 'flame') ?>
    <?php if ($virals['recent']): ?>
        <ul class="divide-y divide-line md:hidden">
            <?php foreach (array_slice($virals['recent'], 0, 12) as $video): ?>
                <li class="flex items-center gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm leading-snug font-semibold"><?= e($video['title']) ?><?= $video['is_short'] ? ' ' . badge('Short', 'info', 'ml-1 align-middle') : '' ?></p>
                        <p class="mt-0.5 text-xs text-muted"><?= e(($video['published_at'] ? fmt_date($video['published_at']) . ' · ' : '') . fmt_compact($video['views_7d']) . ' em 7 dias · ' . fmt_compact($video['views']) . ' no total') ?></p>
                    </div>
                    <?= badge(fmt_ratio($video['ratio']), $video['ratio'] >= $multiplier ? 'warn' : ($video['ratio'] >= 1 ? 'ok' : 'neutral'), 'shrink-0', $video['ratio'] >= $multiplier ? 'flame' : null) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="hidden overflow-x-auto md:block">
            <table class="table-base">
                <thead><tr><th>Vídeo</th><th>Publicado</th><th class="text-right">Views em 7 dias</th><th class="text-right">Total</th><th class="text-right">vs mediana</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($virals['recent'], 0, 12) as $video): ?>
                    <tr>
                        <td><span class="font-semibold"><?= e($video['title']) ?></span><?= $video['is_short'] ? ' ' . badge('Short', 'info', 'ml-2') : '' ?></td>
                        <td class="font-mono text-xs text-muted"><?= $video['published_at'] ? e(fmt_date($video['published_at'])) : '—' ?></td>
                        <td class="text-right tabular-nums"><?= e(fmt_number($video['views_7d'])) ?></td>
                        <td class="text-right text-muted tabular-nums"><?= e(fmt_compact($video['views'])) ?></td>
                        <td class="text-right">
                            <?= badge(fmt_ratio($video['ratio']), $video['ratio'] >= $multiplier ? 'warn' : ($video['ratio'] >= 1 ? 'ok' : 'neutral'), '', $video['ratio'] >= $multiplier ? 'flame' : null) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= empty_state('flame', 'Nenhum vídeo publicado recentemente') ?>
    <?php endif; ?>
    <p class="flex items-center gap-2 border-t border-line px-5 py-3 text-xs text-dim">
        <?= icon('info', 'size-3.5') ?>
        <?= e('Mediana atual: ' . fmt_compact($virals['medians']['longos']) . ' views para vídeos e ' . fmt_compact($virals['medians']['shorts']) . ' para Shorts. Os números do YouTube chegam com 2 a 3 dias de atraso.') ?>
    </p>
</section>
