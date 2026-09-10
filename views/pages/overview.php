<?php
defined('CONTROLADORIA') || exit;

$lowStock = $stock['days_covered'] < $alerts['estoqueMinimoDias'];
$views30 = array_sum(array_column($series, 'views'));
$hours30 = (int) round(array_sum(array_column($series, 'watch_minutes')) / 60);
$subscribers30 = array_sum(array_column($series, 'subscribers_gained'));
$chartPoints = array_map(static fn ($day) => ['label' => day_label($day['day']), 'value' => $day['views']], $series);

if ($mtd['change'] === null) {
    $viewsHint = 'mês em andamento';
} else {
    $up = $mtd['change'] >= 0;
    $viewsHint = '<span class="' . ($up ? 'text-accent' : 'text-danger') . '">' . ($up ? '▲' : '▼') . ' ' . e(fmt_percent(abs($mtd['change']))) . '</span> vs mesmo período do mês passado';
}
?>
<?php if ($accessDenied): ?>
    <?= notice('danger', 'shield-alert', 'Você não tem permissão para acessar aquela página.', 'Se precisar desse acesso, fale com o administrador do painel.', '', 'mb-6') ?>
<?php endif; ?>

<?= page_header('Olá, ' . explode(' ', trim($user['name']))[0], fmt_long_day(utc_now()), 'A situação do canal agora: estoque de vídeos, capas pendentes e o que está performando.') ?>

<?php if ($lowStock): ?>
    <?= notice(
        'warn',
        'triangle-alert',
        $stock['total'] ? 'O estoque cobre só até ' . fmt_date($stock['last_date']) . ' (' . plural($stock['days_covered'], 'dia', 'dias') . '). Hora de gravar.' : 'Nenhum vídeo programado. Hora de gravar.',
        'O mínimo combinado é de ' . (int) $alerts['estoqueMinimoDias'] . ' dias de vídeos agendados.',
        '<a href="/programados" class="btn btn-secondary btn-sm">Ver programados ' . icon('arrow-right', 'size-3.5') . '</a>',
        'mb-6'
    ) ?>
<?php endif; ?>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?= stat_card([
        'label' => 'Programados', 'icon' => 'calendar-clock', 'href' => '/programados',
        'valueHtml' => (string) $stock['total'],
        'hintHtml' => e($stock['shorts'] ? ($stock['total'] - $stock['shorts']) . ' vídeos · ' . $stock['shorts'] . ' Shorts' : 'vídeos agendados'),
    ]) ?>
    <?= stat_card([
        'label' => 'Estoque até', 'icon' => 'calendar-clock', 'href' => '/programados', 'tone' => $lowStock ? 'warn' : 'neutral',
        'valueHtml' => $stock['last_date'] ? e(fmt_date($stock['last_date'])) : '—',
        'hintHtml' => e($stock['last_date'] ? $stock['days_covered'] . ' dias · mínimo ' . $alerts['estoqueMinimoDias'] : 'sem vídeos agendados'),
    ]) ?>
    <?= stat_card([
        'label' => 'Sem capa', 'icon' => 'image-off', 'href' => '/capas', 'tone' => $stock['missing_thumbs'] ? 'warn' : 'neutral',
        'valueHtml' => (string) $stock['missing_thumbs'],
        'hintHtml' => e($stock['next_missing_thumb'] ? 'a próxima publica ' . fmt_in_days(days_until($stock['next_missing_thumb']['publish_at'])) : 'todas as capas prontas'),
    ]) ?>
    <?= stat_card(['label' => 'Views no mês', 'icon' => 'eye', 'href' => '/desempenho', 'valueHtml' => e(fmt_compact($mtd['views'])), 'hintHtml' => $viewsHint]) ?>
</div>

<section class="card mt-6">
    <?= card_header('Estoque dos próximos 30 dias', 'Cada bloco é um vídeo agendado. Passe o mouse para ver o título.', 'calendar-clock', '<a href="/programados" class="btn btn-ghost btn-sm">Detalhes ' . icon('arrow-right', 'size-3.5') . '</a>') ?>
    <div class="p-5"><?= stock_timeline($scheduled, 30, (int) $alerts['estoqueMinimoDias']) ?></div>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_1fr]">
    <section class="card">
        <?= card_header('Próximos lançamentos', '', 'calendar-clock') ?>
        <?php if ($scheduled): ?>
            <ul class="divide-y divide-line">
                <?php foreach (array_slice($scheduled, 0, 6) as $video): ?>
                    <li class="flex items-center gap-4 px-5 py-3">
                        <div class="w-14 shrink-0 text-center">
                            <div class="font-mono text-[10px] text-dim uppercase"><?= e(fmt_weekday($video['publish_at'])) ?></div>
                            <div class="display text-xl leading-tight font-extrabold tabular-nums"><?= e(fmt_date($video['publish_at'])) ?></div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold"><?= e($video['title']) ?></p>
                            <p class="mt-0.5 text-xs text-muted"><?= e(fmt_time($video['publish_at']) . ' · publica ' . fmt_in_days(days_until($video['publish_at'])) . ($video['is_short'] ? ' · Short' : '')) ?></p>
                        </div>
                        <?= $video['thumbnail_status'] === 'ok' ? badge('Capa ok', 'ok') : badge('Sem capa', 'warn') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= empty_state('calendar-clock', 'Nenhum vídeo agendado', 'Quando houver vídeos programados no YouTube, eles aparecem aqui.') ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <?= card_header('Views · últimos 30 dias', '', 'eye') ?>
        <div class="p-5">
            <?= area_chart($chartPoints) ?>
            <div class="mt-5 grid grid-cols-3 gap-3 border-t border-line pt-4">
                <div><p class="eyebrow">Views</p><p class="display mt-1 text-2xl font-extrabold"><?= e(fmt_compact($views30)) ?></p></div>
                <div><p class="eyebrow">Horas</p><p class="display mt-1 text-2xl font-extrabold"><?= e(fmt_compact($hours30)) ?></p></div>
                <div><p class="eyebrow flex items-center gap-1"><?= icon('user-plus', 'size-3') ?>Inscritos</p><p class="display mt-1 text-2xl font-extrabold">+<?= e(fmt_compact($subscribers30)) ?></p></div>
            </div>
        </div>
    </section>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
    <section class="card">
        <?= card_header('Em alta', 'Acima de ' . e(fmt_decimal((float) $alerts['viralMultiplicador'])) . 'x a média em 7 dias', 'flame', '<a href="/desempenho" class="btn btn-ghost btn-sm">Ver tudo</a>') ?>
        <?php if ($virals['items']): ?>
            <ul class="divide-y divide-line">
                <?php foreach (array_slice($virals['items'], 0, 4) as $video): ?>
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="display w-14 shrink-0 text-xl font-extrabold text-warn tabular-nums"><?= e(fmt_ratio($video['ratio'])) ?></span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold"><?= e($video['title']) ?></p>
                            <p class="text-xs text-muted"><?= e(fmt_compact($video['views_7d']) . ' views em 7 dias' . ($video['is_short'] ? ' · Short' : '')) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= empty_state('flame', 'Nenhum vídeo acima da média', 'Nos últimos 45 dias nenhum vídeo passou do multiplicador configurado.') ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <?= card_header('Ideias aprovadas', '', 'lightbulb', '<a href="/ideias?status=aprovada" class="btn btn-ghost btn-sm">Ver ideias</a>') ?>
        <?php if ($approved): ?>
            <ul class="divide-y divide-line">
                <?php foreach (array_slice($approved, 0, 4) as $idea): ?>
                    <li class="px-5 py-3">
                        <p class="text-sm font-semibold"><?= e($idea['title']) ?></p>
                        <p class="mt-0.5 line-clamp-2 text-xs text-muted"><?= e($idea['rationale']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= empty_state('lightbulb', 'Nenhuma ideia aprovada', 'Aprove ideias na página de pautas para elas aparecerem aqui.') ?>
        <?php endif; ?>
    </section>

    <section class="card lg:col-span-2 xl:col-span-1">
        <?= card_header('Bot no WhatsApp', 'Atividade recente', 'bot', '<a href="/bot" class="btn btn-ghost btn-sm">Abrir</a>') ?>
        <?php if ($messages): ?>
            <ul class="divide-y divide-line">
                <?php foreach ($messages as $message): ?>
                    <li class="flex items-start gap-3 px-5 py-3">
                        <?php if ($message['direction'] === 'entrada'): ?>
                            <?= badge($message['sender'] ?? 'Grupo', 'info', 'mt-0.5') ?>
                        <?php elseif ($message['origin'] === 'automacao'): ?>
                            <?= badge('Alerta', 'warn', 'mt-0.5') ?>
                        <?php else: ?>
                            <?= badge('Bot', 'ok', 'mt-0.5') ?>
                        <?php endif; ?>
                        <p class="min-w-0 flex-1 truncate text-sm text-muted"><?= e(str_replace('*', '', strtok($message['text'], "\n"))) ?></p>
                        <span class="shrink-0 font-mono text-[11px] text-dim"><?= e(fmt_ago($message['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= empty_state('bot', 'Sem atividade ainda', 'Os comandos enviados no grupo aparecem aqui.') ?>
        <?php endif; ?>
    </section>
</div>
