# Controladoria do Canal

Central de controle do canal do YouTube do Luiz Jordão, integrada a um bot no grupo do WhatsApp.

Feito em **PHP + MySQL** para rodar direto no `public_html` da Hostinger: cada commit enviado ao GitHub é publicado pelo Git da hospedagem. Link provisório: https://coral-turtle-541464.hostingersite.com

## O que o painel faz

- **Visão geral:** estoque de vídeos programados, capas pendentes, views do mês e destaques
- **Programados:** linha do tempo de 45 dias e lista com filtros
- **Capas:** vídeos sem thumbnail por urgência, com a opção de marcar como feita
- **Desempenho:** views, horas, inscritos, retenção, vídeos em alta e termos de busca
- **Ideias:** sugestões de pauta (Gemini ou exemplos), com fluxo nova → aprovada → gravada
- **Bot do WhatsApp:** simulador de comandos, grupo autorizado, comandos ativos e histórico
- **Alertas:** estoque baixo, capa pendente, resumo semanal e relatório mensal (via Cron Job)
- **Administração:** integrações (Evolution, Gemini), usuários com perfis e auditoria

Comandos do bot: `/programados`, `/semcapa`, `/resumo`, `/virais`, `/ideias`, `/ajuda`.

## Segurança

- **Nenhuma senha no Git:** a configuração fica em `controladoria-config.php`, **fora** do `public_html`.
- **Só a pasta `assets/` é pública.** Todo o resto passa pelo `index.php` (`.htaccess`), e as pastas do sistema têm bloqueio próprio.
- Senhas com **Argon2id** (ou bcrypt). Senha temporária trocada obrigatoriamente no primeiro acesso.
- **Verificação em 2 etapas** (TOTP, qualquer app autenticador), com bloqueio de reuso de código.
- **Sessões no banco**: o cookie guarda só um token aleatório. `HttpOnly`, `SameSite=Lax` e `__Host-`/`Secure` em HTTPS. Expira com 12 h sem uso ou 7 dias.
- **Proteção contra força bruta:** 5 erros por e-mail ou 30 por IP bloqueiam por 15 minutos.
- **CSRF** em todos os formulários, **Content Security Policy**, `X-Frame-Options`, HSTS e consultas SQL sempre com parâmetros.
- **Perfis** (administrador, editor, visualizador) checados no servidor em cada página e ação.
- **Chaves das integrações e segredos do 2FA criptografados** com AES-256-GCM.
- **Auditoria** de logins e alterações.

## Publicar na Hostinger

1. **Git:** o repositório já está ligado ao `public_html` pelo Git da Hostinger. Todo push na `main` atualiza o site.
2. **Banco:** crie um banco MySQL no hPanel (as tabelas são criadas sozinhas no primeiro acesso).
3. **Configuração:** no Gerenciador de Arquivos, crie `controladoria-config.php` **ao lado** da pasta `public_html` (em `domains/SEU-DOMINIO/`), usando o modelo `config.example.php`.
4. **PHP:** use PHP 8.1 ou mais novo (hPanel → Avançado → Configuração do PHP).
5. **Cron Job** (alertas automáticos): hPanel → Avançado → Cron Jobs → tipo PHP, a cada 5 minutos, arquivo `public_html/bin/cron.php`.
6. Abra o site. Confira `https://SEU-DOMINIO/api/health`: `{"ok":true}` significa banco conectado.

## Ligar o WhatsApp

1. Em **Integrações**, cadastre o Evolution (URL, instância e API key).
2. No Evolution, configure o webhook da instância com o evento `MESSAGES_UPSERT` para
   `https://SEU-DOMINIO/api/webhooks/evolution?token=TOKEN` (o `evolution_webhook_token` da configuração).
3. Em **Bot do WhatsApp**, informe o ID do grupo (`...@g.us`) e deixe o bot ligado.

## Rodar no computador

Requer PHP 8.0+ com `pdo_mysql`, `openssl` e `curl`, e um MySQL/MariaDB (ex.: XAMPP).

```bash
cp config.example.php config.local.php   # preencha banco, encryption_key, admin e demo_data => true
php -S localhost:8080 dev-router.php
```

O CSS já vem gerado em `assets/app.css`. Só é preciso Node.js para mudar o visual:

```bash
npm install
npm run build:css
```

## Estrutura

| Pasta | O que tem |
|---|---|
| `index.php`, `.htaccess` | Entrada única e regras de acesso |
| `app/` | Configuração, banco, segurança, regras do canal, bot e controladores |
| `views/` | Layouts e páginas |
| `database/` | Estrutura do banco (aplicada automaticamente) |
| `assets/` | CSS, JavaScript, fontes e ícone (única pasta pública) |
| `bin/cron.php` | Alertas automáticos (Cron Job) |
| `resources/app.css` | Fonte do CSS (Tailwind) |

## Próximas etapas

- [ ] Conexão com o YouTube: OAuth do dono do canal (somente leitura) e sincronização de vídeos e métricas
- [ ] Detecção automática de capa personalizada
- [ ] Leitura de comentários para alimentar as ideias
- [ ] Backup automático do banco
