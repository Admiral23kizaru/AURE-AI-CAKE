<?php
declare(strict_types=1);
require_once __DIR__ . '/dashboard.php';
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
dashboard_start('Operations');
