<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

function page_start(string $title, bool $showActions = true): void
{
    $loggedIn = is_logged_in();
    $role = current_user()['role'] ?? 'guest';
    $home = role_home_path($role);
    $accountLabel = in_array($role, ['admin', 'staff'], true) ? 'Dashboard' : 'My orders';
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= e($title) ?> | Aure Sanchez House of Cakes</title>
      <link rel="icon" href="/AI-CAKE/uploads/logo/logotab.png">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
      <link href="/AI-CAKE/assets/css/app.css?v=20260928-3" rel="stylesheet">
    </head>
    <body>
      <nav class="navbar app-nav<?= $showActions ? '' : ' app-nav-minimal' ?>">
        <div class="container app-nav-inner">
          <?php if (!$showActions): ?>
            <a class="auth-back-link" href="/AI-CAKE/"><i class="bi bi-arrow-left" aria-hidden="true"></i><span>Back to home</span></a>
          <?php endif; ?>
          <a class="app-brand d-flex align-items-center gap-2" href="/AI-CAKE/">
            <img src="/AI-CAKE/logo.png" height="42" alt="Aure Sanchez House of Cakes">
          </a>
          <?php if ($showActions): ?>
            <div class="d-flex gap-2 align-items-center">
              <?php if ($loggedIn): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e($home) ?>"><?= e($accountLabel) ?></a>
                <form method="post" action="/AI-CAKE/logout.php" class="m-0"><?= csrf_field() ?><button class="btn btn-sm btn-purple">Log out</button></form>
              <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" href="/AI-CAKE/login.php">Log in</a>
                <a class="btn btn-sm btn-purple" href="/AI-CAKE/register.php">Register</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <span class="auth-nav-spacer" aria-hidden="true"></span>
          <?php endif; ?>
        </div>
      </nav>
    <?php
}

function page_end(): void
{
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
