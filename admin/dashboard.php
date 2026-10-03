<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin_role();

$counts = $pdo->query('SELECT
    (SELECT COUNT(*) FROM cakes) AS cakes,
    (SELECT COUNT(*) FROM categories) AS cats,
    (SELECT COUNT(*) FROM order_headers WHERE order_type = "normal") AS orders,
    (SELECT COUNT(*) FROM addons) AS addons,
    (SELECT COUNT(*) FROM stores WHERE is_active = 1) AS stores,
    (SELECT COUNT(*) FROM order_headers WHERE order_type = "ai") AS ai_orders,
    (SELECT COUNT(*) FROM users WHERE role = "staff" AND is_active = 1) AS staffs
')->fetch(PDO::FETCH_ASSOC) ?: [];

$cakes = $pdo->query('SELECT cake_id, name, quantity, available FROM cakes ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../inc/header.php';
?>

<section class="workspace-heading">
  <div>
    <p class="workspace-heading__eyebrow">Business control center</p>
    <h1>Admin overview</h1>
    <p>Manage the bakery catalog, order operations, pickup readiness, and customer accounts from one workspace.</p>
  </div>
  <a class="btn btn-purple" href="readiness.php"><i class="bi bi-clipboard2-check me-2" aria-hidden="true"></i>Review readiness</a>
</section>

<section class="dashboard-stat-grid" aria-label="Business summary">
  <article class="dashboard-stat"><span class="dashboard-stat__icon"><i class="bi bi-cake2" aria-hidden="true"></i></span><div><p class="dashboard-stat__label">Cake products</p><p class="dashboard-stat__value"><?= (int) ($counts['cakes'] ?? 0) ?></p></div></article>
  <article class="dashboard-stat"><span class="dashboard-stat__icon"><i class="bi bi-receipt" aria-hidden="true"></i></span><div><p class="dashboard-stat__label">Normal orders</p><p class="dashboard-stat__value"><?= (int) ($counts['orders'] ?? 0) ?></p></div></article>
  <article class="dashboard-stat"><span class="dashboard-stat__icon"><i class="bi bi-stars" aria-hidden="true"></i></span><div><p class="dashboard-stat__label">AI cake orders</p><p class="dashboard-stat__value"><?= (int) ($counts['ai_orders'] ?? 0) ?></p></div></article>
  <article class="dashboard-stat"><span class="dashboard-stat__icon"><i class="bi bi-people" aria-hidden="true"></i></span><div><p class="dashboard-stat__label">Active staff</p><p class="dashboard-stat__value"><?= (int) ($counts['staffs'] ?? 0) ?></p></div></article>
  <article class="dashboard-stat"><span class="dashboard-stat__icon"><i class="bi bi-plus-circle" aria-hidden="true"></i></span><div><p class="dashboard-stat__label">Add-ons</p><p class="dashboard-stat__value"><?= (int) ($counts['addons'] ?? 0) ?></p></div></article>
  <article class="dashboard-stat"><span class="dashboard-stat__icon"><i class="bi bi-shop" aria-hidden="true"></i></span><div><p class="dashboard-stat__label">Active pickup shop</p><p class="dashboard-stat__value"><?= (int) ($counts['stores'] ?? 0) ?></p></div></article>
</section>

<section class="dashboard-panel mt-4">
  <header class="dashboard-panel__header"><div><h2>Quick actions</h2><p>Every shortcut below keeps the existing page destination and workflow.</p></div></header>
  <div class="p-3 p-lg-4"><div class="dashboard-quick-actions">
    <a class="dashboard-quick-action" href="cakes.php"><i class="bi bi-cake2" aria-hidden="true"></i>Cakes</a>
    <a class="dashboard-quick-action" href="categories.php"><i class="bi bi-collection" aria-hidden="true"></i>Categories</a>
    <a class="dashboard-quick-action" href="addons.php"><i class="bi bi-plus-circle" aria-hidden="true"></i>Add-ons</a>
    <a class="dashboard-quick-action" href="options.php"><i class="bi bi-sliders" aria-hidden="true"></i>Cake options</a>
    <a class="dashboard-quick-action" href="orders.php"><i class="bi bi-receipt" aria-hidden="true"></i>Orders</a>
    <a class="dashboard-quick-action" href="ai_orders.php"><i class="bi bi-stars" aria-hidden="true"></i>AI orders</a>
    <a class="dashboard-quick-action" href="../staff/assisted_order.php"><i class="bi bi-person-plus" aria-hidden="true"></i>Assisted order</a>
    <a class="dashboard-quick-action" href="../staff/pickup_schedule.php"><i class="bi bi-calendar2-check" aria-hidden="true"></i>Pickup schedule</a>
    <a class="dashboard-quick-action" href="capacity.php"><i class="bi bi-calendar-range" aria-hidden="true"></i>Capacity</a>
    <a class="dashboard-quick-action" href="add_quantity.php"><i class="bi bi-box-seam" aria-hidden="true"></i>Cake inventory</a>
    <a class="dashboard-quick-action" href="accounts.php"><i class="bi bi-people" aria-hidden="true"></i>Accounts</a>
    <a class="dashboard-quick-action" href="stores.php"><i class="bi bi-shop" aria-hidden="true"></i>Pickup shop</a>
    <a class="dashboard-quick-action" href="reports.php"><i class="bi bi-bar-chart-line" aria-hidden="true"></i>Reports</a>
    <a class="dashboard-quick-action" href="item_sold.php"><i class="bi bi-graph-up" aria-hidden="true"></i>Item sold</a>
    <a class="dashboard-quick-action" href="order_history.php"><i class="bi bi-clock-history" aria-hidden="true"></i>Order history</a>
    <a class="dashboard-quick-action" href="staffs.php"><i class="bi bi-person-badge" aria-hidden="true"></i>Staff accounts</a>
  </div></div>
</section>

<section class="dashboard-panel mt-4">
  <header class="dashboard-panel__header"><div><h2>Current cake inventory</h2><p>Live stock and availability from the current product records.</p></div><a class="btn btn-sm btn-outline-primary" href="add_quantity.php">Adjust inventory</a></header>
  <div class="table-responsive"><table class="table dashboard-table align-middle"><thead><tr><th>Code</th><th>Cake</th><th>Stock</th><th>Availability</th></tr></thead><tbody>
  <?php foreach ($cakes as $cake): ?><tr><td><?= e($cake['cake_id']) ?></td><td class="fw-semibold"><?= e($cake['name']) ?></td><td><?= (int) $cake['quantity'] ?></td><td><span class="badge <?= $cake['available'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $cake['available'] ? 'Available' : 'Unavailable' ?></span></td></tr><?php endforeach; ?>
  <?php if (!$cakes): ?><tr><td colspan="4" class="text-center text-muted py-4">No cakes found.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<?php include __DIR__ . '/../inc/footer.php'; ?>
