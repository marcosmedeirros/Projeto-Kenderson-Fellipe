<?php
defined('CONTROLADORIA') || exit;

$isNew = $form['id'] === '';
$selected = static fn (string $current, string $value): string => $current === $value ? 'selected' : '';
?>
<?= page_header(
    $isNew ? 'Novo vídeo' : 'Editar vídeo',
    'Vídeos',
    $isNew ? 'Cadastre um vídeo planejado, agendado ou já publicado.' : e($form['title']),
    '<a href="/videos" class="btn btn-ghost">' . icon('arrow-left') . 'Voltar</a>'
) ?>

<?php if ($error): ?>
    <?= notice('danger', 'circle-alert', $error, '', '', 'mb-6') ?>
<?php endif; ?>

<?= form_open('/videos/salvar', 'grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]') ?>
    <input type="hidden" name="id" value="<?= e($form['id']) ?>">

    <section class="card">
        <?= card_header('Informações do vídeo', '', 'clapperboard') ?>
        <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <div class="sm:col-span-2">
                <label for="title" class="label">Título</label>
                <input id="title" name="title" required minlength="3" maxlength="255" value="<?= e($form['title']) ?>" class="input">
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    <?php foreach (VIDEO_STATUSES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $selected($form['status'], $key) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="publish_at" class="label">Data e hora da publicação</label>
                <input id="publish_at" name="publish_at" type="datetime-local" value="<?= e($form['publish_at']) ?>" class="input">
                <p class="hint">Horário de São Paulo. Obrigatório para agendado e publicado.</p>
            </div>
            <div class="sm:col-span-2">
                <label for="youtube" class="label">Link ou ID do YouTube <span class="font-normal text-dim">(opcional)</span></label>
                <input id="youtube" name="youtube" maxlength="200" value="<?= e($form['youtube']) ?>" placeholder="https://youtu.be/…" class="input font-mono" autocomplete="off">
            </div>
            <div>
                <label for="duration" class="label">Duração <span class="font-normal text-dim">(opcional)</span></label>
                <input id="duration" name="duration" maxlength="10" value="<?= e($form['duration']) ?>" placeholder="12:30" class="input font-mono" inputmode="numeric">
            </div>
            <label class="flex h-10 items-center gap-3 self-end rounded-lg border border-line bg-panel-2 px-3">
                <input type="checkbox" name="is_short" <?= $form['is_short'] ? 'checked' : '' ?> class="checkbox">
                <span class="text-sm font-semibold">É um Short</span>
            </label>
            <div class="sm:col-span-2">
                <label for="notes" class="label">Observações</label>
                <textarea id="notes" name="notes" rows="4" maxlength="2000" class="input h-auto py-2"><?= e($form['notes']) ?></textarea>
            </div>
        </div>
    </section>

    <div class="space-y-6">
        <section class="card">
            <?= card_header('Capa', '', 'image') ?>
            <div class="space-y-4 p-4 sm:p-5">
                <?php if ($form['thumbnail_id'] !== ''): ?>
                    <img src="/capas/arquivo?id=<?= (int) $form['thumbnail_id'] ?>" alt="Capa escolhida" class="aspect-video w-full rounded-lg bg-panel-2 object-cover">
                <?php endif; ?>
                <div>
                    <label for="thumbnail_id" class="label">Capa da biblioteca</label>
                    <select id="thumbnail_id" name="thumbnail_id" class="input">
                        <option value="">Nenhuma escolhida</option>
                        <?php foreach ($thumbnails as $thumbnail): ?>
                            <option value="<?= (int) $thumbnail['id'] ?>" <?= $selected($form['thumbnail_id'], (string) $thumbnail['id']) ?>>
                                <?= e($thumbnail['title'] . ($thumbnail['video_title'] ? ' · ' . $thumbnail['video_title'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint">Escolher uma capa marca o vídeo como "Capa pronta".</p>
                </div>
                <div>
                    <label for="thumbnail_status" class="label">Situação da capa</label>
                    <select id="thumbnail_status" name="thumbnail_status" class="input">
                        <?php foreach (VIDEO_THUMB_STATUSES as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $selected($form['thumbnail_status'], $key) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$isNew): ?>
                    <a href="/capas/biblioteca?video=<?= e(rawurlencode($form['id'])) ?>#enviar" class="btn btn-secondary btn-sm w-full"><?= icon('upload', 'size-3.5') ?>Enviar nova capa</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <?= card_header('Números', 'Opcional, para vídeos já publicados', 'trending-up') ?>
            <div class="grid grid-cols-2 gap-4 p-4 sm:p-5">
                <div>
                    <label for="views" class="label">Views</label>
                    <input id="views" name="views" inputmode="numeric" maxlength="12" value="<?= e($form['views']) ?>" class="input font-mono">
                </div>
                <div>
                    <label for="views_7d" class="label">Views em 7 dias</label>
                    <input id="views_7d" name="views_7d" inputmode="numeric" maxlength="12" value="<?= e($form['views_7d']) ?>" class="input font-mono">
                </div>
            </div>
        </section>

        <?= submit_button(icon('check') . ($isNew ? 'Cadastrar vídeo' : 'Salvar alterações'), 'btn btn-primary h-11 w-full', 'Salvando…') ?>
    </div>
</form>

<?php if (!$isNew && $canDelete): ?>
    <div class="mt-6 flex justify-end border-t border-line pt-6">
        <?= form_open('/videos/excluir', '', 'data-confirm="Excluir este vídeo? As métricas dele também serão apagadas."') ?>
            <input type="hidden" name="id" value="<?= e($form['id']) ?>">
            <?= submit_button(icon('trash-2') . 'Excluir vídeo', 'btn btn-danger') ?>
        </form>
    </div>
<?php endif; ?>
