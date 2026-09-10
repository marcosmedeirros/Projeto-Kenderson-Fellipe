<?php defined('CONTROLADORIA') || exit; ?>
<?= page_header('Editar ideia', 'Pauta', '', '<a href="/ideias" class="btn btn-ghost">' . icon('arrow-left') . 'Voltar</a>') ?>

<?php if ($error): ?>
    <?= notice('danger', 'circle-alert', $error, '', '', 'mb-6') ?>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
    <section class="card">
        <?= card_header('Dados da ideia', '', 'lightbulb') ?>
        <?= form_open('/ideias/salvar', 'grid gap-4 p-4 sm:grid-cols-2 sm:p-5') ?>
            <input type="hidden" name="id" value="<?= (int) $idea['id'] ?>">
            <div class="sm:col-span-2">
                <label for="title" class="label">Título</label>
                <input id="title" name="title" required minlength="5" maxlength="200" value="<?= e($idea['title']) ?>" class="input">
            </div>
            <div class="sm:col-span-2">
                <label for="rationale" class="label">Por que vale gravar</label>
                <textarea id="rationale" name="rationale" required minlength="5" maxlength="1000" rows="4" class="input h-auto py-2"><?= e($idea['rationale']) ?></textarea>
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    <?php foreach (IDEA_TABS as $key => $label): if ($key === 'todas') continue; ?>
                        <option value="<?= e($key) ?>" <?= $idea['status'] === $key ? 'selected' : '' ?>><?= e(rtrim($label, 's')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="source" class="label">Origem</label>
                <select id="source" name="source" class="input">
                    <?php foreach (IDEA_SOURCES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $idea['source'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="score" class="label">Potencial (0 a 100)</label>
                <input id="score" name="score" type="number" min="0" max="100" required value="<?= e((string) $idea['score']) ?>" class="input">
            </div>
            <div class="flex items-end sm:col-span-2">
                <?= submit_button(icon('check') . 'Salvar alterações', 'btn btn-primary w-full sm:w-auto', 'Salvando…') ?>
            </div>
        </form>
    </section>

    <div class="space-y-4">
        <section class="card p-4 sm:p-5">
            <p class="text-sm font-bold">Virou pauta?</p>
            <p class="mt-1 text-sm text-muted">Cadastre o vídeo com o título e a justificativa desta ideia já preenchidos.</p>
            <a href="/videos/novo?ideia=<?= (int) $idea['id'] ?>" class="btn btn-secondary mt-3 w-full"><?= icon('clapperboard') ?>Criar vídeo</a>
        </section>
        <?php if ($canDelete): ?>
            <?= form_open('/ideias/excluir', 'flex justify-end', 'data-confirm="Excluir esta ideia?"') ?>
                <input type="hidden" name="id" value="<?= (int) $idea['id'] ?>">
                <?= submit_button(icon('trash-2') . 'Excluir ideia', 'btn btn-danger') ?>
            </form>
        <?php endif; ?>
    </div>
</div>
