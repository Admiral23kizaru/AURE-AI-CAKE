<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/page.php';
require __DIR__ . '/../inc/order_service.php';
require_staff_or_admin();

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) ? (string) $_GET['date'] : date('Y-m-d');
$time = preg_match('/^\d{2}:\d{2}$/', (string) ($_GET['time'] ?? '')) ? (string) $_GET['time'] : '';
$status = in_array((string) ($_GET['status'] ?? ''), ORDER_STATUSES, true) ? (string) $_GET['status'] : '';
$type = in_array((string) ($_GET['order_type'] ?? ''), ['normal', 'ai'], true) ? (string) $_GET['order_type'] : '';
$where = ['pickup_date=?', "status<>'Cancelled'"];
$args = [$date];
if ($time !== '') { $where[] = 'TIME_FORMAT(pickup_time,"%H:%i")=?'; $args[] = $time; }
if ($status !== '') { $where[] = 'status=?'; $args[] = $status; }
if ($type !== '') { $where[] = 'order_type=?'; $args[] = $type; }
$query = $pdo->prepare('SELECT * FROM order_headers WHERE ' . implode(' AND ', $where) . ' ORDER BY pickup_time,order_type,order_number');
$query->execute($args);
$orders = $query->fetchAll();
page_start('Pickup schedule');
?>
<main class="container py-4">
  <div class="d-flex justify-content-between align-items-end"><div><div class="eyebrow">Daily operations</div><h1 class="h3 mb-0">Pickup schedule</h1></div><a href="unified_orders.php">All orders</a></div>
  <form class="app-card p-3 my-3 row g-2">
    <div class="col-md-3"><label class="form-label" for="date">Date</label><input id="date" type="date" name="date" value="<?= e($date) ?>" class="form-control"></div>
    <div class="col-md-2"><label class="form-label" for="time">Time</label><input id="time" type="time" name="time" value="<?= e($time) ?>" class="form-control"></div>
    <div class="col-md-3"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select"><option value="">All active statuses</option><?php foreach (ORDER_STATUSES as $option): if ($option === 'Cancelled') continue; ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label" for="order_type">Type</label><select id="order_type" name="order_type" class="form-select"><option value="">All types</option><option value="normal" <?= $type === 'normal' ? 'selected' : '' ?>>Normal</option><option value="ai" <?= $type === 'ai' ? 'selected' : '' ?>>AI</option></select></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-purple w-100">Filter</button></div>
  </form>
  <div class="app-card table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Time</th><th>Order</th><th>Customer</th><th>Phone</th><th>Type</th><th>Status</th><th>Total</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><?= e(substr((string) $order['pickup_time'], 0, 5)) ?></td><td><a href="unified_orders.php?<?= http_build_query(['search'=>$order['order_number'],'pickup_date'=>$date,'pickup_time'=>$time,'status'=>$status,'order_type'=>$type]) ?>"><?= e($order['order_number']) ?></a></td><td><?= e($order['customer_name']) ?></td><td><?= e($order['customer_phone']) ?></td><td><?= e(strtoupper($order['order_type'])) ?></td><td><?= e($order['status']) ?></td><td>&#8369;<?= number_format((float) $order['total'], 2) ?></td></tr><?php endforeach; ?><?php if (!$orders): ?><tr><td colspan="7" class="text-center py-4">No pickups match these filters.</td></tr><?php endif; ?></tbody></table></div>
</main>
<?php page_end();
