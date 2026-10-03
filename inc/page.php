<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/dashboard.php';

function page_start(string $title, bool $showActions = true): void
{
    $loggedIn = is_logged_in();
    $role = current_user()['role'] ?? 'guest';
    if ($showActions && $loggedIn && in_array($role, ['admin', 'staff'], true)) {
        dashboard_start($title);
        return;
    }
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
    <body class="app-page">
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
                <?php if ($role === 'customer'): ?>
                  <div class="app-customer-links d-none d-lg-flex" aria-label="Customer navigation">
                    <a href="/AI-CAKE/my_orders.php">Orders</a><a href="/AI-CAKE/customer_menu.php">Menu</a><a href="/AI-CAKE/favorites.php">Favorites</a><a href="/AI-CAKE/account.php">Profile</a><a href="/AI-CAKE/ai_cake.php">AI cake</a>
                  </div>
                <?php endif; ?>
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
      <?php if ($showActions && $loggedIn && $role === 'customer'): ?>
        <nav class="app-customer-mobile-nav d-lg-none" aria-label="Customer navigation">
          <a href="/AI-CAKE/my_orders.php">Orders</a><a href="/AI-CAKE/customer_menu.php">Menu</a><a href="/AI-CAKE/favorites.php">Favorites</a><a href="/AI-CAKE/account.php">Profile</a><a href="/AI-CAKE/ai_cake.php">AI cake</a>
        </nav>
      <?php endif; ?>
    <?php
}

function page_end(): void
{
    if (dashboard_shell_active()) {
        dashboard_end();
        return;
    }
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
