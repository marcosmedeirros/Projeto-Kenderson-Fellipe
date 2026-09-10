<?php

defined('CONTROLADORIA') || exit;

const AUDIT_LABELS = [
    'login.sucesso' => 'Entrou no painel',
    'login.falha' => 'Tentativa de login recusada',
    'login.bloqueado' => 'Login bloqueado por excesso de tentativas',
    'login.2fa_falha' => 'Código de verificação incorreto',
    'login.senha_expirada' => 'Tentou entrar com senha temporária vencida',
    'conta.bloqueio_sensivel' => 'Sessões encerradas por erros seguidos de senha ou código',
    'logout' => 'Saiu do painel',
    'senha.alterada' => 'Alterou a própria senha',
    '2fa.ativado' => 'Ativou a verificação em 2 etapas',
    '2fa.desativado' => 'Desativou a verificação em 2 etapas',
    'sessao.encerrada' => 'Encerrou uma sessão',
    'usuario.criado' => 'Criou um usuário',
    'usuario.perfil' => 'Alterou o perfil de um usuário',
    'usuario.status' => 'Ativou ou desativou um usuário',
    'usuario.senha_redefinida' => 'Redefiniu a senha de um usuário',
    'capa.status' => 'Alterou o status de uma capa',
    'capa.enviada' => 'Enviou uma capa para a biblioteca',
    'capa.editada' => 'Editou uma capa da biblioteca',
    'capa.usada' => 'Definiu a capa de um vídeo',
    'capa.excluida' => 'Excluiu uma capa da biblioteca',
    'video.criado' => 'Cadastrou um vídeo',
    'video.editado' => 'Editou um vídeo',
    'video.excluido' => 'Excluiu um vídeo',
    'ideia.editada' => 'Editou uma ideia',
    'ideia.excluida' => 'Excluiu uma ideia',
    'ideia.status' => 'Alterou o status de uma ideia',
    'ideia.gerada' => 'Gerou novas ideias',
    'ideia.criada' => 'Cadastrou uma ideia',
    'config.alertas' => 'Alterou os alertas automáticos',
    'config.bot' => 'Alterou as configurações do bot',
    'integracao.salva' => 'Alterou uma integração',
    'integracao.removida' => 'Removeu uma integração',
];

function log_audit(?string $userId, string $action, ?array $detail = null): void
{
    db_insert('audit_logs', [
        'user_id' => $userId,
        'action' => $action,
        'detail' => $detail !== null ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
        'ip' => client_ip(),
        'created_at' => to_db(utc_now()),
    ]);
}
