<?php

// MODELO de configuração. NUNCA coloque senhas neste arquivo (ele vai para o Git).
//
// Copie para FORA da pasta pública com o nome controladoria-config.php.
// Na Hostinger: domains/SEU-DOMINIO/controladoria-config.php (ao lado da pasta public_html).
// Em desenvolvimento, pode ficar na raiz do projeto como config.local.php.

return [
    // Endereço público do painel, sem barra no final
    'app_url' => 'https://seu-dominio.com.br',

    // true só em desenvolvimento: mostra detalhes de erros na tela
    'debug' => false,

    // Banco MySQL (na Hostinger o host é localhost)
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => '',
        'user' => '',
        'pass' => '',
    ],

    // Chave AES-256 em base64 (32 bytes). Protege os segredos do 2FA e das integrações.
    // Gere com: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    // Guarde em lugar seguro e NUNCA troque depois de em uso.
    'encryption_key' => '',

    // Primeiro administrador: criado só quando não existe nenhum usuário.
    // Senha com 10+ caracteres, letras e números. Será trocada no primeiro acesso.
    'admin' => [
        'name' => 'Marcos',
        'email' => '',
        'password' => '',
    ],

    // Dados de exemplo enquanto o YouTube não está conectado
    'demo_data' => false,

    // Token que o Evolution envia na URL do webhook (?token=...)
    'evolution_webhook_token' => '',

    // Token para acionar os alertas por URL (/api/cron?token=...), se não usar o Cron em PHP
    'cron_token' => '',

    // Pasta das capas enviadas. Vazio = controladoria-arquivos, ao lado da pasta public_html
    'storage_path' => '',

    // true só se o Evolution estiver de propósito numa rede interna (por padrão, endereços internos são bloqueados)
    'allow_private_hosts' => false,

    // Opcional: o Evolution e o Gemini também podem ser cadastrados pelo painel (Integrações)
    'evolution' => ['url' => '', 'instance' => '', 'api_key' => ''],
    'gemini' => ['api_key' => '', 'model' => 'gemini-2.5-flash'],
];
