<?php
defined('CONTROLADORIA') || exit;

$titles = [
    404 => ['Página não encontrada', 'O endereço que você abriu não existe no painel.'],
    405 => ['Ação não permitida', 'Essa página não aceita esse tipo de acesso.'],
    419 => ['Formulário expirado', 'Por segurança, o formulário expirou. Recarregue a página e tente de novo.'],
    429 => ['Muitas tentativas', 'Aguarde alguns minutos e tente de novo.'],
    500 => ['Algo deu errado', 'Não foi possível carregar esta página. Tente de novo; se continuar, avise o administrador.'],
];
[$heading, $text] = $titles[$status] ?? $titles[500];
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= csp_meta_tag() ?>
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($heading) ?> · Controladoria</title>
    <link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="flex min-h-screen items-center justify-center px-5">
<div class="card w-full max-w-lg p-8 text-center">
    <span class="mx-auto grid size-11 place-items-center rounded-xl <?= $status >= 500 ? 'bg-danger/12 text-danger' : 'bg-panel-2 text-muted' ?>">
        <?= icon($status === 404 ? 'compass' : 'triangle-alert', 'size-5') ?>
    </span>
    <p class="eyebrow mt-4">Erro <?= (int) $status ?></p>
    <h1 class="display mt-2 text-3xl font-extrabold"><?= e($heading) ?></h1>
    <p class="mt-2 text-sm text-muted"><?= e($message !== '' ? $message : $text) ?></p>
    <a href="/" class="btn btn-primary mt-6">Ir para o painel</a>
</div>
</body>
</html>
