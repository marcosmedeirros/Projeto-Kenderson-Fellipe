import { Bot, Link2, MessageSquare, Settings, Smartphone, Users } from "lucide-react";
import type { Metadata } from "next";
import { headers } from "next/headers";
import { ActionForm, SubmitButton } from "@/components/forms";
import { Badge, Card, CardHeader, EmptyState, PageHeader, type Tone } from "@/components/ui";
import { requireUser } from "@/lib/auth/dal";
import { can } from "@/lib/auth/permissions";
import { COMMAND_INFO } from "@/lib/bot/commands";
import { getConnectionState, type ConnectionState } from "@/lib/bot/evolution";
import { getRecentBotMessages } from "@/lib/channel/queries";
import { fmtDateTime } from "@/lib/format";
import { COMMANDS, getSetting } from "@/lib/settings";
import { saveBotSettingsAction } from "./actions";
import { BotSimulator } from "./simulator";

export const metadata: Metadata = { title: "Bot do WhatsApp" };

const CONNECTION: Record<ConnectionState, { label: string; tone: Tone; hint: string }> = {
  open: { label: "Conectado", tone: "ok", hint: "O número do bot está online." },
  connecting: { label: "Conectando", tone: "warn", hint: "O Evolution está reconectando o número." },
  close: { label: "Desconectado", tone: "danger", hint: "Leia o QR code no Evolution para reconectar o número." },
  nao_configurado: { label: "Não configurado", tone: "neutral", hint: "Cadastre o Evolution em Integrações." },
  erro: { label: "Sem resposta", tone: "danger", hint: "Não foi possível falar com o Evolution. Confira a URL e a chave." },
};

const ORIGIN_LABEL = { whatsapp: "WhatsApp", simulador: "Simulador", automacao: "Automático" } as const;

export default async function BotPage() {
  const user = await requireUser();
  const canConfigure = can(user.role, "automacoes");
  const [bot, connection, messages, h] = await Promise.all([getSetting("bot"), getConnectionState(), getRecentBotMessages(30), headers()]);
  const status = CONNECTION[connection];
  const host = h.get("x-forwarded-host") ?? h.get("host") ?? "seu-dominio";
  const proto = h.get("x-forwarded-proto") ?? (process.env.NODE_ENV === "production" ? "https" : "http");
  const webhookUrl = `${proto}://${host}/api/webhooks/evolution?token=…`;
  const hasWebhookToken = Boolean(process.env.EVOLUTION_WEBHOOK_TOKEN);
  const enabledCommands = COMMANDS.filter((c) => bot.comandos[c]);

  return (
    <>
      <PageHeader
        eyebrow="Automação"
        title="Bot do WhatsApp"
        description="O bot responde aos comandos no grupo com os mesmos dados deste painel."
      />

      <div className="grid gap-4 md:grid-cols-3">
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <span className="eyebrow">Número do bot</span>
            <Smartphone className="size-4 text-dim" />
          </div>
          <div className="mt-3"><Badge tone={status.tone} className="px-2.5 py-1 text-xs">{status.label}</Badge></div>
          <p className="mt-2 text-[13px] text-muted">{status.hint}</p>
        </div>
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <span className="eyebrow">Grupo autorizado</span>
            <Users className="size-4 text-dim" />
          </div>
          <p className="mt-3 font-semibold">{bot.grupoNome || "Sem nome"}</p>
          <p className="mt-1 truncate font-mono text-xs text-muted">{bot.grupoId || "ID do grupo não definido"}</p>
          {!bot.ativo && <Badge tone="warn" className="mt-2">Bot desligado</Badge>}
        </div>
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <span className="eyebrow">Webhook</span>
            <Link2 className="size-4 text-dim" />
          </div>
          <p className="mt-3 truncate font-mono text-xs text-ink" title={webhookUrl}>{webhookUrl}</p>
          <p className="mt-2 text-[13px] text-muted">
            {hasWebhookToken ? "Token configurado no servidor." : <span className="text-warn">Defina EVOLUTION_WEBHOOK_TOKEN no servidor.</span>}
          </p>
        </div>
      </div>

      <div className="mt-6 grid gap-6 xl:grid-cols-[1fr_400px]">
        <Card>
          <CardHeader title="Testar comandos" description="Veja a resposta antes de usar no grupo" icon={MessageSquare} />
          <div className="p-5">
            <BotSimulator commands={enabledCommands} groupName={bot.grupoNome || "Grupo do canal"} />
          </div>
        </Card>

        <Card className="h-fit">
          <CardHeader title="Configurações" icon={Settings} />
          {canConfigure ? (
            <ActionForm action={saveBotSettingsAction} className="space-y-5 p-5">
              <label className="flex items-center justify-between gap-3 rounded-lg border border-line bg-panel-2 px-3 py-2.5">
                <span>
                  <span className="block text-sm font-semibold">Bot ligado</span>
                  <span className="block text-xs text-muted">Desligado, ele ignora comandos e não envia alertas.</span>
                </span>
                <input type="checkbox" name="ativo" defaultChecked={bot.ativo} className="checkbox" />
              </label>
              <div>
                <label htmlFor="grupoNome" className="label">Nome do grupo</label>
                <input id="grupoNome" name="grupoNome" defaultValue={bot.grupoNome} maxLength={80} className="input" />
              </div>
              <div>
                <label htmlFor="grupoId" className="label">ID do grupo</label>
                <input id="grupoId" name="grupoId" defaultValue={bot.grupoId} maxLength={80} className="input font-mono" placeholder="120363000000000000@g.us" />
                <p className="hint">O bot só responde nesse grupo. O ID aparece no Evolution, na lista de grupos.</p>
              </div>
              <fieldset>
                <legend className="label">Comandos ativos</legend>
                <div className="divide-y divide-line rounded-lg border border-line">
                  {COMMANDS.map((c) => (
                    <label key={c} className="flex items-start gap-3 px-3 py-2.5">
                      <input type="checkbox" name={`cmd_${c}`} defaultChecked={bot.comandos[c]} className="checkbox mt-0.5" />
                      <span>
                        <span className="block font-mono text-sm font-semibold">{COMMAND_INFO[c].label}</span>
                        <span className="block text-xs text-muted">{COMMAND_INFO[c].description}</span>
                      </span>
                    </label>
                  ))}
                </div>
              </fieldset>
              <SubmitButton className="btn btn-primary w-full" pendingText="Salvando…">Salvar configurações</SubmitButton>
            </ActionForm>
          ) : (
            <p className="p-5 text-sm text-muted">Só administradores e editores podem alterar as configurações do bot.</p>
          )}
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader title="Histórico" description="Últimas 30 mensagens do bot" icon={Bot} />
        {messages.length ? (
          <div className="overflow-x-auto">
            <table className="table-base min-w-[640px]">
              <thead>
                <tr>
                  <th>Quando</th>
                  <th>Origem</th>
                  <th>Quem</th>
                  <th>Mensagem</th>
                </tr>
              </thead>
              <tbody>
                {messages.map((m) => (
                  <tr key={m.id}>
                    <td className="font-mono text-xs whitespace-nowrap text-muted">{fmtDateTime(m.createdAt)}</td>
                    <td><Badge tone={m.origin === "automacao" ? "warn" : m.origin === "simulador" ? "neutral" : "info"}>{ORIGIN_LABEL[m.origin]}</Badge></td>
                    <td className="text-sm whitespace-nowrap">{m.direction === "entrada" ? m.sender ?? "Grupo" : "Bot"}</td>
                    <td className="max-w-md">
                      <p className="truncate text-sm text-muted">{m.text.split("\n")[0].replaceAll("*", "")}</p>
                      {!m.delivered && <Badge tone="danger" className="mt-1">Não entregue</Badge>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState icon={Bot} title="Nenhuma mensagem ainda" />
        )}
      </Card>
    </>
  );
}
