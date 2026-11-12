<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

function dashboard_shell_active(): bool
{
    return !empty($GLOBALS['ai_cake_dashboard_shell_active']);
}

function dashboard_current_path(): string
{
    return strtolower(str_replace('\\', '/', (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '')));
}

function dashboard_link_is_active(array $item): bool
{
    $current = dashboard_current_path();
    foreach ($item['paths'] as $path) {
        if (str_ends_with($current, strtolower($path))) {
            $query = [];
            parse_str((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?: ''), $query);
            foreach (($item['query'] ?? []) as $key => $value) {
                if (($query[$key] ?? null) !== $value) return false;
            }
            foreach (($item['exclude_query'] ?? []) as $key => $value) {
                if (($query[$key] ?? null) === $value) return false;
            }
            return true;
        }
    }
    return false;
}

function dashboard_navigation(string $role): array
{
    $orderWorkspaceUrl = $role === 'admin' ? app_url('admin/orders.php') : app_url('staff/unified_orders.php');
    $operations = [
        ['label' => 'Orders', 'icon' => 'bi-receipt', 'href' => $orderWorkspaceUrl, 'paths' => ['/staff/unified_orders.php', '/admin/orders.php', '/admin/add_orders.php', '/admin/order_history.php'], 'exclude_query' => ['order_type' => 'ai']],
        ['label' => 'Pickup schedule', 'icon' => 'bi-calendar2-check', 'href' => app_url('staff/pickup_schedule.php'), 'paths' => ['/staff/pickup_schedule.php']],
        ['label' => 'Assisted order', 'icon' => 'bi-person-plus', 'href' => app_url('staff/assisted_order.php'), 'paths' => ['/staff/assisted_order.php']],
        ['label' => 'AI orders', 'icon' => 'bi-stars', 'href' => $orderWorkspaceUrl . '?order_type=ai', 'paths' => ['/staff/unified_orders.php', '/staff/ai_orders.php', '/admin/ai_orders.php'], 'query' => ['order_type' => 'ai']],
        ['label' => 'AI conversation', 'icon' => 'bi-chat-square-text', 'href' => app_url('staff/chat.php'), 'paths' => ['/staff/chat.php', '/admin/chat.php']],
    ];

    $inventoryPath = app_url($role === 'admin' ? 'admin' : 'staff');
    $groups = [
        ['label' => 'Workspace', 'items' => [
            ['label' => 'Overview', 'icon' => 'bi-grid-1x2', 'href' => $inventoryPath . '/dashboard.php', 'paths' => ['/' . ($role === 'admin' ? 'admin' : 'staff') . '/dashboard.php']],
        ]],
        ['label' => 'Operations', 'items' => $operations],
        ['label' => 'Inventory', 'items' => [
            ['label' => 'Cake inventory', 'icon' => 'bi-box-seam', 'href' => $inventoryPath . '/add_quantity.php', 'paths' => ['/' . ($role === 'admin' ? 'admin' : 'staff') . '/add_quantity.php']],
            ['label' => 'Add-on inventory', 'icon' => 'bi-plus-circle', 'href' => $inventoryPath . '/addons.php', 'paths' => ['/' . ($role === 'admin' ? 'admin' : 'staff') . '/addons.php']],
        ]],
        ['label' => 'Reports', 'items' => [
            ['label' => 'Sales report', 'icon' => 'bi-bar-chart-line', 'href' => app_url('admin/combined_reports.php'), 'paths' => ['/admin/combined_reports.php', '/admin/reports.php', '/admin/item_sold.php', '/staff/reports.php', '/staff/item_sold.php']],
        ]],
    ];

    if ($role === 'admin') {
        $groups[] = ['label' => 'Catalog', 'items' => [
            ['label' => 'Cakes', 'icon' => 'bi-cake2', 'href' => app_url('admin/cakes.php'), 'paths' => ['/admin/cakes.php']],
            ['label' => 'Categories', 'icon' => 'bi-collection', 'href' => app_url('admin/categories.php'), 'paths' => ['/admin/categories.php']],
            ['label' => 'Cake options', 'icon' => 'bi-sliders', 'href' => app_url('admin/options.php'), 'paths' => ['/admin/options.php']],
        ]];
        $groups[] = ['label' => 'Administration', 'items' => [
            ['label' => 'Accounts', 'icon' => 'bi-people', 'href' => app_url('admin/accounts.php'), 'paths' => ['/admin/accounts.php', '/admin/staffs.php']],
            ['label' => 'Pickup capacity', 'icon' => 'bi-calendar-range', 'href' => app_url('admin/capacity.php'), 'paths' => ['/admin/capacity.php']],
            ['label' => 'Pickup shop', 'icon' => 'bi-shop', 'href' => app_url('admin/shop.php'), 'paths' => ['/admin/shop.php', '/admin/stores.php']],

        ]];
    }

    return $groups;
}

function dashboard_start(string $title): void
{
    $user = current_user();
    $role = $user['role'] ?? 'staff';
    $roleLabel = $role === 'admin' ? 'Administrator' : 'Staff workspace';
    $GLOBALS['ai_cake_dashboard_shell_active'] = true;
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= e($title) ?> | Aure Sanchez House of Cakes</title>
      <link rel="icon" href="<?= e(app_url('uploads/logo/logotab.png')) ?>">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
      <link href="<?= e(app_url('assets/css/app.css?v=20261006-1')) ?>" rel="stylesheet">
      <link href="<?= e(app_url('assets/css/dashboard.css?v=20261001-1')) ?>" rel="stylesheet">
    </head>
    <body class="dashboard-page">
      <a class="skip-link" href="#dashboard-content">Skip to content</a>
      <div class="dashboard-shell">
        <aside class="dashboard-sidebar" aria-label="<?= e($roleLabel) ?> navigation">
          <a class="dashboard-brand" href="<?= e(app_url(($role === 'admin' ? 'admin' : 'staff') . '/dashboard.php')) ?>"><img src="<?= e(app_url('logo.png')) ?>" width="142" height="42" alt="Aure Sanchez House of Cakes"><span><?= e($roleLabel) ?></span></a>
          <nav class="dashboard-nav">
            <?php foreach (dashboard_navigation($role) as $group): ?><section class="dashboard-nav-group"><h2><?= e($group['label']) ?></h2><?php foreach ($group['items'] as $item): $active = dashboard_link_is_active($item); ?><a class="dashboard-nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e($item['href']) ?>"<?= $active ? ' aria-current="page"' : '' ?>><i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i><span><?= e($item['label']) ?></span></a><?php endforeach; ?></section><?php endforeach; ?>
          </nav>
          <div class="dashboard-sidebar-footer"><a class="dashboard-site-link" href="<?= e(app_url()) ?>"><i class="bi bi-arrow-up-right" aria-hidden="true"></i> View storefront</a><form method="post" action="<?= e(app_url('logout.php')) ?>" class="m-0"><?= csrf_field() ?><button class="dashboard-logout" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button></form></div>
        </aside>
        <div class="dashboard-mobile-bar"><a href="<?= e(app_url(($role === 'admin' ? 'admin' : 'staff') . '/dashboard.php')) ?>"><img src="<?= e(app_url('logo.png')) ?>" width="128" height="38" alt="Aure Sanchez House of Cakes"></a><button class="dashboard-menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardMenu" aria-controls="dashboardMenu" aria-label="Open navigation"><i class="bi bi-list" aria-hidden="true"></i></button></div>
        <div class="offcanvas offcanvas-start dashboard-offcanvas" tabindex="-1" id="dashboardMenu" aria-label="<?= e($roleLabel) ?> navigation"><div class="offcanvas-header"><img src="<?= e(app_url('logo.png')) ?>" width="142" height="42" alt="Aure Sanchez House of Cakes"><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button></div><div class="offcanvas-body p-0"><nav class="dashboard-nav p-3"><?php foreach (dashboard_navigation($role) as $group): ?><section class="dashboard-nav-group"><h2><?= e($group['label']) ?></h2><?php foreach ($group['items'] as $item): $active = dashboard_link_is_active($item); ?><a class="dashboard-nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e($item['href']) ?>"><i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i><span><?= e($item['label']) ?></span></a><?php endforeach; ?></section><?php endforeach; ?></nav></div></div>
        <div class="dashboard-main"><header class="dashboard-topbar"><div><p class="dashboard-topbar-kicker"><?= e($roleLabel) ?></p><p class="dashboard-topbar-user">Hello, <?= e($user['name'] ?: 'there') ?></p></div><a class="dashboard-profile-link" href="<?= e(app_url($role === 'admin' ? 'admin/accounts.php' : 'staff/dashboard.php')) ?>" aria-label="Open <?= e($roleLabel) ?>"><i class="bi bi-person-circle" aria-hidden="true"></i></a></header><div id="dashboard-content" class="dashboard-content" role="main" tabindex="-1">
    <?php
}

function dashboard_end(): void
{
    ?>
          </div>
        </div>
      </div>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
      <script>
      (() => {
        const sidebar = document.querySelector('.dashboard-sidebar');
        if (!sidebar || !window.sessionStorage) return;
        const storageKey = 'ai-cake:dashboard-sidebar-scroll:v1';
        const savedPosition = Number(sessionStorage.getItem(storageKey));
        if (Number.isFinite(savedPosition) && savedPosition > 0) {
          requestAnimationFrame(() => { sidebar.scrollTop = savedPosition; });
        }
        const savePosition = () => sessionStorage.setItem(storageKey, String(sidebar.scrollTop));
        sidebar.addEventListener('scroll', savePosition, { passive: true });
        document.querySelectorAll('.dashboard-sidebar a').forEach((link) => link.addEventListener('click', savePosition));
        window.addEventListener('pagehide', savePosition);
      })();
      </script>
    </body>
    </html>
    <?php
    $GLOBALS['ai_cake_dashboard_shell_active'] = false;
}
