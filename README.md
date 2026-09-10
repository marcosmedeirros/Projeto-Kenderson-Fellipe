# Controladoria do Canal

Central de controle do canal do YouTube do Luiz Jordão, integrada a um bot no grupo do WhatsApp.

Feito em **PHP + MySQL** para rodar direto no `public_html` da Hostinger: cada commit enviado ao GitHub é publicado pelo Git da hospedagem. Link provisório: https://coral-turtle-541464.hostingersite.com

## O que o painel faz

- **Visão geral:** estoque de vídeos programados, capas pendentes, views do mês e destaques
- **Vídeos:** cadastro, edição e exclusão de vídeos (rascunho, agendado, publicado, privado), com busca e filtros
- **Programados:** linha do tempo de 45 dias e lista com filtros, atalhos para editar, enviar capa e abrir no YouTube Studio
- **Capas:** pendentes por urgência e **biblioteca de capas** (enviar, aprovar, editar, baixar, usar num vídeo, excluir)
- **Desempenho:** views, horas, inscritos, retenção, vídeos em alta e termos de busca
- **Ideias:** sugestões de pauta (Gemini ou exemplos), cadastro, edição, exclusão e "virar vídeo"
- **Bot do WhatsApp:** simulador de comandos, grupo autorizado, comandos ativos e histórico
- **Alertas:** estoque baixo, capa pendente, resumo semanal e relatório mensal (via Cron Job)
- **Administração:** integrações (Evolution, Gemini), usuários com perfis e auditoria

Todas as telas funcionam no celular (listas viram cartões, tabelas só no computador).

Comandos do bot: `/programados`, `/semcapa`, `/resumo`, `/virais`, `/ideias`, `/ajuda`.

## Perfis

| Perfil | Pode |
|---|---|
| Administrador | Tudo: excluir vídeos, capas e ideias, integrações, usuários e auditoria |
| Editor | Cadastrar e editar vídeos, capas e ideias; testar o bot; configurar bot e alertas |
| Visualizador | Só consultar |

As permissões são checadas no servidor em cada página e em cada ação, não só escondendo botões.

## Segurança

- **Nenhuma senha no Git:** a configuração fica em `controladoria-config.php`, **fora** do `public_html`.
- **Só a pasta `assets/` é pública.** Todo o resto passa pelo `index.php` (`.htaccess`), e as pastas do sistema têm bloqueio próprio.
- **Capas enviadas ficam fora do `public_html`** (`controladoria-arquivos/`, ao lado dele) e só são entregues para quem está logado. Cada imagem é validada (JPG, PNG ou WebP, até 2 MB) e regravada, o que descarta qualquer conteúdo escondido.
- Senhas com **Argon2id** (ou bcrypt). Senha temporária vale 72 horas e precisa ser trocada no primeiro acesso.
- **Verificação em 2 etapas** (TOTP, qualquer app autenticador), com bloqueio de reuso de código.
- **Sessões no banco**: o cookie guarda só um token aleatório. `HttpOnly`, `SameSite=Lax` e `__Host-`/`Secure` em HTTPS. Expira com 12 h sem uso ou 7 dias.
- **Força bruta:** 5 erros do mesmo e-mail no mesmo aparelho, 30 por IP ou 50 por e-mail bloqueiam por 15 minutos. O tempo de resposta é igual para e-mails que existem ou não.
- **Confirmações sensíveis** (trocar senha, desligar 2 etapas): 5 erros seguidos encerram todas as sessões da conta.
- **CSRF** em todos os formulários (token + origem), **Content Security Policy**, `X-Frame-Options`, HSTS e consultas SQL sempre com parâmetros.
- **Integrações:** chaves e segredos do 2FA criptografados com AES-256-GCM; chamadas externas só para endereços públicos (bloqueia acesso à rede interna).
- **Auditoria** de logins e alterações, guardada por 365 dias. Erros internos nunca aparecem na tela.

## Publicar na Hostinger

1. **Git:** o repositório já está ligado ao `public_html` pelo Git da Hostinger. Todo push na `main` atualiza o site.
2. **Banco:** crie um banco MySQL no hPanel (as tabelas são criadas e atualizadas sozinhas no primeiro acesso).
3. **Configuração:** no Gerenciador de Arquivos, crie `controladoria-config.php` **ao lado** da pasta `public_html` (em `domains/SEU-DOMINIO/`), usando o modelo `config.example.php`.
4. **PHP:** use PHP 8.1 ou mais novo (hPanel → Avançado → Configuração do PHP), com a extensão **GD** ativa (para as capas).
5. **Cron Job** (alertas automáticos e limpeza diária): hPanel → Avançado → Cron Jobs → tipo PHP, a cada 5 minutos, arquivo `public_html/bin/cron.php`.
   Alternativa: `POST https://SEU-DOMINIO/api/cron` com o cabeçalho `X-Cron-Token: <cron_token>` (o token nunca vai na URL).
6. Abra o site. Confira `https://SEU-DOMINIO/api/health`: `{"ok":true}` significa banco conectado.

### Trocar chaves e tokens

- `evolution_webhook_token` e `cron_token`: gere novos valores, salve na configuração e atualize o webhook no Evolution.
- Senha do banco: troque no hPanel e depois na configuração.
- `encryption_key`: **não troque** depois de salvar integrações ou ativar o 2FA (os dados criptografados deixariam de abrir). Se precisar, remova as integrações e desative o 2FA antes.

## Ligar o WhatsApp

1. Em **Integrações**, cadastre o Evolution (URL, instância e API key).
2. No Evolution, configure o webhook da instância com o evento `MESSAGES_UPSERT` para
   `https://SEU-DOMINIO/api/webhooks/evolution?token=TOKEN` (o `evolution_webhook_token` da configuração).
3. Em **Bot do WhatsApp**, informe o ID do grupo (`...@g.us`) e deixe o bot ligado.

## Rodar no computador

Requer PHP 8.0+ com `pdo_mysql`, `openssl`, `curl` e `gd`, e um MySQL/MariaDB (ex.: XAMPP).

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
| `app/` | Configuração, banco, segurança, regras do canal, vídeos, capas, bot e controladores |
| `views/` | Layouts e páginas |
| `database/` | Estrutura do banco (aplicada automaticamente, em ordem) |
| `assets/` | CSS, JavaScript, fontes e ícone (única pasta pública) |
| `bin/cron.php` | Alertas automáticos e limpeza (Cron Job) |
| `resources/app.css` | Fonte do CSS (Tailwind) |

## Próximas etapas

- [ ] Conexão com o YouTube: OAuth do dono do canal (somente leitura) e sincronização de vídeos e métricas
- [ ] Detecção automática de capa personalizada
- [ ] Leitura de comentários para alimentar as ideias
- [ ] Backup automático do banco
