<?php
defined('CONTROLADORIA') || exit;

$total = array_sum($counts);
?>
<?= page_header('Capas', 'Canal', 'Biblioteca das capas do canal: envie as opções, aprove e escolha a capa de cada vídeo.') ?>
<?= capas_tabs('biblioteca') ?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
    <div class="min-w-0 xl:order-1">
        <nav class="-mx-1 mb-4 flex gap-1 overflow-x-auto px-1" aria-label="Filtrar capas">
            <a href="/capas/biblioteca" class="btn btn-sm shrink-0 <?= $status === '' ? 'bg-panel-3 text-ink' : 'btn-ghost' ?>">Todas <span class="font-mono text-[11px] text-dim"><?= $total ?></span></a>
            <?php foreach (THUMBNAIL_STATUSES as $key => $label): ?>
                <a href="/capas/biblioteca?status=<?= e($key) ?>" class="btn btn-sm shrink-0 <?= $status === $key ? 'bg-panel-3 text-ink' : 'btn-ghost' ?>" <?= $status === $key ? 'aria-current="page"' : '' ?>>
                    <?= e($label) ?> <span class="font-mono text-[11px] text-dim"><?= (int) $counts[$key] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($thumbnails): ?>
            <ul class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                <?php foreach ($thumbnails as $thumbnail): ?>
                    <li class="card flex flex-col overflow-hidden">
                        <a href="/capas/arquivo?id=<?= $thumbnail['id'] ?>" target="_blank" rel="noopener" class="block bg-panel-2">
                            <img src="/capas/arquivo?id=<?= $thumbnail['id'] ?>" alt="<?= e($thumbnail['title']) ?>" loading="lazy" class="aspect-video w-full object-cover">
                        </a>
                        <div class="flex flex-1 flex-col p-4">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <?= badge(THUMBNAIL_STATUSES[$thumbnail['status']] ?? $thumbnail['status'], THUMBNAIL_STATUS_TONES[$thumbnail['status']] ?? 'neutral') ?>
                                <?= $thumbnail['in_use'] && $thumbnail['status'] !== 'usada' ? badge('Em uso', 'ok') : '' ?>
                            </div>
                            <p class="mt-2 leading-snug font-semibold"><?= e($thumbnail['title']) ?></p>
                            <p class="mt-0.5 truncate text-xs text-muted"><?= e($thumbnail['video_title'] ? 'Vídeo: ' . $thumbnail['video_title'] : 'Sem vídeo definido') ?></p>
                            <p class="mt-0.5 font-mono text-[11px] text-dim">
                                <?= e(($thumbnail['width'] ? $thumbnail['width'] . '×' . $thumbnail['height'] . ' · ' : '') . format_bytes($thumbnail['size_bytes']) . ' · ' . fmt_ago($thumbnail['created_at'])) ?>
                            </p>
                            <div class="mt-3 flex flex-wrap gap-1 border-t border-line pt-3">
                                <?php if ($canManage): ?>
                                    <a href="/capas/biblioteca/editar?id=<?= $thumbnail['id'] ?>" class="btn btn-secondary btn-sm"><?= icon('pencil', 'size-3.5') ?>Editar</a>
                                    <?php if ($thumbnail['video_id'] && !$thumbnail['in_use']): ?>
                                        <?= form_open('/capas/biblioteca/usar') ?>
                                            <input type="hidden" name="id" value="<?= $thumbnail['id'] ?>">
                                            <?= submit_button(icon('circle-check', 'size-3.5') . 'Usar', 'btn btn-ghost btn-sm', 'Salvando…') ?>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="/capas/arquivo?id=<?= $thumbnail['id'] ?>&amp;download=1" class="btn btn-ghost btn-sm" aria-label="Baixar"><?= icon('download', 'size-3.5') ?></a>
                                <?php if ($canDelete): ?>
                                    <?= form_open('/capas/biblioteca/excluir', 'ml-auto', 'data-confirm="Excluir esta capa? O arquivo será apagado."') ?>
                                        <input type="hidden" name="id" value="<?= $thumbnail['id'] ?>">
                                        <?= submit_button(icon('trash-2', 'size-3.5'), 'btn btn-ghost btn-sm text-danger') ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <section class="card"><?= empty_state('image', 'Nenhuma capa aqui', $status === '' ? 'Envie a primeira capa pelo formulário.' : 'Nenhuma capa com este status.') ?></section>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <section class="card h-fit xl:order-2" id="enviar">
            <?= card_header('Enviar capa', 'JPG, PNG ou WebP, até 2 MB', 'upload') ?>
            <?= form_open('/capas/biblioteca/enviar', 'space-y-4 p-4 sm:p-5', 'enctype="multipart/form-data"') ?>
                <div>
                    <label for="arquivo" class="label">Imagem</label>
                    <input id="arquivo" name="arquivo" type="file" required accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-muted file:mr-3 file:h-9 file:cursor-pointer file:rounded-lg file:border-0 file:bg-panel-3 file:px-3 file:font-semibold file:text-ink">
                    <p class="hint">O YouTube recomenda 1280 × 720 pixels.</p>
                </div>
                <div>
                    <label for="title" class="label">Nome <span class="font-normal text-dim">(opcional)</span></label>
                    <input id="title" name="title" maxlength="160" placeholder="Ex.: Opção 2, fundo amarelo" class="input">
                </div>
                <div>
                    <label for="video_id" class="label">Vídeo</label>
                    <select id="video_id" name="video_id" class="input">
                        <option value="">Ainda sem vídeo</option>
                        <?php foreach ($videos as $video): ?>
                            <option value="<?= e($video['id']) ?>" <?= $preselectedVideo === $video['id'] ? 'selected' : '' ?>><?= e($video['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="usar" <?= $preselectedVideo !== '' ? 'checked' : '' ?> class="checkbox mt-0.5">
                    <span class="text-sm">Usar como a capa deste vídeo</span>
                </label>
                <div>
                    <label for="notes" class="label">Observações</label>
                    <textarea id="notes" name="notes" rows="2" maxlength="1000" class="input h-auto py-2"></textarea>
                </div>
                <?= submit_button(icon('upload') . 'Enviar capa', 'btn btn-primary w-full', 'Enviando…') ?>
            </form>
        </section>
    <?php endif; ?>
</div>
