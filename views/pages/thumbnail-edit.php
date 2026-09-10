<?php defined('CONTROLADORIA') || exit; ?>
<?= page_header('Editar capa', 'Capas', e($thumbnail['title']), '<a href="/capas/biblioteca" class="btn btn-ghost">' . icon('arrow-left') . 'Voltar</a>') ?>

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
    <section class="card overflow-hidden">
        <img src="/capas/arquivo?id=<?= $thumbnail['id'] ?>" alt="<?= e($thumbnail['title']) ?>" class="aspect-video w-full bg-panel-2 object-contain">
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 sm:px-5">
            <p class="font-mono text-xs text-dim"><?= e(($thumbnail['width'] ? $thumbnail['width'] . '×' . $thumbnail['height'] . ' · ' : '') . format_bytes($thumbnail['size_bytes']) . ' · enviada ' . fmt_ago($thumbnail['created_at'])) ?></p>
            <a href="/capas/arquivo?id=<?= $thumbnail['id'] ?>&amp;download=1" class="btn btn-secondary btn-sm"><?= icon('download', 'size-3.5') ?>Baixar</a>
        </div>
    </section>

    <div class="space-y-6">
        <section class="card">
            <?= card_header('Dados da capa', '', 'image') ?>
            <?= form_open('/capas/biblioteca/salvar', 'space-y-4 p-4 sm:p-5') ?>
                <input type="hidden" name="id" value="<?= $thumbnail['id'] ?>">
                <div>
                    <label for="title" class="label">Nome</label>
                    <input id="title" name="title" required minlength="2" maxlength="160" value="<?= e($thumbnail['title']) ?>" class="input">
                </div>
                <div>
                    <label for="status" class="label">Status</label>
                    <select id="status" name="status" class="input">
                        <?php foreach (THUMBNAIL_STATUSES as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($thumbnail['in_use'] ? 'usada' : $thumbnail['status']) === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint">"Em uso" define esta imagem como a capa do vídeo escolhido abaixo.</p>
                </div>
                <div>
                    <label for="video_id" class="label">Vídeo</label>
                    <select id="video_id" name="video_id" class="input">
                        <option value="">Ainda sem vídeo</option>
                        <?php foreach ($videos as $video): ?>
                            <option value="<?= e($video['id']) ?>" <?= $thumbnail['video_id'] === $video['id'] ? 'selected' : '' ?>><?= e($video['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="notes" class="label">Observações</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000" class="input h-auto py-2"><?= e((string) $thumbnail['notes']) ?></textarea>
                </div>
                <?= submit_button(icon('check') . 'Salvar alterações', 'btn btn-primary w-full', 'Salvando…') ?>
            </form>
        </section>

        <?php if ($canDelete): ?>
            <?= form_open('/capas/biblioteca/excluir', 'flex justify-end', 'data-confirm="Excluir esta capa? O arquivo será apagado."') ?>
                <input type="hidden" name="id" value="<?= $thumbnail['id'] ?>">
                <?= submit_button(icon('trash-2') . 'Excluir capa', 'btn btn-danger') ?>
            </form>
        <?php endif; ?>
    </div>
</div>
