<?php
defined('CONTROLADORIA') || exit;

$currentUser = current_session()['user'];
$menuStock = summarize_stock(scheduled_videos());
$menuAlerts = get_setting('alertas');
$currentPath = request_path();
$flashItems = take_flash();
$navGroups = [
    'Canal' => [
        ['/', 'Visão geral', 'layout-dashboard', null],
        ['/programados', 'Programados', 'calendar-clock', $menuStock['days_covered'] < $menuAlerts['estoqueMinimoDias'] ? 'dot' : null],
        ['/capas', 'Capas', 'image-off', $menuStock['missing_thumbs'] ?: null],
        ['/desempenho', 'Desempenho', 'trending-up', null],
        ['/ideias', 'Ideias', 'lightbulb', null],
    ],
    'Automação' => [
        ['/bot', 'Bot do WhatsApp', 'bot', null],
        ['/alertas', 'Alertas', 'bell-ring', null],
    ],
    'Administração' => array_values(array_filter([
        can($currentUser['role'], 'integracoes') ? ['/integracoes', 'Integrações', 'plug', null] : null,
        can($currentUser['role'], 'usuarios') ? ['/usuarios', 'Usuários', 'users', null] : null,
        can($currentUser['role'], 'auditoria') ? ['/auditoria', 'Auditoria', 'scroll-text', null] : null,
    ])),
];
$isActive = static fn (string $href): bool => $href === '/' ? $currentPath === '/' : ($currentPath === $href || str_starts_with($currentPath, $href . '/'));
$nameParts = array_slice(preg_split('/\s+/', trim($currentUser['name'])) ?: ['?'], 0, 2);
$initials = mb_strtoupper(implode('', array_map(static fn ($part) => mb_substr($part, 0, 1), $nameParts)));
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> · Controladoria</title>
    <link rel="icon" href="<?= e(asset('icon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <?php foreach ($scripts as $script): ?>
        <script src="<?= e(asset($script)) ?>" defer></script>
    <?php endforeach; ?>
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body class="min-h-screen">
<div class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-bg/90 px-4 backdrop-blur lg:hidden">
    <a href="/" class="flex items-center gap-2.5"><?= brand_mark('size-7') ?><span class="display text-lg font-extrabold">Controladoria</span></a>
    <button type="button" class="btn btn-ghost px-2" data-sidebar-open aria-label="Abrir menu"><?= icon('menu', 'size-5') ?></button>
</div>

<div class="fixed inset-0 z-40 hidden bg-black/60 lg:hidden" data-sidebar-overlay></div>

<aside class="fixed inset-y-0 left-0 z-50 flex w-[264px] -translate-x-full flex-col border-r border-line bg-panel transition-transform lg:translate-x-0" data-sidebar>
    <div class="flex h-16 items-center justify-between px-5">
        <a href="/" class="flex items-center gap-3">
            <?= brand_mark('size-8') ?>
            <span class="leading-tight">
                <span class="display block text-[19px] font-extrabold">Controladoria</span>
                <span class="block font-mono text-[10px] tracking-[0.1em] text-dim uppercase">Canal Luiz Jordão</span>
            </span>
        </a>
        <button type="button" class="btn btn-ghost px-2 lg:hidden" data-sidebar-close aria-label="Fechar menu"><?= icon('x', 'size-5') ?></button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        <?php foreach ($navGroups as $groupTitle => $items): ?>
            <?php if (!$items) continue; ?>
            <div>
                <p class="eyebrow mb-2 px-3 text-dim"><?= e($groupTitle) ?></p>
                <ul class="space-y-0.5">
                    <?php foreach ($items as [$href, $label, $iconName, $marker]): $active = $isActive($href); ?>
                        <li>
                            <a href="<?= e($href) ?>" <?= $active ? 'aria-current="page"' : '' ?>
                               class="group relative flex h-9 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition-colors <?= $active ? 'bg-panel-3 text-ink' : 'text-muted hover:bg-panel-2 hover:text-ink' ?>">
                                <?php if ($active): ?><span class="absolute top-2 bottom-2 left-0 w-0.5 rounded-full bg-accent"></span><?php endif; ?>
                                <?= icon($iconName, 'size-4 ' . ($active ? 'text-accent' : 'text-dim group-hover:text-muted')) ?>
                                <span class="flex-1"><?= e($label) ?></span>
                                <?php if ($marker === 'dot'): ?>
                                    <span class="size-2 rounded-full bg-warn" title="Estoque abaixo do mínimo"></span>
                                <?php elseif ($marker): ?>
                                    <span class="badge bg-warn/15 text-warn"><?= (int) $marker ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="border-t border-line p-3">
        <a href="/conta" class="flex items-center gap-3 rounded-lg px-2.5 py-2 transition-colors hover:bg-panel-2 <?= $isActive('/conta') ? 'bg-panel-3' : '' ?>">
            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-accent/15 font-display text-sm font-bold text-accent"><?= e($initials) ?></span>
            <span class="min-w-0 flex-1 leading-tight">
                <span class="block truncate text-sm font-semibold"><?= e($currentUser['name']) ?></span>
                <span class="block truncate text-xs text-dim"><?= e(ROLE_LABELS[$currentUser['role']] ?? $currentUser['role']) ?></span>
            </span>
            <?= icon('user-round', 'size-4 text-dim') ?>
        </a>
        <?= form_open('/sair', 'mt-1') ?>
            <button type="submit" class="btn btn-ghost h-9 w-full justify-start px-2.5 font-semibold"><?= icon('log-out') ?>Sair</button>
        </form>
    </div>
</aside>

<div class="lg:pl-[264px]">
    <?php if (!youtube_connected()): ?>
        <div class="flex items-center gap-2.5 border-b border-warn/20 bg-warn/[0.06] px-4 py-2 text-[13px] text-warn sm:px-8">
            <?= icon('flask-conical', 'size-4 shrink-0') ?>
            <span><strong class="font-semibold">Modo demonstração:</strong> os vídeos e números são fictícios até o YouTube ser conectado.</span>
            <?php if (can($currentUser['role'], 'integracoes')): ?>
                <a href="/integracoes" class="ml-auto hidden font-semibold underline-offset-4 hover:underline sm:inline">Ver integrações</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <main class="mx-auto w-full max-w-[1280px] px-4 pt-7 pb-16 sm:px-8 sm:pt-9">
        <?= flash_messages($flashItems) ?>
        <?= $content ?>
    </main>
</div>
</body>
</html>
