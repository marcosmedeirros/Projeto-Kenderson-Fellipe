<?php
defined('CONTROLADORIA') || exit;

$summarize = static function (?string $detail): string {
    $data = $detail ? json_decode($detail, true) : null;
    if (!is_array($data)) {
        return '';
    }
    $parts = [];
    foreach ($data as $key => $value) {
        if (is_bool($value)) {
            $text = $value ? 'sim' : 'não';
        } elseif (is_scalar($value) || $value === null) {
            $text = (string) $value;
        } else {
            $text = (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $parts[] = $key . ': ' . $text;
    }
    return implode(' · ', $parts);
};

$entries = array_map(static fn (array $row) => [
    'when' => fmt_full_date(from_db($row['created_at'])),
    'who' => $row['user_name'],
    'label' => AUDIT_LABELS[$row['action']] ?? $row['action'],
    'alert' => str_contains($row['action'], 'falha') || str_contains($row['action'], 'bloque') || str_contains($row['action'], 'expirada'),
    'summary' => $summarize($row['detail']),
    'ip' => $row['ip'] ?? '—',
], $rows);

$pager = '<div class="flex items-center gap-1">'
    . ($page > 1 ? '<a href="/auditoria?pagina=' . ($page - 1) . '" class="btn btn-ghost btn-sm px-2" aria-label="Página anterior">' . icon('chevron-left') . '</a>' : '')
    . '<span class="font-mono text-xs whitespace-nowrap text-muted">' . $page . ' / ' . $pages . '</span>'
    . ($page < $pages ? '<a href="/auditoria?pagina=' . ($page + 1) . '" class="btn btn-ghost btn-sm px-2" aria-label="Próxima página">' . icon('chevron-right') . '</a>' : '')
    . '</div>';
?>
<?= page_header('Auditoria', 'Administração', 'Registro de acessos e alterações feitas no painel. Senhas e chaves nunca são gravadas aqui.') ?>

<section class="card">
    <?= card_header(plural($total, 'registro', 'registros'), 'Guardados por ' . AUDIT_RETENTION_DAYS . ' dias', 'scroll-text', $pager) ?>
    <?php if ($entries): ?>
        <ul class="divide-y divide-line md:hidden">
            <?php foreach ($entries as $entry): ?>
                <li class="px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-semibold"><?= e($entry['label']) ?></p>
                        <?= $entry['alert'] ? badge('Atenção', 'danger', 'shrink-0') : '' ?>
                    </div>
                    <?php if ($entry['summary'] !== ''): ?><p class="mt-0.5 text-xs break-words text-dim"><?= e($entry['summary']) ?></p><?php endif; ?>
                    <p class="mt-1 font-mono text-[11px] break-all text-muted"><?= e($entry['when'] . ' · ' . ($entry['who'] ?? '—') . ' · ' . $entry['ip']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="hidden overflow-x-auto md:block">
            <table class="table-base">
                <thead><tr><th>Quando</th><th>Quem</th><th>O que aconteceu</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap text-muted"><?= e($entry['when']) ?></td>
                        <td class="text-sm whitespace-nowrap"><?= $entry['who'] !== null ? e($entry['who']) : '<span class="text-dim">—</span>' ?></td>
                        <td>
                            <div class="flex items-center gap-2">
                                <?= $entry['alert'] ? badge('Atenção', 'danger') : '' ?>
                                <span class="text-sm font-semibold"><?= e($entry['label']) ?></span>
                            </div>
                            <?php if ($entry['summary'] !== ''): ?><p class="mt-0.5 max-w-xl truncate text-xs text-dim" title="<?= e($entry['summary']) ?>"><?= e($entry['summary']) ?></p><?php endif; ?>
                        </td>
                        <td class="font-mono text-xs text-muted"><?= e($entry['ip']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= empty_state('scroll-text', 'Nenhum registro ainda') ?>
    <?php endif; ?>
</section>
