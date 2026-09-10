import { BellRing, CalendarClock, Flame, ImageOff, MessageSquare, TriangleAlert } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { ActionForm, SubmitButton } from "@/components/forms";
import { Card, CardHeader, KeyValue, Notice, PageHeader } from "@/components/ui";
import { requireUser } from "@/lib/auth/dal";
import { can } from "@/lib/auth/permissions";
import { buildStockAlert } from "@/lib/bot/commands";
import { getScheduledVideos, summarizeStock } from "@/lib/channel/queries";
import { WEEKDAY_LABEL } from "@/lib/format";
import { getSetting } from "@/lib/settings";
import { saveAlertSettingsAction } from "./actions";

export const metadata: Metadata = { title: "Alertas" };

function Section({ icon: Icon, title, description, children }: { icon: typeof BellRing; title: string; description: string; children: React.ReactNode }) {
  return (
    <div className="grid gap-4 border-b border-line px-5 py-5 last:border-b-0 md:grid-cols-[240px_1fr]">
      <div className="flex gap-3">
        <Icon className="mt-0.5 size-4 shrink-0 text-dim" />
        <div>
          <p className="text-sm font-bold">{title}</p>
          <p className="mt-0.5 text-xs text-muted">{description}</p>
        </div>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">{children}</div>
    </div>
  );
}

const formatDate = (value: string | null) => (value ? value.split("-").reverse().join("/") : "nunca");

export default async function AlertsPage() {
  const user = await requireUser();
  const editable = can(user.role, "automacoes");
  const [alertas, bot, estado, scheduled] = await Promise.all([getSetting("alertas"), getSetting("bot"), getSetting("automacoes"), getScheduledVideos()]);
  const stock = summarizeStock(scheduled);
  const preview = buildStockAlert(stock, alertas.estoqueMinimoDias);

  return (
    <>
      <PageHeader
        eyebrow="Automação"
        title="Alertas automáticos"
        description="O bot avisa o grupo sozinho quando algo precisa de atenção, sem ninguém precisar perguntar."
      />

      {(!bot.ativo || !bot.grupoId) && (
        <Notice
          tone="warn"
          icon={TriangleAlert}
          className="mb-6"
          title="Os alertas ainda não estão sendo enviados."
          action={<Link href="/bot" className="btn btn-secondary btn-sm">Configurar bot</Link>}
        >
          {bot.ativo ? "Defina o ID do grupo do WhatsApp na página do bot." : "O bot está desligado."}
        </Notice>
      )}

      <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
        <Card>
          <ActionForm action={saveAlertSettingsAction}>
            <fieldset disabled={!editable}>
              <Section icon={CalendarClock} title="Estoque baixo" description="Aviso diário quando os vídeos programados não cobrem o mínimo de dias.">
                <div>
                  <label htmlFor="estoqueMinimoDias" className="label">Mínimo de dias de estoque</label>
                  <input id="estoqueMinimoDias" name="estoqueMinimoDias" type="number" min={1} max={90} defaultValue={alertas.estoqueMinimoDias} className="input" />
                </div>
                <div>
                  <label htmlFor="alertaEstoqueHora" className="label">Horário da verificação</label>
                  <input id="alertaEstoqueHora" name="alertaEstoqueHora" type="time" defaultValue={alertas.alertaEstoqueHora} className="input" />
                </div>
              </Section>

              <Section icon={ImageOff} title="Capa pendente" description="Aviso quando um vídeo está perto de publicar e ainda não tem thumbnail.">
                <div>
                  <label htmlFor="capaAvisoDias" className="label">Avisar com quantos dias de antecedência</label>
                  <input id="capaAvisoDias" name="capaAvisoDias" type="number" min={1} max={30} defaultValue={alertas.capaAvisoDias} className="input" />
                  <p className="hint">Enviado junto com a verificação de estoque.</p>
                </div>
              </Section>

              <Section icon={MessageSquare} title="Resumo semanal" description="O mesmo conteúdo do /resumo, enviado no dia e horário escolhidos.">
                <label className="flex items-center gap-3 sm:col-span-2">
                  <input type="checkbox" name="resumoSemanalAtivo" defaultChecked={alertas.resumoSemanalAtivo} className="checkbox" />
                  <span className="text-sm font-semibold">Enviar resumo semanal</span>
                </label>
                <div>
                  <label htmlFor="resumoSemanalDia" className="label">Dia</label>
                  <select id="resumoSemanalDia" name="resumoSemanalDia" defaultValue={alertas.resumoSemanalDia} className="input">
                    {WEEKDAY_LABEL.map((label, i) => (
                      <option key={label} value={i}>{label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label htmlFor="resumoSemanalHora" className="label">Horário</label>
                  <input id="resumoSemanalHora" name="resumoSemanalHora" type="time" defaultValue={alertas.resumoSemanalHora} className="input" />
                </div>
              </Section>

              <Section icon={BellRing} title="Relatório mensal" description="Todo dia 1º: views, horas, inscritos e os mais vistos do mês anterior.">
                <label className="flex items-center gap-3 sm:col-span-2">
                  <input type="checkbox" name="relatorioMensalAtivo" defaultChecked={alertas.relatorioMensalAtivo} className="checkbox" />
                  <span className="text-sm font-semibold">Enviar relatório mensal</span>
                </label>
                <div>
                  <label htmlFor="relatorioMensalHora" className="label">Horário</label>
                  <input id="relatorioMensalHora" name="relatorioMensalHora" type="time" defaultValue={alertas.relatorioMensalHora} className="input" />
                </div>
              </Section>

              <Section icon={Flame} title="Vídeo em alta" description="Quantas vezes acima da mediana do canal um vídeo precisa ir para ser considerado viral.">
                <div>
                  <label htmlFor="viralMultiplicador" className="label">Multiplicador</label>
                  <input id="viralMultiplicador" name="viralMultiplicador" inputMode="decimal" defaultValue={String(alertas.viralMultiplicador).replace(".", ",")} className="input" />
                  <p className="hint">Ex.: 2 = o dobro das views de um vídeo típico em 7 dias.</p>
                </div>
              </Section>

              {editable && (
                <div className="flex justify-end border-t border-line px-5 py-4">
                  <SubmitButton pendingText="Salvando…">Salvar alertas</SubmitButton>
                </div>
              )}
            </fieldset>
          </ActionForm>
        </Card>

        <div className="space-y-6">
          <Card>
            <CardHeader title="Prévia do alerta de estoque" description="Com os dados de agora" icon={MessageSquare} />
            <div className="bg-[#0c1318] p-5">
              <div className="max-w-[92%] rounded-lg rounded-tl-sm bg-panel-3 px-3 py-2 text-sm whitespace-pre-wrap">
                <p className="mb-0.5 text-xs font-bold text-accent">Controladoria</p>
                {preview.replaceAll("*", "")}
              </div>
              {stock.daysCovered >= alertas.estoqueMinimoDias && (
                <p className="mt-3 text-xs text-dim">Hoje o estoque está acima do mínimo, então esse alerta não seria enviado.</p>
              )}
            </div>
          </Card>

          <Card>
            <CardHeader title="Últimos envios" icon={BellRing} />
            <div className="p-5">
              <KeyValue
                items={[
                  { label: "Verificação diária", value: formatDate(estado.ultimoAlertaEstoque) },
                  { label: "Alerta de capa", value: formatDate(estado.ultimoAlertaCapa) },
                  { label: "Resumo semanal", value: formatDate(estado.ultimoResumoSemanal) },
                  { label: "Relatório mensal", value: estado.ultimoRelatorioMensal ?? "nunca" },
                ]}
              />
              <p className="mt-4 text-xs text-dim">Todos os horários seguem o fuso de São Paulo.</p>
            </div>
          </Card>
        </div>
      </div>
    </>
  );
}
