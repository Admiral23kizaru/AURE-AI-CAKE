<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require __DIR__ . '/inc/order_service.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$query = $pdo->prepare('SELECT * FROM order_headers WHERE id=?');
$query->execute([$id]);
$order = $query->fetch();
if (!$order || (current_user()['role'] === 'customer' && (int) $order['user_id'] !== user_id())) {
    http_response_code(404);
    exit('Receipt not found.');
}
$claim = $order['claim_code_cipher'] ? claim_decrypt($order['claim_code_cipher']) : '';
$items = [];
$ai = null;
if ($order['normal_order_id']) {
    $itemQuery = $pdo->prepare('SELECT oi.*,c.name FROM order_items oi LEFT JOIN cakes c ON c.id=oi.cake_id WHERE oi.order_id=?');
    $itemQuery->execute([$order['normal_order_id']]);
    $items = $itemQuery->fetchAll();
    foreach ($items as &$item) {
        $optionQuery = $pdo->prepare('SELECT option_type,option_name,price_snapshot,quantity FROM order_item_options WHERE order_item_id=? ORDER BY id');
        $optionQuery->execute([$item['id']]);
        $item['options'] = $optionQuery->fetchAll();
    }
    unset($item);
} elseif ($order['ai_order_id']) {
    $aiQuery = $pdo->prepare('SELECT picture,personalize,cake_size FROM ai_cake_orders WHERE id=?');
    $aiQuery->execute([$order['ai_order_id']]);
    $ai = $aiQuery->fetch() ?: null;
}
page_start('Receipt');
?>
<main class="container py-4 py-lg-5 receipt-page"><section class="app-card p-4 p-md-5 mx-auto receipt-card" style="max-width:780px">
  <div class="d-flex justify-content-between align-items-start gap-3 border-bottom pb-3"><div><div class="eyebrow">Pickup receipt</div><h1 class="h3 mb-0"><?= e($order['order_number']) ?></h1></div><button class="btn btn-outline-secondary d-print-none" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button></div>
  <div class="mt-4">
    <?php foreach ($items as $item): ?><div class="d-flex justify-content-between border-bottom py-2"><div><?= e($item['name'] ?? 'Cake') ?> x <?= (int) $item['qty'] ?><?php foreach ($item['options'] as $option): ?><small class="d-block"><?= e($option['option_type'] . ': ' . $option['option_name']) ?><?php if ((float) $option['price_snapshot'] !== 0.0): ?> (&#8369;<?= number_format((float) $option['price_snapshot'], 2) ?> x <?= (int) $option['quantity'] ?>)<?php endif; ?></small><?php endforeach; ?></div><strong>&#8369;<?= number_format((float) $item['price'] * (int) $item['qty'], 2) ?></strong></div><?php endforeach; ?>
    <?php if ($ai): ?><div class="row g-3 border-bottom py-3"><div class="col-4"><?php if ($ai['picture']): ?><img src="uploads/ai-cakes/<?= e(basename((string) $ai['picture'])) ?>" class="img-fluid rounded" alt="AI cake design"><?php endif; ?></div><div class="col-8"><strong><?= e($ai['cake_size'] ?: 'AI cake') ?></strong><p class="small mb-1"><?= e($ai['personalize']) ?></p><?php if ($order['customer_note']): ?><p class="small mb-0"><strong>Note:</strong> <?= e($order['customer_note']) ?></p><?php endif; ?></div></div><?php endif; ?>
  </div>
  <dl class="row mt-4">
    <dt class="col-5">Customer</dt><dd class="col-7"><?= e($order['customer_name']) ?></dd>
    <dt class="col-5">Pickup schedule</dt><dd class="col-7"><?= e($order['pickup_date']) ?> <?= e(substr((string) $order['pickup_time'], 0, 5)) ?></dd>
    <dt class="col-5">Status</dt><dd class="col-7"><?= e($order['status']) ?></dd>
    <dt class="col-5">Payment</dt><dd class="col-7"><?= e(ucfirst($order['payment_method'])) ?> / <?= e(ucfirst($order['payment_status'])) ?></dd>
    <?php if ($order['payment_reference']): ?><dt class="col-5">Payment reference</dt><dd class="col-7"><?= e($order['payment_reference']) ?></dd><?php endif; ?>
    <dt class="col-5">Subtotal</dt><dd class="col-7">&#8369;<?= number_format((float) $order['subtotal'], 2) ?></dd>
    <dt class="col-5">Discount</dt><dd class="col-7">-&#8369;<?= number_format((float) $order['discount_total'], 2) ?></dd>
    <dt class="col-5">Total</dt><dd class="col-7 fs-5 fw-bold">&#8369;<?= number_format((float) $order['total'], 2) ?></dd>
    <?php if ($claim): ?><dt class="col-5">Claim code</dt><dd class="col-7 fs-4 fw-bold text-primary"><?= e($claim) ?></dd><?php endif; ?>
  </dl>
  <p class="help-text border-top pt-3">Present this receipt and claim code when collecting the order. Cash orders remain unpaid until collection is verified.</p>
</section></main>
<?php page_end();
