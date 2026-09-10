<?php

defined('CONTROLADORIA') || exit;

function page_videos(): void
{
    $user = require_user();
    $status = array_key_exists(query_string('status'), VIDEO_STATUSES) ? query_string('status') : '';
    $search = query_string('q');
    render_page('videos', 'Vídeos', [
        'videos' => list_videos($status, $search),
        'status' => $status,
        'search' => $search,
        'counts' => video_status_counts(),
        'canManage' => can($user['role'], 'operar'),
        'canDelete' => can($user['role'], 'excluir'),
    ]);
}

function render_video_form(array $form, ?string $error = null): void
{
    $user = require_permission('operar');
    render_page('video-form', $form['id'] === '' ? 'Novo vídeo' : 'Editar vídeo', [
        'form' => $form,
        'error' => $error,
        'thumbnails' => thumbnail_options(),
        'canDelete' => can($user['role'], 'excluir'),
    ]);
}

function page_video_new(): void
{
    require_permission('operar');
    $form = empty_video_form();
    $ideaId = filter_var(query_string('ideia'), FILTER_VALIDATE_INT);
    if ($ideaId !== false) {
        $idea = db_one('SELECT title, rationale FROM ideas WHERE id = ?', [$ideaId]);
        if ($idea !== null) {
            $form['title'] = mb_substr($idea['title'], 0, 255);
            $form['status'] = 'rascunho';
            $form['notes'] = mb_substr('Ideia de pauta: ' . $idea['rationale'], 0, 2000);
        }
    }
    render_video_form($form);
}

function page_video_edit(): void
{
    require_permission('operar');
    $video = find_video(query_string('id'));
    if ($video === null) {
        abort(404);
    }
    render_video_form(video_to_form($video));
}

function action_video_save(): void
{
    $user = require_permission('operar');
    $form = video_form_from_post();
    $existing = $form['id'] !== '' ? find_video($form['id']) : null;
    if ($form['id'] !== '' && $existing === null) {
        abort(404);
    }

    [$data, $error] = validate_video_form($form);
    if ($error !== null) {
        http_response_code(422);
        render_video_form($form, $error);
    }

    $now = to_db(utc_now());
    if ($existing === null) {
        $id = 'm-' . bin2hex(random_bytes(8));
        db_insert('videos', $data + [
            'id' => $id,
            'source' => 'manual',
            'thumbnail_source' => 'manual',
            'thumbnail_updated_by' => $user['id'],
            'thumbnail_updated_at' => $now,
            'created_by' => $user['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        log_audit($user['id'], 'video.criado', ['video' => $data['title'], 'status' => $data['status']]);
        flash('ok', 'Vídeo cadastrado.');
    } else {
        $id = $existing['id'];
        $changes = $data + ['updated_at' => $now];
        if ($existing['thumbnail_status'] !== $data['thumbnail_status'] || $existing['thumbnail_id'] !== $data['thumbnail_id']) {
            $changes += ['thumbnail_source' => 'manual', 'thumbnail_updated_by' => $user['id'], 'thumbnail_updated_at' => $now];
        }
        db_update('videos', $changes, 'id = ?', [$id]);
        log_audit($user['id'], 'video.editado', ['video' => $data['title'], 'status' => $data['status']]);
        flash('ok', 'Vídeo atualizado.');
    }

    if ($data['thumbnail_id'] !== null) {
        mark_thumbnail_in_use((int) $data['thumbnail_id'], $id);
    }
    redirect('/videos');
}

function action_video_delete(): void
{
    $user = require_permission('excluir');
    $video = find_video(post_string('id', 64));
    if ($video !== null) {
        db_exec('DELETE FROM videos WHERE id = ?', [$video['id']]);
        log_audit($user['id'], 'video.excluido', ['video' => $video['title']]);
        flash('ok', 'Vídeo excluído.');
    }
    redirect('/videos');
}

/* ---------------- Edição e exclusão de ideias ---------------- */

function find_idea($id): ?array
{
    $id = filter_var($id, FILTER_VALIDATE_INT);
    return $id !== false ? db_one('SELECT id, title, rationale, source, score, status FROM ideas WHERE id = ?', [$id]) : null;
}

function page_idea_edit(): void
{
    $user = require_permission('operar');
    $idea = find_idea(query_string('id'));
    if ($idea === null) {
        abort(404);
    }
    render_page('idea-form', 'Editar ideia', ['idea' => $idea, 'error' => null, 'canDelete' => can($user['role'], 'excluir')]);
}

function action_idea_save(): void
{
    $user = require_permission('operar');
    $idea = find_idea($_POST['id'] ?? null);
    if ($idea === null) {
        abort(404);
    }
    $form = [
        'id' => $idea['id'],
        'title' => post_string('title', 200),
        'rationale' => post_string('rationale', 1000),
        'source' => post_string('source', 15),
        'status' => post_string('status', 12),
        'score' => post_string('score', 3),
    ];
    $score = filter_var($form['score'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
    $error = null;
    if (mb_strlen($form['title']) < 5) {
        $error = 'O título precisa ter pelo menos 5 caracteres.';
    } elseif (mb_strlen($form['rationale']) < 5) {
        $error = 'Explique em uma frase por que a ideia é boa.';
    } elseif (!isset(IDEA_SOURCES[$form['source']])) {
        $error = 'Escolha a origem da ideia.';
    } elseif (!in_array($form['status'], IDEA_STATUSES, true)) {
        $error = 'Escolha um status válido.';
    } elseif ($score === false) {
        $error = 'O potencial vai de 0 a 100.';
    }
    if ($error !== null) {
        http_response_code(422);
        render_page('idea-form', 'Editar ideia', ['idea' => $form, 'error' => $error, 'canDelete' => can($user['role'], 'excluir')]);
    }

    db_update('ideas', [
        'title' => $form['title'],
        'rationale' => $form['rationale'],
        'source' => $form['source'],
        'status' => $form['status'],
        'score' => $score,
        'updated_by' => $user['id'],
        'updated_at' => to_db(utc_now()),
    ], 'id = ?', [$idea['id']]);
    log_audit($user['id'], 'ideia.editada', ['ideia' => $form['title']]);
    flash('ok', 'Ideia atualizada.');
    redirect('/ideias?status=' . $form['status']);
}

function action_idea_delete(): void
{
    $user = require_permission('excluir');
    $idea = find_idea($_POST['id'] ?? null);
    if ($idea !== null) {
        db_exec('DELETE FROM ideas WHERE id = ?', [$idea['id']]);
        log_audit($user['id'], 'ideia.excluida', ['ideia' => $idea['title']]);
        flash('ok', 'Ideia excluída.');
    }
    back_to('/ideias');
}
