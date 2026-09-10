<?php

defined('CONTROLADORIA') || exit;

// Arquivos enviados (capas). Ficam FORA da pasta pública e só são entregues para quem está logado.

const THUMBNAIL_MAX_BYTES = 2 * 1024 * 1024;
const THUMBNAIL_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/**
 * Pasta de arquivos. Ordem: storage_path da configuração, ../controladoria-arquivos (ao lado do
 * public_html) e, se a hospedagem não permitir, storage/ dentro do projeto com acesso web bloqueado.
 */
function storage_dir(string $subfolder): string
{
    static $base = null;
    if ($base === null) {
        $candidates = array_filter([
            (string) config('storage_path', ''),
            dirname(ROOT_DIR) . '/controladoria-arquivos',
            ROOT_DIR . '/storage',
        ]);
        foreach ($candidates as $candidate) {
            $candidate = rtrim($candidate, '/\\');
            if ((@is_dir($candidate) || @mkdir($candidate, 0750, true)) && @is_writable($candidate)) {
                $base = $candidate;
                break;
            }
        }
        if ($base === null) {
            throw new UserFacingException('Não foi possível criar a pasta de arquivos. Peça ao administrador para definir storage_path na configuração.');
        }
        if ($base === ROOT_DIR . '/storage' && !is_file($base . '/.htaccess')) {
            file_put_contents($base . '/.htaccess', "Require all denied\n");
        }
    }
    $dir = $base . '/' . $subfolder;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new UserFacingException('Não foi possível criar a pasta de arquivos.');
    }
    return $dir;
}

function valid_stored_name(string $name): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name);
}

/** Recria a imagem com a biblioteca GD: remove metadados e qualquer conteúdo escondido no arquivo. */
function reencode_image(string $source, string $mime, string $target): bool
{
    if (!function_exists('imagecreatefromstring') || ($mime === 'image/webp' && !function_exists('imagewebp'))) {
        return false;
    }
    $image = @imagecreatefromstring((string) file_get_contents($source));
    if ($image === false) {
        throw new UserFacingException('A imagem parece corrompida. Exporte de novo e tente outra vez.');
    }
    switch ($mime) {
        case 'image/jpeg':
            $saved = imagejpeg($image, $target, 90);
            break;
        case 'image/png':
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = imagepng($image, $target, 6);
            break;
        default:
            $saved = imagewebp($image, $target, 90);
    }
    imagedestroy($image);
    if (!$saved) {
        throw new UserFacingException('Não foi possível processar a imagem.');
    }
    return true;
}

/**
 * Valida e guarda a capa enviada. O nome do arquivo nunca vem do usuário.
 * @return array{file_name: string, mime: string, width: int, height: int, size_bytes: int}
 */
function store_thumbnail_upload($file): array
{
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        throw new UserFacingException('Escolha a imagem da capa.');
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new UserFacingException('Escolha a imagem da capa.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new UserFacingException('A imagem passou do limite de 2 MB.');
        default:
            throw new UserFacingException('Não foi possível receber o arquivo. Tente de novo.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new UserFacingException('Arquivo inválido.');
    }
    if ((int) $file['size'] > THUMBNAIL_MAX_BYTES) {
        throw new UserFacingException('A imagem passou do limite de 2 MB.');
    }

    $info = @getimagesize($file['tmp_name']);
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    if (!isset(THUMBNAIL_EXTENSIONS[$mime])) {
        throw new UserFacingException('Use uma imagem JPG, PNG ou WebP.');
    }
    [$width, $height] = $info;
    if ($width < 160 || $height < 90 || $width > 5000 || $height > 5000) {
        throw new UserFacingException('Tamanho de imagem inválido. O YouTube recomenda 1280 × 720 pixels.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . THUMBNAIL_EXTENSIONS[$mime];
    $target = storage_dir('capas') . '/' . $name;
    if (!reencode_image($file['tmp_name'], $mime, $target) && !move_uploaded_file($file['tmp_name'], $target)) {
        throw new UserFacingException('Não foi possível salvar a imagem.');
    }
    @chmod($target, 0640);

    return [
        'file_name' => $name,
        'mime' => $mime,
        'width' => (int) $width,
        'height' => (int) $height,
        'size_bytes' => (int) (filesize($target) ?: $file['size']),
    ];
}

function delete_thumbnail_file(string $name): void
{
    if (valid_stored_name($name)) {
        @unlink(storage_dir('capas') . '/' . $name);
    }
}

function send_thumbnail_file(array $thumbnail, bool $download): void
{
    if (!valid_stored_name($thumbnail['file_name']) || !isset(THUMBNAIL_EXTENSIONS[$thumbnail['mime']])) {
        abort(404);
    }
    $path = storage_dir('capas') . '/' . $thumbnail['file_name'];
    if (!is_file($path)) {
        abort(404);
    }
    $fileName = 'capa-' . $thumbnail['id'] . '.' . THUMBNAIL_EXTENSIONS[$thumbnail['mime']];
    header('Content-Type: ' . $thumbnail['mime']);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
    readfile($path);
    exit;
}
