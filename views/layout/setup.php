<?php defined('CONTROLADORIA') || exit; ?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= csp_meta_tag() ?>
    <meta name="robots" content="noindex, nofollow">
    <title>Configuração pendente · Controladoria</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="flex min-h-screen items-center justify-center px-5 py-12">
<div class="card w-full max-w-xl p-8">
    <div class="flex items-center gap-3"><?= brand_mark('size-9') ?><span class="display text-xl font-extrabold">Controladoria</span></div>
    <h1 class="display mt-6 text-3xl font-extrabold">Falta a configuração</h1>
    <p class="mt-2 text-sm text-muted">O painel foi publicado, mas ainda não encontrou o arquivo com os dados do banco e as chaves de segurança.</p>
    <ol class="mt-5 list-decimal space-y-2 pl-5 text-sm">
        <li>Copie o conteúdo de <code class="font-mono text-accent">config.example.php</code> do repositório.</li>
        <li>Crie o arquivo <code class="font-mono text-accent">controladoria-config.php</code> <strong>fora</strong> da pasta pública, ao lado da pasta <code class="font-mono">public_html</code>.</li>
        <li>Preencha banco, chave de criptografia e administrador, e recarregue esta página.</li>
    </ol>
    <p class="mt-5 text-xs text-dim">Por segurança, esse arquivo nunca deve ir para o Git.</p>
</div>
</body>
</html>
