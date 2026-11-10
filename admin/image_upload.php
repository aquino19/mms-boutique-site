<?php
/**
 * Recebe a foto enviada no formulário de produto, corrige a rotação de
 * fotos de celular, reduz para no máximo 1200 x 1600 px e grava em
 * assets/produtos/. Retorna o caminho público (/assets/produtos/...).
 * Lança RuntimeException com uma mensagem em português se algo falhar.
 */
function mmsSaveProductPhoto(array $file, int $productId): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = [
            UPLOAD_ERR_INI_SIZE => 'arquivo maior que o limite do servidor',
            UPLOAD_ERR_FORM_SIZE => 'arquivo grande demais',
            UPLOAD_ERR_PARTIAL => 'envio interrompido, tente de novo',
        ][$file['error']] ?? 'erro no envio (código ' . (int)$file['error'] . ')';
        throw new RuntimeException($msg);
    }
    if ($file['size'] > 20 * 1024 * 1024) {
        throw new RuntimeException('a foto passa de 20 MB');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('formato não aceito (use JPG, PNG ou WEBP)');
    }

    $dir = dirname(__DIR__) . '/assets/produtos';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('não foi possível criar a pasta assets/produtos');
    }
    $name = 'p' . $productId . '-' . date('YmdHis') . '.jpg';
    $dest = $dir . '/' . $name;

    if (!function_exists('imagecreatetruecolor')) {
        // Sem a biblioteca de imagens: guarda o JPG original
        if ($types[$info[2]] !== 'jpeg' || !move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('o servidor não consegue converter imagens; envie a foto em JPG');
        }
        return '/assets/produtos/' . $name;
    }

    $loader = ['jpeg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'][$types[$info[2]]];
    $src = function_exists($loader) ? @$loader($file['tmp_name']) : false;
    if (!$src) {
        throw new RuntimeException('não foi possível ler a imagem');
    }

    // Fotos de celular guardam a rotação no EXIF
    if ($types[$info[2]] === 'jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rotate = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rotate) {
            $rotated = imagerotate($src, $rotate, 0);
            if ($rotated) {
                imagedestroy($src);
                $src = $rotated;
            }
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, 1200 / $w, 1600 / $h);
    $nw = (int)round($w * $scale);
    $nh = (int)round($h * $scale);
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // fundo branco para PNG transparente
    imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($out, true);
    $ok = imagejpeg($out, $dest, 82);
    imagedestroy($src);
    imagedestroy($out);
    if (!$ok) {
        throw new RuntimeException('não foi possível gravar a foto no servidor');
    }
    return '/assets/produtos/' . $name;
}
