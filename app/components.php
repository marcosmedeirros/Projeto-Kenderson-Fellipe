<?php

defined('CONTROLADORIA') || exit;

// Pedaços de interface reutilizados nas páginas. Textos são escapados aqui;
// parâmetros terminados em "Html" recebem HTML já pronto e seguro.

const TONE_BADGE = [
    'ok' => 'bg-accent/12 text-accent',
    'warn' => 'bg-warn/12 text-warn',
    'danger' => 'bg-danger/12 text-danger',
    'info' => 'bg-info/12 text-info',
    'neutral' => 'bg-panel-3 text-muted',
];

function cn(...$classes): string
{
    return implode(' ', array_filter($classes, static fn ($class) => is_string($class) && $class !== ''));
}

function badge(string $label, string $tone = 'neutral', string $class = '', ?string $iconName = null): string
{
    return '<span class="' . e(cn('badge', TONE_BADGE[$tone] ?? TONE_BADGE['neutral'], $class)) . '">'
        . ($iconName ? icon($iconName, 'size-3') : '') . e($label) . '</span>';
}

function brand_mark(string $class = 'size-8'): string
{
    return '<svg viewBox="0 0 32 32" aria-hidden="true" class="' . e($class) . '"><rect width="32" height="32" rx="8" fill="#151e27"/>'
        . '<rect x="6" y="11" width="7" height="10" rx="2" fill="#2db89b"/><rect x="15" y="11" width="5" height="10" rx="2" fill="#e9a23b"/>'
        . '<rect x="22" y="11" width="4" height="10" rx="2" fill="#2db89b" opacity=".5"/><rect x="9" y="6" width="2" height="20" rx="1" fill="#f0564a"/></svg>';
}

function page_header(string $title, ?string $eyebrow = null, string $descriptionHtml = '', string $actionsHtml = ''): string
{
    return '<header class="mb-7 flex flex-wrap items-end justify-between gap-4"><div class="min-w-0">'
        . ($eyebrow !== null ? '<p class="eyebrow mb-2">' . e($eyebrow) . '</p>' : '')
        . '<h1 class="display text-[34px] leading-none font-extrabold text-balance sm:text-[40px]">' . e($title) . '</h1>'
        . ($descriptionHtml !== '' ? '<p class="mt-2.5 max-w-2xl text-[15px] text-muted">' . $descriptionHtml . '</p>' : '')
        . '</div>' . ($actionsHtml !== '' ? '<div class="flex flex-wrap items-center gap-2">' . $actionsHtml . '</div>' : '') . '</header>';
}

function card_header(string $title, string $descriptionHtml = '', ?string $iconName = null, string $actionHtml = ''): string
{
    return '<div class="flex items-start justify-between gap-3 border-b border-line px-5 py-4"><div class="flex min-w-0 items-start gap-3">'
        . ($iconName ? '<span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-panel-2 text-muted">' . icon($iconName) . '</span>' : '')
        . '<div class="min-w-0"><h2 class="text-[15px] font-bold">' . e($title) . '</h2>'
        . ($descriptionHtml !== '' ? '<p class="mt-0.5 text-[13px] text-muted">' . $descriptionHtml . '</p>' : '')
        . '</div></div>' . ($actionHtml !== '' ? '<div class="shrink-0">' . $actionHtml . '</div>' : '') . '</div>';
}

/** $options: label, valueHtml, hintHtml, tone, icon, href */
function stat_card(array $options): string
{
    $tone = $options['tone'] ?? 'neutral';
    $color = ['warn' => 'text-warn', 'danger' => 'text-danger', 'info' => 'text-info'][$tone] ?? 'text-ink';
    $body = '<div class="flex items-center justify-between"><span class="eyebrow">' . e($options['label']) . '</span>'
        . (!empty($options['icon']) ? icon($options['icon'], 'size-4 ' . ($color === 'text-ink' ? 'text-dim' : $color)) : '') . '</div>'
        . '<div class="display mt-3 text-[38px] leading-none font-extrabold tabular-nums ' . $color . '">' . $options['valueHtml'] . '</div>'
        . (!empty($options['hintHtml']) ? '<div class="mt-2 text-[13px] text-muted">' . $options['hintHtml'] . '</div>' : '')
        . (in_array($tone, ['warn', 'danger'], true) ? '<span class="absolute inset-x-0 top-0 h-0.5 rounded-t-xl ' . ($tone === 'warn' ? 'bg-warn' : 'bg-danger') . '"></span>' : '');
    if (!empty($options['href'])) {
        return '<a href="' . e($options['href']) . '" class="card relative block p-5 transition-colors hover:border-line-2 hover:bg-panel-2/60">' . $body . '</a>';
    }
    return '<div class="card relative block p-5">' . $body . '</div>';
}

function empty_state(string $iconName, string $title, string $description = '', string $actionHtml = ''): string
{
    return '<div class="flex flex-col items-center px-6 py-10 text-center"><span class="grid size-11 place-items-center rounded-xl bg-panel-2 text-dim">'
        . icon($iconName, 'size-5') . '</span><p class="mt-3 font-semibold">' . e($title) . '</p>'
        . ($description !== '' ? '<p class="mt-1 max-w-sm text-sm text-muted">' . e($description) . '</p>' : '')
        . ($actionHtml !== '' ? '<div class="mt-4">' . $actionHtml . '</div>' : '') . '</div>';
}

function notice(string $tone, string $iconName, string $title, string $bodyHtml = '', string $actionHtml = '', string $class = ''): string
{
    $tones = [
        'warn' => 'border-warn/30 bg-warn/[0.07] text-warn',
        'danger' => 'border-danger/30 bg-danger/[0.07] text-danger',
        'info' => 'border-info/30 bg-info/[0.07] text-info',
        'ok' => 'border-accent/30 bg-accent/[0.07] text-accent',
    ];
    return '<div class="' . e(cn('flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border px-4 py-3.5', $tones[$tone] ?? $tones['info'], $class)) . '">'
        . icon($iconName, 'size-5 shrink-0') . '<div class="min-w-0 flex-1"><p class="font-semibold">' . e($title) . '</p>'
        . ($bodyHtml !== '' ? '<div class="mt-0.5 text-sm text-ink/75">' . $bodyHtml . '</div>' : '') . '</div>' . $actionHtml . '</div>';
}

/** @param array<int, array{0: string, 1: string}> $items rótulo e valor em HTML */
function key_value(array $items): string
{
    $html = '<dl class="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2.5 text-sm">';
    foreach ($items as [$label, $valueHtml]) {
        $html .= '<dt class="text-muted">' . e($label) . '</dt><dd class="min-w-0 break-words">' . $valueHtml . '</dd>';
    }
    return $html . '</dl>';
}

function form_open(string $action, string $class = '', string $extraAttributes = ''): string
{
    return '<form method="post" action="' . e($action) . '" class="' . e($class) . '"' . ($extraAttributes !== '' ? ' ' . $extraAttributes : '') . '>' . csrf_field();
}

function submit_button(string $labelHtml, string $class = 'btn btn-primary', ?string $pendingText = null, ?string $name = null, ?string $value = null): string
{
    return '<button type="submit" class="' . e($class) . '"'
        . ($pendingText !== null ? ' data-pending="' . e($pendingText) . '"' : '')
        . ($name !== null ? ' name="' . e($name) . '" value="' . e((string) $value) . '"' : '')
        . '>' . $labelHtml . '</button>';
}

/** Mostra *negrito* do WhatsApp com segurança. */
function whatsapp_html(string $text): string
{
    return (string) preg_replace('/\*([^*\n]+)\*/', '<strong class="font-bold">$1</strong>', e($text));
}

function flash_messages(array $items): string
{
    $html = '';
    foreach ($items as $item) {
        $ok = ($item['type'] ?? '') === 'ok';
        $html .= '<div class="mb-5 rounded-xl border px-4 py-3 ' . ($ok ? 'border-accent/30 bg-accent/[0.07]' : 'border-danger/30 bg-danger/[0.07]') . '" role="status">'
            . '<p class="flex items-start gap-2 text-sm ' . ($ok ? 'text-accent' : 'text-danger') . '">'
            . icon($ok ? 'check' : 'circle-alert', 'mt-0.5 size-4 shrink-0') . e($item['message']) . '</p>';
        if (!empty($item['secret'])) {
            $html .= '<div class="mt-2.5 flex items-center gap-2 rounded-lg border border-warn/30 bg-warn/[0.07] p-2 pl-3">'
                . '<code class="flex-1 font-mono text-sm break-all text-warn">' . e($item['secret']) . '</code>'
                . '<button type="button" class="btn btn-secondary btn-sm" data-copy="' . e($item['secret']) . '">' . icon('copy', 'size-3.5') . '<span>Copiar</span></button></div>';
        }
        $html .= '</div>';
    }
    return $html;
}

/* ---------------- Gráficos ---------------- */

/** Gráfico de área. Rótulos em HTML para não distorcer com o SVG esticado. */
function area_chart(array $points, string $formatter = 'fmt_compact', int $height = 170): string
{
    $points = array_values($points);
    $count = count($points);
    if ($count < 2) {
        return '<p class="py-10 text-center text-sm text-muted">Ainda não há dados suficientes para o gráfico.</p>';
    }
    $width = 1000;
    $chartHeight = 300;
    $peak = max(array_column($points, 'value'));
    $top = $peak > 0 ? $peak * 1.12 : 1;
    $line = '';
    $lastY = 0.0;
    foreach ($points as $index => $point) {
        $x = $index / ($count - 1) * $width;
        $lastY = $chartHeight - ($point['value'] / $top) * $chartHeight;
        $line .= ($index ? ' L' : 'M') . sprintf('%.1F,%.1F', $x, $lastY);
    }
    $grid = '';
    foreach ([0.25, 0.5, 0.75] as $fraction) {
        $grid .= sprintf('<line x1="0" x2="%d" y1="%.1F" y2="%.1F" stroke="#1f2a35" stroke-width="1" vector-effect="non-scaling-stroke"/>', $width, $chartHeight * $fraction, $chartHeight * $fraction);
    }
    return '<div><div class="relative" style="height:' . $height . 'px">'
        . '<svg viewBox="0 0 ' . $width . ' ' . $chartHeight . '" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible" aria-hidden="true">'
        . '<defs><linearGradient id="area-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#2db89b" stop-opacity="0.32"/><stop offset="100%" stop-color="#2db89b" stop-opacity="0"/></linearGradient></defs>'
        . $grid
        . '<line x1="0" x2="' . $width . '" y1="' . $chartHeight . '" y2="' . $chartHeight . '" stroke="#2c3a48" stroke-width="1" vector-effect="non-scaling-stroke"/>'
        . '<path d="' . $line . ' L' . $width . ',' . $chartHeight . ' L0,' . $chartHeight . ' Z" fill="url(#area-fill)"/>'
        . '<path d="' . $line . '" fill="none" stroke="#2db89b" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>'
        . '<span class="absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-accent ring-4 ring-accent/20" style="left:100%;top:' . sprintf('%.2F', $lastY / $chartHeight * 100) . '%"></span>'
        . '<span class="absolute top-0 left-0 rounded bg-panel/80 px-1 font-mono text-[11px] text-dim">pico ' . e($formatter($peak)) . '</span></div>'
        . '<div class="mt-2 flex justify-between font-mono text-[11px] text-dim"><span>' . e($points[0]['label']) . '</span><span>'
        . e($points[intdiv($count, 2)]['label']) . '</span><span>' . e($points[$count - 1]['label']) . '</span></div></div>';
}

/** $items: key, labelHtml, value, metaHtml, tone (accent|warn|info) */
function bar_list(array $items, string $formatter = 'fmt_compact'): string
{
    $max = max(1, $items ? max(array_column($items, 'value')) : 1);
    $colors = ['warn' => 'bg-warn', 'info' => 'bg-info', 'accent' => 'bg-accent'];
    $html = '<ul class="space-y-3.5">';
    foreach ($items as $item) {
        $html .= '<li><div class="flex items-baseline justify-between gap-3 text-sm"><span class="min-w-0 truncate">' . $item['labelHtml'] . '</span>'
            . '<span class="shrink-0 font-mono text-xs text-muted tabular-nums">' . e($formatter($item['value'])) . '</span></div>'
            . '<div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-panel-3"><div class="h-full rounded-full ' . ($colors[$item['tone'] ?? 'accent'] ?? 'bg-accent') . '" style="width:'
            . sprintf('%.2F', max(2, $item['value'] / $max * 100)) . '%"></div></div>'
            . (!empty($item['metaHtml']) ? '<div class="mt-1 text-xs text-dim">' . $item['metaHtml'] . '</div>' : '') . '</li>';
    }
    return $html . '</ul>';
}

/** Linha do tempo no estilo de um editor de vídeo: cada bloco é um vídeo agendado. */
function stock_timeline(array $videos, int $days, int $minimumDays): string
{
    $now = time();
    $span = $days * 86400;
    $dayWidth = 100 / $days;
    $pos = static fn (DateTimeInterface $date): float => ($date->getTimestamp() - $now) / $span * 100;
    $last = $videos ? end($videos)['publish_at'] : null;
    $lastPos = $last ? min(100, max(0, $pos($last) + $dayWidth)) : 0;
    $minPos = min(100, $minimumDays / $days * 100);
    $visible = array_filter($videos, static fn ($video) => $pos($video['publish_at']) <= 100);
    $tracks = [
        ['Vídeos', array_filter($visible, static fn ($video) => !$video['is_short'])],
        ['Shorts', array_filter($visible, static fn ($video) => $video['is_short'])],
    ];

    $ruler = '';
    for ($tick = 0; $tick <= $days; $tick += 5) {
        $align = $tick === 0 ? 'translate-x-0' : ($tick >= $days ? '-translate-x-full' : '-translate-x-1/2');
        $label = $tick === 0 ? 'hoje' : fmt_date(utc_now()->modify("+$tick days"));
        $ruler .= '<span class="absolute top-0.5 font-mono text-[11px] text-dim tabular-nums ' . $align . '" style="left:' . sprintf('%.3F', $tick / $days * 100) . '%">' . e($label) . '</span>';
    }

    $html = '<div><div class="overflow-x-auto pb-1"><div class="grid min-w-[560px] grid-cols-[64px_1fr] gap-x-3 gap-y-2">'
        . '<div class="col-start-2 row-start-1"><div class="relative h-7 border-b border-line-2" style="background-image:repeating-linear-gradient(90deg,#2c3a48 0 1px,transparent 1px '
        . sprintf('%.3F', $dayWidth) . '%);background-size:100% 5px;background-position:left bottom;background-repeat:no-repeat">' . $ruler . '</div></div>';

    foreach ($tracks as $index => [$label, $items]) {
        $row = $index + 2;
        $html .= '<div class="flex items-center font-mono text-[11px] font-semibold tracking-[0.06em] text-dim uppercase" style="grid-row:' . $row . '">' . e($label) . '</div>'
            . '<div class="relative col-start-2 h-11 overflow-hidden rounded-md bg-panel-2" style="grid-row:' . $row . '">'
            . '<div class="absolute inset-y-0 right-0" style="left:' . sprintf('%.3F', $lastPos) . '%;background-image:repeating-linear-gradient(135deg,rgba(233,162,59,.16) 0 6px,transparent 6px 12px)"></div>';
        foreach ($items as $video) {
            $missing = $video['thumbnail_status'] !== 'ok';
            $title = fmt_weekday($video['publish_at']) . ' ' . fmt_date($video['publish_at']) . ' ' . fmt_time($video['publish_at']) . ' · ' . $video['title'] . ($missing ? ' · sem capa' : '');
            $html .= '<div title="' . e($title) . '" aria-label="' . e($title) . '" class="absolute inset-y-1.5 grid place-items-center rounded-[5px] font-mono text-[10px] font-semibold shadow-[inset_0_1px_0_rgba(255,255,255,.22)] '
                . ($missing ? 'bg-warn text-[#231603]' : 'bg-accent text-accent-ink') . '" style="left:' . sprintf('%.3F', max(0, $pos($video['publish_at']))) . '%;width:max('
                . sprintf('%.3F', $dayWidth) . '%,14px)">' . e(substr(fmt_date($video['publish_at']), 0, 2)) . '</div>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="pointer-events-none relative col-start-2 row-start-1 row-end-4" aria-hidden="true">'
        . '<div class="absolute inset-y-0 left-0 w-0.5 bg-danger"></div>'
        . '<div class="absolute top-7 bottom-0 border-l-2 border-dashed border-info/70" style="left:' . sprintf('%.3F', $minPos) . '%"></div>'
        . ($last ? '<div class="absolute top-7 bottom-0 border-l-2 border-dashed border-warn" style="left:' . sprintf('%.3F', $lastPos) . '%"></div>' : '')
        . '</div></div></div>';

    $html .= '<div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-muted">'
        . '<span class="flex items-center gap-2"><i class="size-2.5 rounded-sm bg-accent"></i>Com capa</span>'
        . '<span class="flex items-center gap-2"><i class="size-2.5 rounded-sm bg-warn"></i>Sem capa</span>'
        . '<span class="flex items-center gap-2"><i class="h-3 w-0.5 bg-danger"></i>Hoje</span>'
        . '<span class="flex items-center gap-2"><i class="h-3 border-l-2 border-dashed border-info/70"></i>Mínimo (' . $minimumDays . ' dias)</span>'
        . ($last ? '<span class="flex items-center gap-2"><i class="h-3 border-l-2 border-dashed border-warn"></i>Fim do estoque (' . e(fmt_date($last)) . ')</span>' : '')
        . '</div></div>';

    return $html;
}
