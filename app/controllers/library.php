<?php

defined('CONTROLADORIA') || exit;

function page_library(): void
{
    $user = require_user();
    $status = array_key_exists(query_string('status'), THUMBNAIL_STATUSES) ? query_string('status') : '';
    render_page('library', 'Biblioteca de capas', [
        'thumbnails' => list_thumbnails($status),
        'status' => $status,
        'counts' => thumbnail_status_counts(),
        'videos' => video_options(),
        'preselectedVideo' => query_string('video'),
        'canManage' => can($user['role'], 'operar'),
        'canDelete' => can($user['role'], 'excluir'),
    ]);
}

function action_thumbnail_upload(): void
{
    $user = require_permission('operar');
    $videoId = post_string('video_id', 64);
    $video = $videoId !== '' ? find_video($videoId) : null;
    if ($videoId !== '' && $video === null) {
        flash('erro', 'O vídeo escolhido não existe mais.');
        redirect('/capas/biblioteca');
    }

    $backUrl = '/capas/biblioteca' . ($videoId !== '' ? '?video=' . rawurlencode($videoId) : '');
    try {
        $stored = store_thumbnail_upload($_FILES['arquivo'] ?? null);
    } catch (UserFacingException $error) {
        flash('erro', $error->getMessage());
        redirect($backUrl);
    } catch (Throwable $error) {
        error_log('[controladoria] envio de capa: ' . $error->getMessage());
        flash('erro', 'Não foi possível salvar a imagem agora. Tente de novo.');
        redirect($backUrl);
    }

    $title = post_string('title', 160);
    if ($title === '') {
        $title = $video !== null ? 'Capa · ' . mb_substr($video['title'], 0, 140) : 'Capa enviada em ' . fmt_date(utc_now());
    }
    $now = to_db(utc_now());
    $id = (int) db_insert('thumbnails', $stored + [
        'title' => $title,
        'notes' => post_string('notes', 1000) ?: null,
        'status' => 'rascunho',
        'video_id' => $video['id'] ?? null,
        'uploaded_by' => $user['id'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    log_audit($user['id'], 'capa.enviada', ['capa' => $title, 'video' => $video['title'] ?? null]);

    if ($video !== null && post_checkbox('usar')) {
        use_thumbnail_for_video($id, $video['id'], $user['id']);
        log_audit($user['id'], 'capa.usada', ['capa' => $title, 'video' => $video['title']]);
        flash('ok', 'Capa enviada e definida como a capa de "' . $video['title'] . '".');
    } else {
        flash('ok', 'Capa enviada para a biblioteca.');
    }
    redirect('/capas/biblioteca');
}

function find_thumbnail_or_404($id): array
{
    $id = filter_var($id, FILTER_VALIDATE_INT);
    $thumbnail = $id !== false ? find_thumbnail($id) : null;
    if ($thumbnail === null) {
        abort(404);
    }
    return $thumbnail;
}

function page_thumbnail_edit(): void
{
    $user = require_permission('operar');
    render_page('thumbnail-edit', 'Editar capa', [
        'thumbnail' => find_thumbnail_or_404(query_string('id')),
        'videos' => video_options(),
        'canDelete' => can($user['role'], 'excluir'),
    ]);
}

function action_thumbnail_update(): void
{
    $user = require_permission('operar');
    $thumbnail = find_thumbnail_or_404($_POST['id'] ?? null);
    $title = post_string('title', 160);
    $status = post_string('status', 12);
    $videoId = post_string('video_id', 64);
    $video = $videoId !== '' ? find_video($videoId) : null;

    if (mb_strlen($title) < 2) {
        flash('erro', 'Dê um nome para a capa.');
    } elseif (!isset(THUMBNAIL_STATUSES[$status])) {
        flash('erro', 'Escolha um status válido.');
    } elseif ($videoId !== '' && $video === null) {
        flash('erro', 'O vídeo escolhido não existe mais.');
    } elseif ($status === 'usada' && $video === null) {
        flash('erro', 'Para marcar como "Em uso", escolha o vídeo da capa.');
    } else {
        // Se a capa deixou de ser usada ou mudou de vídeo, o vídeo antigo volta a ficar sem capa.
        if ($thumbnail['in_use'] && ($status !== 'usada' || $thumbnail['video_id'] !== $videoId)) {
            db_exec("UPDATE videos SET thumbnail_id = NULL, thumbnail_status = 'pendente', updated_at = ? WHERE thumbnail_id = ?", [to_db(utc_now()), $thumbnail['id']]);
        }
        db_update('thumbnails', [
            'title' => $title,
            'notes' => post_string('notes', 1000) ?: null,
            'status' => $status === 'usada' ? $thumbnail['status'] : $status,
            'video_id' => $video['id'] ?? null,
            'updated_at' => to_db(utc_now()),
        ], 'id = ?', [$thumbnail['id']]);
        if ($status === 'usada') {
            use_thumbnail_for_video($thumbnail['id'], $video['id'], $user['id']);
        }
        log_audit($user['id'], 'capa.editada', ['capa' => $title, 'status' => $status]);
        flash('ok', 'Capa atualizada.');
        redirect('/capas/biblioteca');
    }
    redirect('/capas/biblioteca/editar?id=' . $thumbnail['id']);
}

function action_thumbnail_use(): void
{
    $user = require_permission('operar');
    $thumbnail = find_thumbnail_or_404($_POST['id'] ?? null);
    $video = $thumbnail['video_id'] !== null ? find_video($thumbnail['video_id']) : null;
    if ($video === null) {
        flash('erro', 'Escolha o vídeo desta capa antes de usá-la.');
        redirect('/capas/biblioteca/editar?id=' . $thumbnail['id']);
    }
    use_thumbnail_for_video($thumbnail['id'], $video['id'], $user['id']);
    log_audit($user['id'], 'capa.usada', ['capa' => $thumbnail['title'], 'video' => $video['title']]);
    flash('ok', 'Pronto: esta é a capa de "' . $video['title'] . '".');
    redirect('/capas/biblioteca');
}

function action_thumbnail_delete(): void
{
    $user = require_permission('excluir');
    $thumbnail = find_thumbnail_or_404($_POST['id'] ?? null);
    db_exec("UPDATE videos SET thumbnail_id = NULL, thumbnail_status = 'pendente', updated_at = ? WHERE thumbnail_id = ?", [to_db(utc_now()), $thumbnail['id']]);
    db_exec('DELETE FROM thumbnails WHERE id = ?', [$thumbnail['id']]);
    delete_thumbnail_file($thumbnail['file_name']);
    log_audit($user['id'], 'capa.excluida', ['capa' => $thumbnail['title']]);
    flash('ok', 'Capa excluída.');
    redirect('/capas/biblioteca');
}

/** Entrega a imagem só para quem está logado. */
function serve_thumbnail(): void
{
    require_user();
    send_thumbnail_file(find_thumbnail_or_404(query_string('id')), query_string('download') === '1');
}
