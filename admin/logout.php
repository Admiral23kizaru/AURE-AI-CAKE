<?php
declare(strict_types=1);

require __DIR__ . '/../inc/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
require_post_csrf();
logout_user();
redirect('/AI-CAKE/login.php');
