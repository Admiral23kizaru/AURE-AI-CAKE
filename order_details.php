<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require __DIR__ . '/inc/order_service.php';
require __DIR__ . '/inc/ai_design.php';
require_customer();

$id = (int) ($_GET['id'] ?? 0);
$query = $pdo->prepare('SELECT * FROM order_headers WHERE id=? AND user_id=?');
$query->execute([$id, user_id()]);
$order = $query->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}
$historyQuery = $pdo->prepare('SELECT * FROM order_status_history WHERE order_header_id=? ORDER BY id');
$historyQuery->execute([$id]);
$history = $historyQuery->fetchAll();
$items = [];
$ai = null;
if ($order['normal_order_id']) {
    $itemQuery = $pdo->prepare('SELECT oi.*,c.name,c.picture FROM order_items oi LEFT JOIN cakes c ON c.id=oi.cake_id WHERE oi.order_id=?');
    $itemQuery->execute([$order['normal_order_id']]);
    $items = $itemQuery->fetchAll();
    foreach ($items as &$item) {
        $optionQuery = $pdo->prepare('SELECT option_type,option_name,price_snapshot,quantity FROM order_item_options WHERE order_item_id=? ORDER BY id');
        $optionQuery->execute([$item['id']]);
        $item['options'] = $optionQuery->fetchAll();
    }
    unset($item);
} elseif ($order['ai_order_id']) {
    $aiQuery = $pdo->prepare('SELECT picture,personalize,cake_size,customer_note FROM ai_cake_orders WHERE id=?');
    $aiQuery->execute([$order['ai_order_id']]);
    $ai = $aiQuery->fetch() ?: null;
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    require_post_csrf();
    try {
        cancel_order($pdo, $id, user_id(), (string) ($_POST['reason'] ?? ''));
        redirect('order_details.php?id=' . $id);
    } catch (Throwable $error) {
        $message = $error instanceof RuntimeException || $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to cancel the order.';
    }
}
page_start('Order ' . $order['order_number']);
?>
<main class="container py-4 py-lg-5 customer-workspace customer-order-details">
  <a href="my_orders.php" class="customer-back-link">&larr; Back to orders</a>
  <?php if ($message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <section class="app-card p-4 p-lg-5 order-detail-card">
        <div class="d-flex justify-content-between"><div><div class="eyebrow"><?= e(strtoupper($order['order_type'])) ?> order</div><h1 class="h3"><?= e($order['order_number']) ?></h1></div><span class="status-pill align-self-start"><?= e($order['status']) ?></span></div>
        <?php foreach ($items as $item): ?>
          <div class="border-top py-3"><div class="d-flex justify-content-between"><strong><?= e($item['name'] ?? 'Cake') ?> x <?= (int) $item['qty'] ?></strong><strong>&#8369;<?= number_format((float) $item['price'] * (int) $item['qty'], 2) ?></strong></div><?php foreach ($item['options'] as $option): ?><div class="small text-muted"><?= e($option['option_type']) ?>: <?= e($option['option_name']) ?><?php if ((float) $option['price_snapshot'] !== 0.0): ?> (&#8369;<?= number_format((float) $option['price_snapshot'], 2) ?> x <?= (int) $option['quantity'] ?>)<?php endif; ?></div><?php endforeach; ?></div>
        <?php endforeach; ?>
        <?php if ($ai): ?>
          <?php $aiImageUrl = ai_design_image_url($ai['picture']); $hasAiImage = $aiImageUrl !== null && ai_design_image_exists($ai['picture']); ?>
          <div class="row g-3 border-top py-3">
            <div class="col-sm-4"><div class="ai-design-thumbnail ai-design-thumbnail--customer"><?php if ($hasAiImage): ?><img src="<?= e($aiImageUrl) ?>" class="img-fluid" alt="Generated cake design" loading="lazy" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');"><?php endif; ?><span class="ai-design-thumbnail__fallback<?= $hasAiImage ? ' d-none' : '' ?>" role="img" aria-label="Generated cake design image unavailable"><i class="bi bi-image" aria-hidden="true"></i><span>Design image unavailable</span></span></div></div>
            <div class="col-sm-8"><div class="d-flex align-items-start justify-content-between gap-3"><div><h2 class="h5"><?= e($ai['cake_size'] ?: 'Size not recorded') ?> AI cake</h2><p><strong>Design request:</strong> <?= e($ai['personalize']) ?></p><?php if ($order['customer_note']): ?><p><strong>Note:</strong> <?= e($order['customer_note']) ?></p><?php endif; ?></div><a class="conversation-icon-link" href="chat.php?order_id=<?= (int) $order['ai_order_id'] ?>" aria-label="Open design conversation" title="Open design conversation"><i class="bi bi-chat-dots" aria-hidden="true"></i></a></div></div>
          </div>
        <?php endif; ?>
        <dl class="row mt-3">
          <dt class="col-sm-4">Pickup</dt><dd class="col-sm-8"><?= e($order['pickup_date']) ?> at <?= e(substr((string) $order['pickup_time'], 0, 5)) ?></dd>
          <dt class="col-sm-4">Shop</dt><dd class="col-sm-8"><?= e($order['store_name']) ?></dd>
          <dt class="col-sm-4">Payment</dt><dd class="col-sm-8"><?= e(ucfirst($order['payment_method'])) ?> &middot; <?= e(ucfirst($order['payment_status'])) ?></dd>
          <dt class="col-sm-4">Subtotal</dt><dd class="col-sm-8">&#8369;<?= number_format((float) $order['subtotal'], 2) ?></dd>
          <dt class="col-sm-4">Discount</dt><dd class="col-sm-8">-&#8369;<?= number_format((float) $order['discount_total'], 2) ?></dd>
          <dt class="col-sm-4">Total</dt><dd class="col-sm-8 fw-bold">&#8369;<?= number_format((float) $order['total'], 2) ?></dd>
        </dl>
        <?php if ($order['payment_method'] === 'gcash' && $order['payment_status'] !== 'paid' && (float) $order['total'] > 0): ?><a href="<?= $order['order_type'] === 'ai' ? 'ai_gcash_xendit.php?order_id=' . (int) $order['ai_order_id'] : 'gcash_xendit.php?order_id=' . (int) $order['normal_order_id'] ?>" class="btn btn-purple">Pay with GCash</a><?php endif; ?>
        <a target="_blank" href="receipt.php?id=<?= $id ?>" class="btn btn-outline-primary">Print receipt</a>
        <a href="reorder.php?id=<?= $id ?>" class="btn btn-outline-primary">Reorder</a>
        <?php if ($order['status'] === 'Pending'): ?><form method="post" class="mt-4 border-top pt-4"><?= csrf_field() ?><label for="reason" class="form-label">Reason for cancellation</label><textarea id="reason" name="reason" class="form-control" minlength="3" maxlength="500" required></textarea><button name="cancel" class="btn btn-outline-danger mt-2" onclick="return confirm('Cancel this order?')">Cancel pending order</button></form><?php endif; ?>
      </section>
    </div>
    <div class="col-lg-4"><aside class="app-card p-4 order-status-card"><p class="eyebrow mb-2">Order progress</p><h2 class="h5">Status history</h2><ol class="order-status-timeline"><?php foreach ($history as $row): ?><li><strong><?= e($row['to_status']) ?></strong><div class="help-text"><?= e($row['created_at']) ?></div><?php if ($row['note']): ?><div class="small"><?= e($row['note']) ?></div><?php endif; ?></li><?php endforeach; ?></ol><?php if ($order['status'] === 'Completed' && $order['order_type'] === 'normal'): ?><a href="review.php?order=<?= $id ?>" class="btn btn-purple w-100">Review cakes</a><?php endif; ?></aside></div>
  </div>
</main>
<?php page_end();
