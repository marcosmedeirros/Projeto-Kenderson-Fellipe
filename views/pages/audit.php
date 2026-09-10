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

$pager = '<div class="flex items-center gap-1">'
    . ($page > 1 ? '<a href="/auditoria?pagina=' . ($page - 1) . '" class="btn btn-ghost btn-sm px-2" aria-label="Página anterior">' . icon('chevron-left') . '</a>' : '')
    . '<span class="font-mono text-xs text-muted">' . $page . ' / ' . $pages . '</span>'
    . ($page < $pages ? '<a href="/auditoria?pagina=' . ($page + 1) . '" class="btn btn-ghost btn-sm px-2" aria-label="Próxima página">' . icon('chevron-right') . '</a>' : '')
    . '</div>';
?>
<?= page_header('Auditoria', 'Administração', 'Registro de acessos e alterações feitas no painel. Senhas e chaves nunca são gravadas aqui.') ?>

<section class="card">
    <?= card_header(plural($total, 'registro', 'registros'), '', 'scroll-text', $pager) ?>
    <?php if ($rows): ?>
        <div class="overflow-x-auto">
            <table class="table-base min-w-[760px]">
                <thead><tr><th>Quando</th><th>Quem</th><th>O que aconteceu</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): $alert = str_contains($row['action'], 'falha') || str_contains($row['action'], 'bloqueado'); $summary = $summarize($row['detail']); ?>
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap text-muted"><?= e(fmt_full_date(from_db($row['created_at']))) ?></td>
                        <td class="text-sm whitespace-nowrap"><?= $row['user_name'] !== null ? e($row['user_name']) : '<span class="text-dim">—</span>' ?></td>
                        <td>
                            <div class="flex items-center gap-2">
                                <?= $alert ? badge('Atenção', 'danger') : '' ?>
                                <span class="text-sm font-semibold"><?= e(AUDIT_LABELS[$row['action']] ?? $row['action']) ?></span>
                            </div>
                            <?php if ($summary !== ''): ?><p class="mt-0.5 max-w-xl truncate text-xs text-dim"><?= e($summary) ?></p><?php endif; ?>
                        </td>
                        <td class="font-mono text-xs text-muted"><?= e($row['ip'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= empty_state('scroll-text', 'Nenhum registro ainda') ?>
    <?php endif; ?>
</section>
