import { KeyRound, Lock, MessageSquare, PlayCircle, Sparkles } from "lucide-react";
import type { Metadata } from "next";
import { ActionForm, SubmitButton } from "@/components/forms";
import { Badge, Card, CardHeader, KeyValue, PageHeader } from "@/components/ui";
import { requirePermission } from "@/lib/auth/dal";
import { fmtAgo } from "@/lib/format";
import { getIntegrationsOverview } from "@/lib/integrations";
import { removeIntegrationAction, saveEvolutionAction, saveGeminiAction, testEvolutionAction } from "./actions";

export const metadata: Metadata = { title: "Integrações" };

export default async function IntegrationsPage() {
  await requirePermission("integracoes");
  const overview = await getIntegrationsOverview();
  const { evolution, gemini, youtube } = overview;

  return (
    <>
      <PageHeader
        eyebrow="Administração"
        title="Integrações"
        description="Conexões com o YouTube, o WhatsApp (Evolution) e a IA do Google (Gemini)."
      />

      <div className="mb-6 flex items-start gap-3 rounded-xl border border-line bg-panel px-4 py-3.5 text-sm text-muted">
        <Lock className="mt-0.5 size-4 shrink-0 text-accent" />
        <p>
          As chaves ficam criptografadas no banco (AES-256-GCM) e nunca aparecem completas na tela. Só administradores veem esta página, e
          toda alteração fica registrada na auditoria.
        </p>
      </div>

      <div className="grid gap-6 xl:grid-cols-3">
        <Card>
          <CardHeader
            title="YouTube"
            description="Vídeos, agendamentos e métricas"
            icon={PlayCircle}
            action={youtube.connected ? <Badge tone="ok">Conectado</Badge> : <Badge tone="neutral">Próxima etapa</Badge>}
          />
          <div className="space-y-4 p-5 text-sm">
            {youtube.connected ? (
              <KeyValue items={[{ label: "Última sincronização", value: youtube.lastSyncAt ? fmtAgo(youtube.lastSyncAt) : "—" }]} />
            ) : (
              <>
                <p className="text-muted">
                  O dono do canal autoriza o acesso uma única vez com a conta Google. O acesso é <strong className="text-ink">somente leitura</strong>:
                  o painel consulta vídeos e métricas, mas não publica, não edita e não apaga nada.
                </p>
                <ul className="space-y-1.5 text-muted">
                  <li>• Vídeos programados e status das capas</li>
                  <li>• Views, retenção e inscritos por vídeo</li>
                  <li>• Termos de busca que trazem público</li>
                </ul>
                <button type="button" className="btn btn-secondary w-full" disabled>
                  Conectar com Google
                </button>
                <p className="text-xs text-dim">Enquanto isso, o painel mostra dados de exemplo.</p>
              </>
            )}
          </div>
        </Card>

        <Card>
          <CardHeader
            title="WhatsApp · Evolution"
            description="Número do bot e envio de mensagens"
            icon={MessageSquare}
            action={evolution.configured ? <Badge tone="ok">Configurado</Badge> : <Badge tone="warn">Pendente</Badge>}
          />
          <div className="p-5">
            {evolution.configured && evolution.source === "env" && (
              <p className="mb-4 rounded-lg bg-panel-2 px-3 py-2 text-xs text-muted">Hoje vem das variáveis de ambiente do servidor. Salvar aqui passa a usar a configuração do painel.</p>
            )}
            <ActionForm action={saveEvolutionAction} className="space-y-4">
              <div>
                <label htmlFor="baseUrl" className="label">URL do Evolution</label>
                <input id="baseUrl" name="baseUrl" type="url" required defaultValue={evolution.configured ? evolution.baseUrl : ""} placeholder="https://evolution.seudominio.com.br" className="input" />
              </div>
              <div>
                <label htmlFor="instance" className="label">Instância</label>
                <input id="instance" name="instance" required defaultValue={evolution.configured ? evolution.instance : ""} placeholder="controladoria" className="input font-mono" />
              </div>
              <div>
                <label htmlFor="evoKey" className="label">API key</label>
                <input
                  id="evoKey"
                  name="apiKey"
                  type="password"
                  autoComplete="off"
                  placeholder={evolution.configured ? `Atual: ${evolution.apiKey} · deixe em branco para manter` : "Chave da instância"}
                  className="input font-mono"
                />
              </div>
              <SubmitButton className="btn btn-primary w-full" pendingText="Salvando…">Salvar</SubmitButton>
            </ActionForm>
            {evolution.configured && (
              <div className="mt-4 flex gap-2 border-t border-line pt-4">
                <ActionForm action={testEvolutionAction} className="flex-1">
                  <SubmitButton className="btn btn-secondary btn-sm w-full" pendingText="Testando…">Testar conexão</SubmitButton>
                </ActionForm>
                {evolution.source === "painel" && (
                  <form action={removeIntegrationAction}>
                    <input type="hidden" name="key" value="evolution" />
                    <SubmitButton className="btn btn-danger btn-sm">Remover</SubmitButton>
                  </form>
                )}
              </div>
            )}
          </div>
        </Card>

        <Card>
          <CardHeader
            title="Gemini"
            description="IA que sugere ideias de pauta"
            icon={Sparkles}
            action={gemini.configured ? <Badge tone="ok">Configurado</Badge> : <Badge tone="neutral">Opcional</Badge>}
          />
          <div className="p-5">
            <ActionForm action={saveGeminiAction} className="space-y-4">
              <div>
                <label htmlFor="geminiKey" className="label">Chave da API</label>
                <input
                  id="geminiKey"
                  name="apiKey"
                  type="password"
                  autoComplete="off"
                  placeholder={gemini.configured ? `Atual: ${gemini.apiKey} · deixe em branco para manter` : "Chave do Google AI Studio"}
                  className="input font-mono"
                />
              </div>
              <div>
                <label htmlFor="model" className="label">Modelo</label>
                <input id="model" name="model" required defaultValue={gemini.model} className="input font-mono" />
                <p className="hint">Use um projeto do Google Cloud só para este painel, com limite de uso próprio.</p>
              </div>
              <SubmitButton className="btn btn-primary w-full" pendingText="Salvando…">
                <KeyRound className="size-4" /> Salvar
              </SubmitButton>
            </ActionForm>
            {gemini.configured && gemini.source === "painel" && (
              <form action={removeIntegrationAction} className="mt-4 border-t border-line pt-4">
                <input type="hidden" name="key" value="gemini" />
                <SubmitButton className="btn btn-danger btn-sm w-full">Remover chave</SubmitButton>
              </form>
            )}
          </div>
        </Card>
      </div>
    </>
  );
}
