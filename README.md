# Controladoria do Canal

Central de controle do canal do YouTube do Luiz Jordão, integrada a um bot no grupo do WhatsApp.

| Pasta | O que tem |
|---|---|
| [`painel/`](painel) | Aplicação web (Next.js 16): painel, login, bot e automações |
| [`infra/`](infra) | Docker Compose, Caddy (HTTPS) e variáveis para a VM da Oracle |

## O que o painel faz

- **Visão geral:** estoque de vídeos programados, capas pendentes, views do mês e destaques
- **Programados:** linha do tempo de 45 dias e lista com filtros
- **Capas:** vídeos sem thumbnail por urgência, com a opção de marcar como feita
- **Desempenho:** views, horas, inscritos, retenção, vídeos em alta e termos de busca
- **Ideias:** sugestões de pauta (Gemini ou exemplos), com fluxo nova → aprovada → gravada
- **Bot do WhatsApp:** simulador de comandos, grupo autorizado, comandos ativos e histórico
- **Alertas:** estoque baixo, capa pendente, resumo semanal e relatório mensal automáticos
- **Administração:** integrações (Evolution, Gemini), usuários com perfis e auditoria

Comandos do bot: `/programados`, `/semcapa`, `/resumo`, `/virais`, `/ideias`, `/ajuda`.

## Segurança

- Senhas com **Argon2id**. Senha temporária obrigatoriamente trocada no primeiro acesso.
- **Verificação em 2 etapas** (TOTP, qualquer app autenticador), com bloqueio de reuso de código.
- **Sessões no banco**: o cookie guarda só um token aleatório (o banco guarda o hash). Cookie `HttpOnly`, `SameSite=Lax` e `__Host-`/`Secure` em produção. Expira com 12 h sem uso ou 7 dias no total. Dá para ver e encerrar os aparelhos conectados.
- **Proteção contra força bruta:** 5 erros por e-mail ou 30 por IP bloqueiam por 15 minutos. A resposta não revela se o e-mail existe.
- **Perfis:** administrador, editor e visualizador. A permissão é checada no servidor em cada página e em cada ação, não só no menu.
- **Chaves das integrações e segredos do 2FA criptografados** no banco com AES-256-GCM.
- **Content Security Policy com nonce** por requisição, além de `X-Frame-Options`, `nosniff`, HSTS e `Referrer-Policy`.
- **Webhook do WhatsApp** exige token, só responde ao grupo autorizado e tem limite de comandos por minuto.
- **Auditoria** de logins, tentativas recusadas e alterações. Senhas e chaves nunca são registradas.

## Rodar no computador

Requer Node.js 20.9+. Não precisa de Docker: sem `DATABASE_URL`, o painel usa um Postgres embutido (PGlite) em `painel/.data/`.

```bash
cd painel
cp .env.example .env.local   # preencha ENCRYPTION_KEY, ADMIN_EMAIL, ADMIN_PASSWORD e DEMO_DATA=true
npm install
npm run dev
```

Abra http://localhost:3000. O primeiro administrador é criado com os dados do `.env.local` e precisa trocar a senha no primeiro acesso.

Depois de alterar `src/db/schema.ts`, gere a migração com `npm run db:generate`. As migrações são aplicadas sozinhas quando o servidor inicia.

## Colocar na Oracle

1. Na VM (Ubuntu ARM), instale Docker e o plugin Compose.
2. Libere as portas 80 e 443 na Security List da VCN e no firewall da VM (`iptables`/`ufw`).
3. Aponte o DNS do domínio do painel para o IP público da VM.
4. Copie o projeto, crie `infra/.env` a partir de `infra/.env.example` e preencha tudo.
5. Suba:

   ```bash
   cd infra
   docker compose up -d --build
   ```

   Para subir também um Evolution novo na mesma VM: `docker compose --profile evolution up -d --build`.
6. Entre no painel e cadastre o Evolution em **Integrações** (URL, instância e API key).
7. No Evolution, configure o webhook da instância com o evento `MESSAGES_UPSERT` apontando para:
   `https://SEU-DOMINIO/api/webhooks/evolution?token=EVOLUTION_WEBHOOK_TOKEN`
   (se o Evolution estiver na mesma rede do Docker, pode usar `http://painel:3000/...`).
8. Em **Bot do WhatsApp**, informe o ID do grupo (`...@g.us`) e deixe o bot ligado.

**Backups:** faça `pg_dump` diário do Postgres e guarde a `ENCRYPTION_KEY` fora do servidor.

## Próximas etapas

- [ ] Conexão com o YouTube: OAuth do dono do canal (somente leitura) e sincronização de vídeos, agendamentos e métricas (Data API v3 e Analytics API)
- [ ] Detecção automática de capa personalizada (validar no teste técnico)
- [ ] Leitura de comentários para alimentar as ideias
- [ ] Backup automático do banco
