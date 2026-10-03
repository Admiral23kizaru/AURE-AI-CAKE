<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' && session_status()!==PHP_SESSION_ACTIVE) session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','use_strict_mode'=>true]);
function csrf_token(): string { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8').'">'; }
function require_post_csrf(): void { if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);exit('Method not allowed');} $sent=(string)($_POST['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??'')); if($sent===''||!hash_equals(csrf_token(),$sent)){http_response_code(419);exit('Your session expired. Please refresh and try again.');} }
function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function redirect(string $url): never { header('Location: '.$url); exit; }
