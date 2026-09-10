<?php
defined('CONTROLADORIA') || exit;

$total = array_sum($counts);
$filterUrl = static function (string $filter) use ($search): string {
    $query = array_filter(['status' => $filter, 'q' => $search], static fn ($value) => $value !== '');
    return '/videos' . ($query ? '?' . http_build_query($query) : '');
};
$actions = $canManage ? '<a href="/videos/novo" class="btn btn-primary">' . icon('plus') . 'Novo vídeo</a>' : '';
$deleteForm = static fn (array $video): string => form_open('/videos/excluir', '', 'data-confirm="Excluir este vídeo? As métricas dele também serão apagadas."')
    . '<input type="hidden" name="id" value="' . e($video['id']) . '">'
    . submit_button(icon('trash-2', 'size-3.5') . '<span class="sr-only md:not-sr-only">Excluir</span>', 'btn btn-ghost btn-sm text-danger') . '</form>';
?>
<?= page_header('Vídeos', 'Canal', 'Todos os vídeos do canal: cadastrados aqui ou vindos do YouTube.', $actions) ?>

<section class="card">
    <div class="flex flex-col gap-3 border-b border-line px-4 py-3 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
        <nav class="-mx-1 flex gap-1 overflow-x-auto px-1" aria-label="Filtrar por status">
            <a href="<?= e($filterUrl('')) ?>" class="btn btn-sm shrink-0 <?= $status === '' ? 'bg-panel-3 text-ink' : 'btn-ghost' ?>">
                Todos <span class="font-mono text-[11px] text-dim"><?= $total ?></span>
            </a>
            <?php foreach (VIDEO_STATUSES as $key => $label): ?>
                <a href="<?= e($filterUrl($key)) ?>" class="btn btn-sm shrink-0 <?= $status === $key ? 'bg-panel-3 text-ink' : 'btn-ghost' ?>" <?= $status === $key ? 'aria-current="page"' : '' ?>>
                    <?= e($label) ?> <span class="font-mono text-[11px] text-dim"><?= (int) $counts[$key] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <form method="get" action="/videos" class="flex gap-2">
            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar pelo título" class="input h-9 lg:w-64" aria-label="Buscar vídeos">
            <button type="submit" class="btn btn-secondary h-9 px-3" aria-label="Buscar"><?= icon('search') ?></button>
        </form>
    </div>

    <?php if ($videos): ?>
        <ul class="divide-y divide-line md:hidden">
            <?php foreach ($videos as $video): $date = video_main_date($video); ?>
                <li class="flex gap-3 px-4 py-3.5">
                    <?= video_thumb_html($video, 'w-24') ?>
                    <div class="min-w-0 flex-1">
                        <p class="leading-snug font-semibold"><?= e($video['title']) ?></p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                            <?= badge(VIDEO_STATUSES[$video['status']] ?? $video['status'], VIDEO_STATUS_TONES[$video['status']] ?? 'neutral') ?>
                            <?= $video['is_short'] ? badge('Short', 'info') : '' ?>
                            <?php if ($date): ?><span><?= e(fmt_weekday($date) . ' ' . fmt_date($date) . ' · ' . fmt_time($date)) ?></span><?php endif; ?>
                        </div>
                        <?php if ($canManage): ?>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <a href="/videos/editar?id=<?= e(rawurlencode($video['id'])) ?>" class="btn btn-secondary btn-sm"><?= icon('pencil', 'size-3.5') ?>Editar</a>
                                <?= $canDelete ? $deleteForm($video) : '' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="hidden overflow-x-auto md:block">
            <table class="table-base">
                <thead><tr><th class="w-24">Capa</th><th>Vídeo</th><th>Status</th><th>Publicação</th><th class="text-right">Ações</th></tr></thead>
                <tbody>
                <?php foreach ($videos as $video): $date = video_main_date($video); ?>
                    <tr class="hover:bg-panel-2/50">
                        <td><?= video_thumb_html($video, 'w-20') ?></td>
                        <td>
                            <p class="font-semibold"><?= e($video['title']) ?></p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                <?= $video['is_short'] ? badge('Short', 'info') : '' ?>
                                <?= badge(VIDEO_SOURCES[$video['source']] ?? $video['source']) ?>
                                <?= $video['thumbnail_status'] === 'pendente' ? badge('Sem capa', 'warn') : '' ?>
                            </div>
                        </td>
                        <td><?= badge(VIDEO_STATUSES[$video['status']] ?? $video['status'], VIDEO_STATUS_TONES[$video['status']] ?? 'neutral') ?></td>
                        <td class="whitespace-nowrap">
                            <?php if ($date): ?>
                                <span class="font-semibold tabular-nums"><?= e(fmt_weekday($date) . ' ' . fmt_date($date)) ?></span>
                                <span class="ml-1 font-mono text-xs text-muted"><?= e(fmt_time($date)) ?></span>
                            <?php else: ?>
                                <span class="text-dim">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <?php if ($video['youtube_id']): ?>
                                    <a href="https://studio.youtube.com/video/<?= e(rawurlencode($video['youtube_id'])) ?>/edit" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm" aria-label="Abrir no YouTube Studio"><?= icon('external-link', 'size-3.5') ?></a>
                                <?php endif; ?>
                                <?php if ($canManage): ?>
                                    <a href="/videos/editar?id=<?= e(rawurlencode($video['id'])) ?>" class="btn btn-secondary btn-sm"><?= icon('pencil', 'size-3.5') ?>Editar</a>
                                    <?= $canDelete ? $deleteForm($video) : '' ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= empty_state('clapperboard', $search !== '' ? 'Nenhum vídeo encontrado' : 'Nenhum vídeo cadastrado', $search !== '' ? 'Tente outro termo de busca.' : 'Cadastre os vídeos planejados, agendados e publicados.', $canManage && $search === '' ? '<a href="/videos/novo" class="btn btn-primary">' . icon('plus') . 'Novo vídeo</a>' : '') ?>
    <?php endif; ?>
</section>
