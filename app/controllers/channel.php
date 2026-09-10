<?php

defined('CONTROLADORIA') || exit;

function page_overview(): void
{
    $user = require_user();
    $alerts = get_setting('alertas');
    $scheduled = scheduled_videos();
    render_page('overview', 'Visão geral', [
        'user' => $user,
        'accessDenied' => query_string('acesso') === 'negado',
        'scheduled' => $scheduled,
        'stock' => summarize_stock($scheduled),
        'alerts' => $alerts,
        'mtd' => month_to_date(),
        'series' => daily_series(30),
        'approved' => ideas_list(['aprovada']),
        'messages' => recent_bot_messages(6),
        'virals' => viral_videos((float) $alerts['viralMultiplicador']),
    ]);
}

const SCHEDULED_FILTERS = ['todos' => 'Todos', 'sem-capa' => 'Sem capa', 'longos' => 'Vídeos', 'shorts' => 'Shorts'];

function page_scheduled(): void
{
    $user = require_user();
    $filter = array_key_exists(query_string('filtro'), SCHEDULED_FILTERS) ? query_string('filtro') : 'todos';
    $videos = scheduled_videos();
    $filtered = array_values(array_filter($videos, static function ($video) use ($filter) {
        switch ($filter) {
            case 'sem-capa':
                return $video['thumbnail_status'] !== 'ok';
            case 'longos':
                return !$video['is_short'];
            case 'shorts':
                return $video['is_short'];
            default:
                return true;
        }
    }));
    render_page('scheduled', 'Programados', [
        'videos' => $videos,
        'filtered' => $filtered,
        'filter' => $filter,
        'stock' => summarize_stock($videos),
        'alerts' => get_setting('alertas'),
        'canManage' => can($user['role'], 'operar'),
    ]);
}

function page_thumbnails(): void
{
    $user = require_user();
    $alerts = get_setting('alertas');
    $videos = scheduled_videos();
    $pending = array_filter($videos, static fn ($video) => $video['thumbnail_status'] !== 'ok');
    $warnDays = (int) $alerts['capaAvisoDias'];
    $groups = array_values(array_filter([
        ['title' => 'Urgente · publica em até ' . plural($warnDays, 'dia', 'dias'), 'tone' => 'danger', 'items' => array_filter($pending, static fn ($v) => days_until($v['publish_at']) <= $warnDays)],
        ['title' => 'Nesta semana', 'tone' => 'warn', 'items' => array_filter($pending, static fn ($v) => days_until($v['publish_at']) > $warnDays && days_until($v['publish_at']) <= 7)],
        ['title' => 'Mais para frente', 'tone' => 'neutral', 'items' => array_filter($pending, static fn ($v) => days_until($v['publish_at']) > max(7, $warnDays))],
    ], static fn ($group) => count($group['items']) > 0));

    render_page('thumbnails', 'Capas', [
        'groups' => $groups,
        'pendingCount' => count($pending),
        'done' => array_filter($videos, static fn ($video) => $video['thumbnail_status'] === 'ok'),
        'canOperate' => can($user['role'], 'operar'),
    ]);
}

function action_thumbnail_status(): void
{
    $user = require_permission('operar');
    $videoId = post_string('videoId', 64);
    $status = post_string('status', 10);
    if (!in_array($status, ['ok', 'pendente'], true)) {
        redirect('/capas');
    }
    $video = db_one("SELECT title FROM videos WHERE id = ? AND status = 'agendado'", [$videoId]);
    if ($video !== null) {
        $now = to_db(utc_now());
        db_exec(
            "UPDATE videos SET thumbnail_status = ?, thumbnail_source = 'manual', thumbnail_updated_by = ?, thumbnail_updated_at = ?, updated_at = ? WHERE id = ?",
            [$status, $user['id'], $now, $now, $videoId]
        );
        log_audit($user['id'], 'capa.status', ['video' => $video['title'], 'status' => $status]);
        flash('ok', $status === 'ok' ? 'Capa marcada como feita.' : 'Capa voltou para pendente.');
    }
    redirect('/capas');
}

function page_performance(): void
{
    require_user();
    $current = month_key();
    $requested = query_string('mes');
    $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested) && $requested <= $current ? $requested : $current;
    $alerts = get_setting('alertas');
    // O card mostra barras de views: ordena por views, com o crescimento como detalhe.
    $terms = rising_terms($month, 30);
    usort($terms, static fn ($a, $b) => $b['views'] <=> $a['views']);
    render_page('performance', 'Desempenho', [
        'month' => $month,
        'current' => $current,
        'totals' => month_totals($month),
        'previous' => month_totals(shift_month($month, -1)),
        'series' => daily_series(null, $month),
        'top' => top_videos($month, 8),
        'retention' => month_retention($month),
        'rising' => array_slice($terms, 0, 8),
        'alerts' => $alerts,
        'virals' => viral_videos((float) $alerts['viralMultiplicador']),
    ]);
}

const IDEA_TABS = ['nova' => 'Novas', 'aprovada' => 'Aprovadas', 'gravada' => 'Gravadas', 'descartada' => 'Descartadas', 'todas' => 'Todas'];

function page_ideas(): void
{
    $user = require_user();
    $tab = array_key_exists(query_string('status'), IDEA_TABS) ? query_string('status') : 'nova';
    render_page('ideas', 'Ideias', [
        'tab' => $tab,
        'ideas' => ideas_list($tab === 'todas' ? null : [$tab]),
        'counts' => idea_counts(),
        'rising' => rising_terms(month_key(), 8),
        'geminiOn' => gemini_config() !== null,
        'canOperate' => can($user['role'], 'operar'),
        'canDelete' => can($user['role'], 'excluir'),
    ]);
}

const IDEAS_GENERATIONS_PER_HOUR = 10;

function action_ideas_generate(): void
{
    $user = require_permission('operar');
    $recent = (int) db_value("SELECT COUNT(*) FROM audit_logs WHERE action = 'ideia.gerada' AND created_at > ?", [to_db(utc_now()->modify('-1 hour'))]);
    if ($recent >= IDEAS_GENERATIONS_PER_HOUR) {
        flash('erro', 'Limite de ' . IDEAS_GENERATIONS_PER_HOUR . ' gerações por hora atingido. Tente de novo mais tarde.');
        redirect('/ideias');
    }
    try {
        $result = generate_ideas();
        log_audit($user['id'], 'ideia.gerada', ['quantidade' => $result['created'], 'origem' => $result['generated_by']]);
        if ($result['created'] === 0) {
            flash('erro', 'Não surgiram ideias novas com os dados atuais. Tente de novo mais tarde.');
        } elseif ($result['generated_by'] === 'ia') {
            flash('ok', plural($result['created'], 'ideia nova gerada', 'ideias novas geradas') . ' pelo Gemini.');
        } else {
            flash('ok', plural($result['created'], 'ideia de exemplo criada', 'ideias de exemplo criadas') . ' a partir das buscas. Cadastre a chave do Gemini para usar IA.');
        }
    } catch (UserFacingException $error) {
        flash('erro', $error->getMessage());
    } catch (Throwable $error) {
        error_log('[controladoria] ideias: ' . $error->getMessage());
        flash('erro', 'Não foi possível gerar ideias agora. Tente de novo mais tarde.');
    }
    redirect('/ideias');
}

function action_idea_status(): void
{
    $user = require_permission('operar');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $status = post_string('status', 12);
    if ($id !== false && in_array($status, IDEA_STATUSES, true)) {
        $idea = db_one('SELECT title FROM ideas WHERE id = ?', [$id]);
        if ($idea !== null) {
            db_exec('UPDATE ideas SET status = ?, updated_by = ?, updated_at = ? WHERE id = ?', [$status, $user['id'], to_db(utc_now()), $id]);
            log_audit($user['id'], 'ideia.status', ['ideia' => $idea['title'], 'status' => $status]);
        }
    }
    back_to('/ideias');
}

function action_idea_create(): void
{
    $user = require_permission('operar');
    $title = post_string('title', 200);
    $rationale = post_string('rationale', 1000);
    if (mb_strlen($title) < 5) {
        flash('erro', 'O título precisa ter pelo menos 5 caracteres.');
    } elseif (mb_strlen($rationale) < 5) {
        flash('erro', 'Explique em uma frase por que a ideia é boa.');
    } else {
        $now = to_db(utc_now());
        db_insert('ideas', [
            'title' => $title, 'rationale' => $rationale, 'source' => 'manual', 'score' => 60, 'status' => 'nova',
            'generated_by' => 'usuario', 'updated_by' => $user['id'], 'created_at' => $now, 'updated_at' => $now,
        ]);
        log_audit($user['id'], 'ideia.criada', ['ideia' => $title]);
        flash('ok', 'Ideia cadastrada.');
    }
    redirect('/ideias');
}
