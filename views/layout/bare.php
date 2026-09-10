<?php defined('CONTROLADORIA') || exit; ?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= csp_meta_tag() ?>
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · Controladoria</title>
    <link rel="icon" href="<?= e(asset('icon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body class="min-h-screen">
<?= $content ?>
</body>
</html>
