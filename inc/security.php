<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' && session_status()!==PHP_SESSION_ACTIVE) session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','use_strict_mode'=>true]);
function csrf_token(): string { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8').'">'; }
function require_post_csrf(): void { if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);exit('Method not allowed');} $sent=(string)($_POST['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??'')); if($sent===''||!hash_equals(csrf_token(),$sent)){http_response_code(419);exit('Your session expired. Please refresh and try again.');} }
function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function redirect(string $url): never { header('Location: '.$url); exit; }

/**
 * Returns the web path of this project without relying on the folder name.
 * It works in a subfolder (for example /cake-ai) or directly in public_html.
 */
function app_base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    if (PHP_SAPI === 'cli') return $base = '';

    $projectRoot = realpath(__DIR__ . '/..');
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $normalise = static fn(string $path): string => rtrim(str_replace('\\', '/', $path), '/');

    if ($projectRoot && $documentRoot) {
        $project = $normalise($projectRoot);
        $document = $normalise($documentRoot);
        if (str_starts_with(strtolower($project . '/'), strtolower($document . '/'))) {
            $relative = trim(substr($project, strlen($document)), '/');
            return $base = $relative === '' ? '' : '/' . $relative;
        }
    }

    // Fallback for hosts whose DOCUMENT_ROOT cannot be resolved by PHP.
    $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? realpath((string) $_SERVER['SCRIPT_FILENAME']) : false;
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($projectRoot && $scriptFile && $scriptName !== '') {
        $project = $normalise($projectRoot);
        $file = $normalise($scriptFile);
        if (str_starts_with(strtolower($file . '/'), strtolower($project . '/'))) {
            $relativeParts = array_values(array_filter(explode('/', trim(substr($file, strlen($project)), '/')), 'strlen'));
            $scriptParts = explode('/', $scriptName);
            if ($relativeParts && count($scriptParts) >= count($relativeParts)) {
                $baseParts = array_slice($scriptParts, 0, -count($relativeParts));
                return $base = rtrim(implode('/', $baseParts), '/');
            }
        }
    }

    return $base = '';
}

function app_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = app_base_path();
    if ($path === '') return $base === '' ? '/' : $base . '/';
    return ($base === '' ? '' : $base) . '/' . $path;
}
