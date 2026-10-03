<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/page.php';
require_staff_or_admin();

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : date('Y-m-d');
if ($from > $to) { [$from, $to] = [$to, $from]; }
$range = [$from, $to];
$completedWhere = "created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY) AND status='Completed' AND payment_status='paid'";

$summaryQuery = $pdo->prepare("SELECT order_type,COUNT(*) orders_count,COALESCE(SUM(total),0) revenue,COALESCE(SUM(discount_total),0) discounts FROM order_headers WHERE {$completedWhere} GROUP BY order_type");
$summaryQuery->execute($range);
$summary = $summaryQuery->fetchAll();
$summaryByType = [];
foreach ($summary as $row) $summaryByType[$row['order_type']] = $row;
$dailyQuery = $pdo->prepare("SELECT DATE(created_at) day,COUNT(*) orders_count,COALESCE(SUM(total),0) revenue FROM order_headers WHERE {$completedWhere} GROUP BY DATE(created_at) ORDER BY day");
$dailyQuery->execute($range);
$daily = $dailyQuery->fetchAll();
$topQuery = $pdo->prepare("SELECT c.name,SUM(oi.qty) qty FROM order_headers h JOIN order_items oi ON oi.order_id=h.normal_order_id JOIN cakes c ON c.id=oi.cake_id WHERE h.created_at>=? AND h.created_at<DATE_ADD(?,INTERVAL 1 DAY) AND h.status='Completed' AND h.payment_status='paid' GROUP BY c.id,c.name ORDER BY qty DESC LIMIT 10");
$topQuery->execute($range);
$top = $topQuery->fetchAll();
$statusQuery = $pdo->prepare('SELECT status,order_type,COUNT(*) total FROM order_headers WHERE created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY) GROUP BY status,order_type ORDER BY status,order_type');
$statusQuery->execute($range);
$statusCounts = $statusQuery->fetchAll();
$pickupQuery=$pdo->prepare("SELECT pickup_date,DATE_FORMAT(pickup_time,'%H:%i') pickup_time,order_type,COUNT(*) total FROM order_headers WHERE pickup_date>=? AND pickup_date<=? AND status<>'Cancelled' GROUP BY pickup_date,pickup_time,order_type ORDER BY pickup_date,pickup_time,order_type");
$pickupQuery->execute($range);$pickupStats=$pickupQuery->fetchAll();

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cake-report-' . $from . '-' . $to . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date','Order number','Type','Status','Payment status','Discount','Total','Recognized revenue']);
    $rows = $pdo->prepare('SELECT DATE(created_at) order_date,order_number,order_type,status,payment_status,discount_total,total,IF(status=\'Completed\' AND payment_status=\'paid\',total,0) recognized_revenue FROM order_headers WHERE created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY) ORDER BY created_at');
    $rows->execute($range);
    foreach ($rows as $row) fputcsv($output, array_values($row));
    fclose($output);
    exit;
}

page_start('Reports');
?>
<main class="container py-4">
  <div class="d-flex justify-content-between align-items-end"><div><div class="eyebrow">Completed and collected orders</div><h1 class="h3 mb-0">Combined sales report</h1></div><div class="d-print-none"><a class="btn btn-outline-primary" href="?<?= http_build_query(['from'=>$from,'to'=>$to,'format'=>'csv']) ?>">Export CSV</a> <button onclick="window.print()" class="btn btn-outline-primary">Print</button></div></div>
  <p class="help-text mt-2">Revenue includes only orders marked Completed and Paid. Pending and unpaid orders remain visible in the status breakdown but are not counted as sales.</p>
  <form class="app-card p-3 my-3 row g-2 d-print-none"><div class="col"><label for="from">From</label><input id="from" type="date" name="from" value="<?= e($from) ?>" class="form-control"></div><div class="col"><label for="to">To</label><input id="to" type="date" name="to" value="<?= e($to) ?>" class="form-control"></div><div class="col-auto d-flex align-items-end"><button class="btn btn-purple">Apply</button></div></form>
  <div class="row g-3"><?php foreach (['normal'=>'Normal cakes','ai'=>'AI cakes'] as $type=>$label): $row = $summaryByType[$type] ?? ['orders_count'=>0,'revenue'=>0,'discounts'=>0]; ?><div class="col-md-6"><div class="app-card p-4"><div class="eyebrow"><?= e($label) ?></div><div class="fs-3 fw-bold">&#8369;<?= number_format((float) $row['revenue'], 2) ?></div><div><?= (int) $row['orders_count'] ?> completed order(s) &middot; &#8369;<?= number_format((float) $row['discounts'], 2) ?> discounts</div></div></div><?php endforeach; ?></div>
  <div class="row g-4 mt-1">
    <div class="col-lg-7"><div class="app-card table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Completed orders</th><th>Recognized revenue</th></tr></thead><tbody><?php foreach ($daily as $row): ?><tr><td><?= e($row['day']) ?></td><td><?= (int) $row['orders_count'] ?></td><td>&#8369;<?= number_format((float) $row['revenue'], 2) ?></td></tr><?php endforeach; ?><?php if (!$daily): ?><tr><td colspan="3" class="text-center py-4">No completed paid orders in this range.</td></tr><?php endif; ?></tbody></table></div></div>
    <div class="col-lg-5"><div class="app-card p-4"><h2 class="h5">Top-selling normal cakes</h2><ol><?php foreach ($top as $row): ?><li><?= e($row['name']) ?> &mdash; <?= (int) $row['qty'] ?></li><?php endforeach; ?><?php if (!$top): ?><li class="text-muted">No completed normal-cake sales.</li><?php endif; ?></ol></div></div>
  </div>
  <div class="app-card table-responsive mt-4"><table class="table mb-0"><thead><tr><th>Status</th><th>Type</th><th>Orders</th></tr></thead><tbody><?php foreach ($statusCounts as $row): ?><tr><td><?= e($row['status']) ?></td><td><?= e(strtoupper($row['order_type'])) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="app-card table-responsive mt-4"><h2 class="h5 p-3 mb-0">Pickup statistics</h2><table class="table mb-0"><thead><tr><th>Date</th><th>Time</th><th>Type</th><th>Orders</th></tr></thead><tbody><?php foreach($pickupStats as $row):?><tr><td><?=e($row['pickup_date'])?></td><td><?=e($row['pickup_time'])?></td><td><?=e(strtoupper($row['order_type']))?></td><td><?=(int)$row['total']?></td></tr><?php endforeach;?><?php if(!$pickupStats):?><tr><td colspan="4" class="text-center py-4">No scheduled pickups in this range.</td></tr><?php endif;?></tbody></table></div>
</main>
<?php page_end();
