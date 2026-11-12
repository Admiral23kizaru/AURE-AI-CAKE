<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
function ai_design_filename(?string $storedPath): ?string
{
    $name = basename(str_replace('\\', '/', trim((string) $storedPath)));
    if ($name === '' || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.(?:png|jpe?g|webp)\z/i', $name)) {
        return null;
    }
    return $name;
}

function ai_design_image_url(?string $storedPath): ?string
{
    $name = ai_design_filename($storedPath);
    return $name === null ? null : app_url('uploads/ai-cakes/' . rawurlencode($name));
}

function ai_design_image_exists(?string $storedPath): bool
{
    $name = ai_design_filename($storedPath);
    return $name !== null && is_file(__DIR__ . '/../uploads/ai-cakes/' . $name);
}
