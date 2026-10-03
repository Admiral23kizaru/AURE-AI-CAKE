<?php
declare(strict_types=1);

function store_uploaded_image(array $file, string $directory): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Please choose a valid image file.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Image size must not exceed 5 MB.');
    }
    $tmp = (string) $file['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info || ($info[0] ?? 0) > 6000 || ($info[1] ?? 0) > 6000) {
        throw new RuntimeException('The uploaded file is not a supported image.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || ($info['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Only JPG, PNG, and WebP images are accepted.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The image directory is unavailable.');
    }
    $name = bin2hex(random_bytes(20)).'.'.$allowed[$mime];
    if (!move_uploaded_file($tmp, rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name)) {
        throw new RuntimeException('Unable to save the image.');
    }
    return $name;
}

function remove_managed_image(string $directory, ?string $name): void
{
    $safe = basename((string) $name);
    if ($safe === '' || $safe === '.' || $safe === '..') return;
    $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$safe;
    if (is_file($path)) @unlink($path);
}
