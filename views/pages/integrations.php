<?php
defined('CONTROLADORIA') || exit;

$youtube = $overview['youtube'];
$evolution = $overview['evolution'];
$gemini = $overview['gemini'];
?>
<?= page_header('Integrações', 'Administração', 'Conexões com o YouTube, o WhatsApp (Evolution) e a IA do Google (Gemini).') ?>

<div class="mb-6 flex items-start gap-3 rounded-xl border border-line bg-panel px-4 py-3.5 text-sm text-muted">
    <?= icon('lock', 'mt-0.5 size-4 shrink-0 text-accent') ?>
    <p>As chaves ficam criptografadas no banco (AES-256-GCM) e nunca aparecem completas na tela. Só administradores veem esta página, e toda alteração fica registrada na auditoria.</p>
</div>

<div class="grid gap-6 xl:grid-cols-3">
    <section class="card">
        <?= card_header('YouTube', 'Vídeos, agendamentos e métricas', 'circle-play', $youtube['connected'] ? badge('Conectado', 'ok') : badge('Próxima etapa')) ?>
        <div class="space-y-4 p-5 text-sm">
            <?php if ($youtube['connected']): ?>
                <?= key_value([['Última sincronização', $youtube['last_sync_at'] ? e(fmt_ago($youtube['last_sync_at'])) : '—']]) ?>
            <?php else: ?>
                <p class="text-muted">O dono do canal autoriza o acesso uma única vez com a conta Google. O acesso é <strong class="text-ink">somente leitura</strong>: o painel consulta vídeos e métricas, mas não publica, não edita e não apaga nada.</p>
                <ul class="space-y-1.5 text-muted">
                    <li>• Vídeos programados e status das capas</li>
                    <li>• Views, retenção e inscritos por vídeo</li>
                    <li>• Termos de busca que trazem público</li>
                </ul>
                <button type="button" class="btn btn-secondary w-full" disabled>Conectar com Google</button>
                <p class="text-xs text-dim">Enquanto isso, o painel mostra dados de exemplo.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <?= card_header('WhatsApp · Evolution', 'Número do bot e envio de mensagens', 'message-square', $evolution['configured'] ? badge('Configurado', 'ok') : badge('Pendente', 'warn')) ?>
        <div class="p-5">
            <?php if ($evolution['configured'] && $evolution['source'] === 'arquivo'): ?>
                <p class="mb-4 rounded-lg bg-panel-2 px-3 py-2 text-xs text-muted">Hoje vem do arquivo de configuração do servidor. Salvar aqui passa a usar a configuração do painel.</p>
            <?php endif; ?>
            <?= form_open('/integracoes/evolution', 'space-y-4') ?>
                <div>
                    <label for="baseUrl" class="label">URL do Evolution</label>
                    <input id="baseUrl" name="baseUrl" type="url" required value="<?= e($evolution['base_url'] ?? '') ?>" placeholder="https://evolution.seudominio.com.br" class="input">
                </div>
                <div>
                    <label for="instance" class="label">Instância</label>
                    <input id="instance" name="instance" required value="<?= e($evolution['instance'] ?? '') ?>" placeholder="controladoria" class="input font-mono">
                </div>
                <div>
                    <label for="evoKey" class="label">API key</label>
                    <input id="evoKey" name="apiKey" type="password" autocomplete="off" class="input font-mono"
                           placeholder="<?= e($evolution['configured'] ? 'Atual: ' . $evolution['api_key'] . ' · deixe em branco para manter' : 'Chave da instância') ?>">
                </div>
                <?= submit_button('Salvar', 'btn btn-primary w-full', 'Salvando…') ?>
            </form>
            <?php if ($evolution['configured']): ?>
                <div class="mt-4 flex gap-2 border-t border-line pt-4">
                    <?= form_open('/integracoes/evolution/testar', 'flex-1') ?><?= submit_button('Testar conexão', 'btn btn-secondary btn-sm w-full', 'Testando…') ?></form>
                    <?php if ($evolution['source'] === 'painel'): ?>
                        <?= form_open('/integracoes/remover', '', 'data-confirm="Remover a configuração do Evolution?"') ?>
                            <input type="hidden" name="key" value="evolution"><?= submit_button('Remover', 'btn btn-danger btn-sm') ?>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <?= card_header('Gemini', 'IA que sugere ideias de pauta', 'sparkles', $gemini['configured'] ? badge('Configurado', 'ok') : badge('Opcional')) ?>
        <div class="p-5">
            <?= form_open('/integracoes/gemini', 'space-y-4') ?>
                <div>
                    <label for="geminiKey" class="label">Chave da API</label>
                    <input id="geminiKey" name="apiKey" type="password" autocomplete="off" class="input font-mono"
                           placeholder="<?= e($gemini['configured'] ? 'Atual: ' . $gemini['api_key'] . ' · deixe em branco para manter' : 'Chave do Google AI Studio') ?>">
                </div>
                <div>
                    <label for="model" class="label">Modelo</label>
                    <input id="model" name="model" required value="<?= e($gemini['model']) ?>" class="input font-mono">
                    <p class="hint">Use um projeto do Google Cloud só para este painel, com limite de uso próprio.</p>
                </div>
                <?= submit_button(icon('key-round') . 'Salvar', 'btn btn-primary w-full', 'Salvando…') ?>
            </form>
            <?php if ($gemini['configured'] && $gemini['source'] === 'painel'): ?>
                <?= form_open('/integracoes/remover', 'mt-4 border-t border-line pt-4', 'data-confirm="Remover a chave do Gemini?"') ?>
                    <input type="hidden" name="key" value="gemini"><?= submit_button('Remover chave', 'btn btn-danger btn-sm w-full') ?>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
