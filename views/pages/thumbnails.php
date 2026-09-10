<?php
defined('CONTROLADORIA') || exit;

$toneTitle = ['danger' => 'text-danger', 'warn' => 'text-warn', 'neutral' => 'text-ink'];
$row = static function (array $video, string $actionHtml) use ($canOperate): string {
    $days = days_until($video['publish_at']);
    $source = $video['thumbnail_source'] === 'manual' && $video['thumbnail_updated_at']
        ? 'marcado manualmente ' . fmt_ago($video['thumbnail_updated_at'])
        : 'verificado automaticamente';
    return '<li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3.5">'
        . '<div class="w-16 shrink-0"><div class="font-mono text-[10px] text-dim uppercase">' . e(fmt_weekday($video['publish_at']) . ' ' . fmt_time($video['publish_at'])) . '</div>'
        . '<div class="display text-xl leading-tight font-extrabold tabular-nums">' . e(fmt_date($video['publish_at'])) . '</div></div>'
        . '<div class="min-w-0 flex-1"><p class="font-semibold">' . e($video['title']) . ($video['is_short'] ? ' ' . badge('Short', 'info', 'ml-1 align-middle') : '') . '</p>'
        . '<p class="mt-0.5 text-xs text-muted">' . e('Publica ' . fmt_in_days($days) . ' · ' . $source) . '</p></div>'
        . ($canOperate ? $actionHtml : '') . '</li>';
};
$statusForm = static fn (array $video, string $status, string $labelHtml, string $class): string =>
    form_open('/capas/status') . '<input type="hidden" name="videoId" value="' . e($video['id']) . '"><input type="hidden" name="status" value="' . $status . '">'
    . submit_button($labelHtml, $class, 'Salvando…') . '</form>';
?>
<?= page_header('Capas', 'Canal', e($pendingCount
    ? $pendingCount . ($pendingCount === 1 ? ' vídeo programado está sem thumbnail.' : ' vídeos programados estão sem thumbnail.')
    : 'Todos os vídeos programados já têm capa.')) ?>

<div class="grid gap-6 xl:grid-cols-[1fr_320px]">
    <div class="space-y-6">
        <?php if ($groups): ?>
            <?php foreach ($groups as $group): ?>
                <section class="card">
                    <div class="flex items-center justify-between border-b border-line px-5 py-3">
                        <h2 class="text-sm font-bold <?= $toneTitle[$group['tone']] ?>"><?= e($group['title']) ?></h2>
                        <?= badge((string) count($group['items']), $group['tone']) ?>
                    </div>
                    <ul class="divide-y divide-line">
                        <?php foreach ($group['items'] as $video): ?>
                            <?= $row($video, $statusForm($video, 'ok', icon('circle-check') . ' Marcar capa como feita', 'btn btn-primary btn-sm')) ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        <?php else: ?>
            <section class="card"><?= empty_state('circle-check', 'Nenhuma capa pendente', 'Quando um vídeo agendado estiver sem thumbnail, ele aparece aqui.') ?></section>
        <?php endif; ?>

        <?php if ($done): ?>
            <section class="card">
                <details>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-3 text-sm font-bold">
                        Com capa pronta <?= badge((string) count($done), 'ok') ?>
                    </summary>
                    <ul class="divide-y divide-line border-t border-line">
                        <?php foreach ($done as $video): ?>
                            <?= $row($video, $video['thumbnail_source'] === 'manual'
                                ? $statusForm($video, 'pendente', icon('undo-2') . ' Desfazer', 'btn btn-ghost btn-sm')
                                : badge('Capa ok', 'ok')) ?>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </section>
        <?php endif; ?>
    </div>

    <section class="card h-fit">
        <?= card_header('Como a capa é verificada', '', 'info') ?>
        <div class="space-y-3 p-5 text-sm text-muted">
            <p>O YouTube não informa diretamente se a thumbnail é personalizada. Ao sincronizar, o sistema compara a capa atual com os quadros gerados automaticamente pelo YouTube.</p>
            <p>Se a verificação não for possível, quem faz a capa pode marcar como feita aqui. Tudo fica registrado na auditoria.</p>
            <div class="flex items-center gap-2 rounded-lg bg-panel-2 px-3 py-2 text-xs"><?= icon('image-off', 'size-4 text-warn') ?>No grupo: <code class="font-mono text-ink">/semcapa</code></div>
        </div>
    </section>
</div>
