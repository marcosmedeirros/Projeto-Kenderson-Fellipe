"use client";

import { Bot, Send } from "lucide-react";
import { Fragment, useEffect, useRef, useState, useTransition } from "react";
import { simulateCommandAction } from "./actions";

type Message = { id: number; from: "user" | "bot"; text: string; at: string };

/** Mostra *negrito* do WhatsApp e mantém as quebras de linha. */
function WhatsAppText({ text }: { text: string }) {
  return (
    <>
      {text.split(/(\*[^*\n]+\*)/g).map((part, i) =>
        part.startsWith("*") && part.endsWith("*") && part.length > 2 ? (
          <strong key={i} className="font-bold">{part.slice(1, -1)}</strong>
        ) : (
          <Fragment key={i}>{part}</Fragment>
        ),
      )}
    </>
  );
}

const time = () => new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });

export function BotSimulator({ commands, groupName }: { commands: string[]; groupName: string }) {
  const [messages, setMessages] = useState<Message[]>([]);
  const [text, setText] = useState("");
  const [pending, startTransition] = useTransition();
  const endRef = useRef<HTMLDivElement>(null);
  const nextId = useRef(0);

  useEffect(() => {
    endRef.current?.scrollIntoView({ block: "nearest", behavior: "smooth" });
  }, [messages, pending]);

  function send(value: string) {
    const content = value.trim();
    if (!content || pending) return;
    setMessages((m) => [...m, { id: nextId.current++, from: "user", text: content, at: time() }]);
    setText("");
    startTransition(async () => {
      const result = await simulateCommandAction(content);
      setMessages((m) => [...m, { id: nextId.current++, from: "bot", text: result.reply ?? result.error ?? "", at: time() }]);
    });
  }

  return (
    <div className="overflow-hidden rounded-xl border border-line">
      <div className="flex items-center gap-3 border-b border-line bg-panel-2 px-4 py-3">
        <span className="grid size-9 place-items-center rounded-full bg-accent text-accent-ink">
          <Bot className="size-4" />
        </span>
        <div className="leading-tight">
          <p className="text-sm font-bold">{groupName}</p>
          <p className="text-xs text-muted">Simulador · usa os dados reais do painel, não envia nada no WhatsApp</p>
        </div>
      </div>

      <div className="h-[420px] space-y-2.5 overflow-y-auto bg-[#0c1318] p-4" aria-live="polite">
        {!messages.length && (
          <p className="mx-auto mt-24 max-w-xs text-center text-sm text-dim">
            Toque em um comando abaixo para ver exatamente o que o bot responderia no grupo.
          </p>
        )}
        {messages.map((m) => (
          <div key={m.id} className={m.from === "user" ? "flex justify-end" : "flex justify-start"}>
            <div
              className={
                m.from === "user"
                  ? "max-w-[80%] rounded-lg rounded-tr-sm bg-[#134536] px-3 py-2 text-sm"
                  : "max-w-[85%] rounded-lg rounded-tl-sm bg-panel-3 px-3 py-2 text-sm"
              }
            >
              {m.from === "bot" && <p className="mb-0.5 text-xs font-bold text-accent">Controladoria</p>}
              <p className="whitespace-pre-wrap">{m.from === "bot" ? <WhatsAppText text={m.text} /> : m.text}</p>
              <p className="mt-1 text-right font-mono text-[10px] text-dim">{m.at}</p>
            </div>
          </div>
        ))}
        {pending && (
          <div className="flex justify-start">
            <div className="rounded-lg bg-panel-3 px-3 py-2 text-sm text-muted">digitando…</div>
          </div>
        )}
        <div ref={endRef} />
      </div>

      <div className="border-t border-line bg-panel p-3">
        <div className="mb-2.5 flex flex-wrap gap-1.5">
          {commands.map((c) => (
            <button key={c} type="button" onClick={() => send(`/${c}`)} disabled={pending} className="btn btn-secondary btn-sm font-mono text-xs">
              /{c}
            </button>
          ))}
        </div>
        <form
          className="flex gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            send(text);
          }}
        >
          <input
            value={text}
            onChange={(e) => setText(e.target.value)}
            maxLength={200}
            placeholder="Digite um comando…"
            className="input"
            aria-label="Mensagem"
          />
          <button type="submit" className="btn btn-primary px-3" disabled={pending || !text.trim()} aria-label="Enviar">
            <Send className="size-4" />
          </button>
        </form>
      </div>
    </div>
  );
}
