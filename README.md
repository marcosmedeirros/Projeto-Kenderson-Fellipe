# Controladoria do Canal

Central de controle do canal do YouTube do Luiz Jordão, integrada a um bot no grupo do WhatsApp.

Aplicação Next.js 16 com banco MySQL. Link provisório de teste: https://coral-turtle-541464.hostingersite.com

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

- Senhas com **scrypt** (parâmetros da OWASP). Senha temporária obrigatoriamente trocada no primeiro acesso.
- **Verificação em 2 etapas** (TOTP, qualquer app autenticador), com bloqueio de reuso de código.
- **Sessões no banco**: o cookie guarda só um token aleatório (o banco guarda o hash). Cookie `HttpOnly`, `SameSite=Lax` e `__Host-`/`Secure` em produção. Expira com 12 h sem uso ou 7 dias no total.
- **Proteção contra força bruta:** 5 erros por e-mail ou 30 por IP bloqueiam por 15 minutos, sem revelar se o e-mail existe.
- **Perfis:** administrador, editor e visualizador, checados no servidor em cada página e ação.
- **Chaves das integrações e segredos do 2FA criptografados** com AES-256-GCM.
- **Content Security Policy com nonce**, `X-Frame-Options`, `nosniff`, HSTS e `Referrer-Policy`.
- **Webhook do WhatsApp** exige token, só responde ao grupo autorizado e tem limite por minuto.
- **Auditoria** de logins e alterações. Senhas e chaves nunca são registradas.

## Publicar na Hostinger (hospedagem Node.js)

1. **Banco:** crie um banco MySQL no hPanel e anote nome, usuário e senha.
2. **App Node.js:** adicione um app a partir deste repositório do GitHub (ou envie o ZIP do projeto, sem `node_modules`).
3. **Configurações de build:**
   - Versão do Node: **22**
   - Comando de build: `npm run build`
   - Arquivo de entrada (Entry file): `server.js`
   - Diretório de saída: `.next`
4. **Variáveis de ambiente:** cadastre as do `.env.example` (`APP_URL`, `DB_HOST=localhost`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `ENCRYPTION_KEY`, `ADMIN_*`, `EVOLUTION_WEBHOOK_TOKEN`, `DEMO_DATA`).
5. **Publique.** Na primeira inicialização o painel cria as tabelas e o administrador sozinho.
6. Confira `https://SEU-DOMINIO/api/health`: `{"ok":true}` significa banco conectado. Se der erro, veja os **Runtime Logs** do app.

O link precisa ser **HTTPS**: em produção o cookie de login só funciona com conexão segura.

## Rodar no computador

Requer Node.js 20.9+ e um MySQL ou MariaDB local (ex.: o do XAMPP).

```bash
cp .env.example .env.local   # preencha DB_*, ENCRYPTION_KEY, ADMIN_* e DEMO_DATA=true
npm install
npm run dev
```

Abra http://localhost:3000. Depois de alterar `src/db/schema.ts`, gere a migração com `npm run db:generate`. As migrações são aplicadas sozinhas quando o servidor inicia.

## Colocar numa VPS (opcional)

A pasta [`infra/`](infra) tem Docker Compose com MariaDB, Caddy (HTTPS automático) e Evolution opcional:

```bash
cd infra
cp .env.example .env   # preencha
docker compose up -d --build
```

## Ligar o WhatsApp

1. Em **Integrações**, cadastre o Evolution (URL, instância e API key).
2. No Evolution, configure o webhook da instância com o evento `MESSAGES_UPSERT` apontando para
   `https://SEU-DOMINIO/api/webhooks/evolution?token=EVOLUTION_WEBHOOK_TOKEN`.
3. Em **Bot do WhatsApp**, informe o ID do grupo (`...@g.us`) e deixe o bot ligado.

## Próximas etapas

- [ ] Conexão com o YouTube: OAuth do dono do canal (somente leitura) e sincronização de vídeos, agendamentos e métricas
- [ ] Detecção automática de capa personalizada (validar no teste técnico)
- [ ] Leitura de comentários para alimentar as ideias
- [ ] Backup automático do banco
