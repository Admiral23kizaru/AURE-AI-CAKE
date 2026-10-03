<?php
declare(strict_types=1);

require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/page.php';
require __DIR__ . '/../inc/order_admin_service.php';

require_staff_or_admin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    try {
        $id = (int) ($_POST['id'] ?? 0);
        if (isset($_POST['status'])) {
            update_order_status($pdo, $id, (string) $_POST['status'], user_id(), $_POST['claim_code'] ?? null);
        } elseif (isset($_POST['cancel'])) {
            cancel_order($pdo, $id, user_id(), (string) ($_POST['reason'] ?? ''), true);
        } elseif (isset($_POST['ai_price'])) {
            set_ai_price($pdo, $id, (float) ($_POST['price'] ?? 0), user_id());
        } elseif (isset($_POST['discount'])) {
            apply_discount($pdo, $id, (string) ($_POST['discount_type'] ?? ''), (float) ($_POST['discount_value'] ?? 0), (string) ($_POST['reason'] ?? ''), user_id());
        } elseif (isset($_POST['custom_sms'])) {
            $orderQuery=$pdo->prepare('SELECT customer_phone FROM order_headers WHERE id=?');$orderQuery->execute([$id]);$phone=$orderQuery->fetchColumn();if(!$phone)throw new RuntimeException('The order has no valid customer phone number.');
            queue_custom_sms($pdo,$id,user_id(),(string)$phone,(string)($_POST['message']??''),(string)($_POST['reason']??''));
        }
        redirect('unified_orders.php');
    } catch (Throwable $error) {
        error_log('Staff order action: ' . $error->getMessage());
        $message = $error instanceof PDOException ? 'Unable to update the order.' : ($error instanceof RuntimeException || $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to update the order.');
    }
}

$where = [];
$arguments = [];
$status = trim((string) ($_GET['status'] ?? ''));
$type = trim((string) ($_GET['order_type'] ?? ''));
$date = trim((string) ($_GET['pickup_date'] ?? ''));
$time = trim((string) ($_GET['pickup_time'] ?? ''));
$search=mb_substr(trim((string)($_GET['search']??'')),0,100);
if($search!==''){$where[]='(order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)';$term='%'.$search.'%';array_push($arguments,$term,$term,$term);}
if ($status !== '' && in_array($status, ORDER_STATUSES, true)) {
    $where[] = 'status=?';
    $arguments[] = $status;
}
if (in_array($type, ['normal', 'ai'], true)) {
    $where[] = 'order_type=?';
    $arguments[] = $type;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $where[] = 'pickup_date=?';
    $arguments[] = $date;
}
if (preg_match('/^\d{2}:\d{2}$/', $time)) {
    $where[] = 'TIME_FORMAT(pickup_time,"%H:%i")=?';
    $arguments[] = $time;
}
$sql = 'SELECT * FROM order_headers' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY pickup_date,pickup_time,created_at DESC';
$query = $pdo->prepare($sql);
$query->execute($arguments);
$orders = $query->fetchAll();

foreach ($orders as &$order) {
    $order['items'] = [];
    $order['ai'] = null;
    if ($order['order_type'] === 'normal' && $order['normal_order_id']) {
        $items = $pdo->prepare('SELECT oi.*,c.name,c.picture FROM order_items oi LEFT JOIN cakes c ON c.id=oi.cake_id WHERE oi.order_id=?');
        $items->execute([$order['normal_order_id']]);
        $order['items'] = $items->fetchAll();
        foreach ($order['items'] as &$item) {
            $options = $pdo->prepare('SELECT option_type,option_name,price_snapshot,quantity FROM order_item_options WHERE order_item_id=? ORDER BY id');
            $options->execute([$item['id']]);
            $item['options'] = $options->fetchAll();
        }
        unset($item);
    } elseif ($order['ai_order_id']) {
        $ai = $pdo->prepare('SELECT picture,personalize,cake_size,customer_note FROM ai_cake_orders WHERE id=?');
        $ai->execute([$order['ai_order_id']]);
        $order['ai'] = $ai->fetch() ?: null;
    }
    $history = $pdo->prepare('SELECT from_status,to_status,note,created_at FROM order_status_history WHERE order_header_id=? ORDER BY id');
    $history->execute([$order['id']]);
    $order['history'] = $history->fetchAll();
}
unset($order);

page_start('Order management');
?>
<main class="container-fluid px-3 px-lg-5 py-4">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div><div class="eyebrow">Staff workspace</div><h1 class="h3 mb-0">Order management</h1></div>
    <a href="pickup_schedule.php" class="btn btn-outline-primary">Pickup schedule</a>
  </div>
  <?php if ($message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
  <form class="app-card p-3 mb-4 row g-2" method="get">
    <div class="col-md-3"><label class="form-label" for="search">Order or customer</label><input id="search" name="search" class="form-control" value="<?=e($search)?>"></div><div class="col-md-3"><label class="form-label" for="pickup_date">Pickup date</label><input id="pickup_date" type="date" name="pickup_date" class="form-control" value="<?= e($date) ?>"></div>
    <div class="col-md-2"><label class="form-label" for="pickup_time">Time</label><input id="pickup_time" type="time" name="pickup_time" class="form-control" value="<?= e($time) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select"><option value="">All statuses</option><?php foreach (ORDER_STATUSES as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label" for="order_type">Type</label><select id="order_type" name="order_type" class="form-select"><option value="">All types</option><option value="normal" <?= $type === 'normal' ? 'selected' : '' ?>>Normal</option><option value="ai" <?= $type === 'ai' ? 'selected' : '' ?>>AI</option></select></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-purple w-100">Filter</button></div>
  </form>

  <?php if (!$orders): ?><div class="app-card p-5 text-center"><h2 class="h5">No orders match these filters</h2></div><?php endif; ?>
  <div class="row g-4">
    <?php foreach ($orders as $order): $next = ['Pending'=>'Confirmed','Confirmed'=>'Preparing','Preparing'=>'Ready for Pickup','Ready for Pickup'=>'Completed'][$order['status']] ?? null; ?>
      <div class="col-xl-6">
        <article class="app-card p-4 h-100">
          <div class="d-flex justify-content-between gap-3">
            <div><div class="eyebrow"><?= e(strtoupper($order['order_type'])) ?> order</div><h2 class="h5 mb-1"><?= e($order['order_number']) ?></h2><div><?= e($order['customer_name']) ?> &middot; <?= e($order['customer_phone']) ?></div><small class="help-text"><?= e($order['customer_email']) ?></small></div>
            <span class="status-pill align-self-start"><?= e($order['status']) ?></span>
          </div>

          <div class="border-top border-bottom py-3 my-3">
            <?php if ($order['order_type'] === 'normal'): ?>
              <?php foreach ($order['items'] as $item): ?>
                <div class="mb-3"><strong><?= e($item['name'] ?? 'Cake') ?> x <?= (int) $item['qty'] ?></strong><div class="small">Base price: &#8369;<?= number_format((float) $item['price'], 2) ?></div><?php foreach ($item['options'] as $option): ?><div class="small text-muted"><?= e($option['option_type']) ?>: <?= e($option['option_name']) ?><?php if ((float) $option['price_snapshot'] !== 0.0): ?> (&#8369;<?= number_format((float) $option['price_snapshot'], 2) ?> x <?= (int) $option['quantity'] ?>)<?php endif; ?></div><?php endforeach; ?></div>
              <?php endforeach; ?>
            <?php elseif ($order['ai']): ?>
              <div class="row g-3"><div class="col-4"><?php if ($order['ai']['picture']): ?><img src="../uploads/ai-cakes/<?= e(basename((string) $order['ai']['picture'])) ?>" class="img-fluid rounded" alt="AI cake design"><?php endif; ?></div><div class="col-8"><strong><?= e($order['ai']['cake_size'] ?: 'Size pending') ?></strong><p class="small mb-1"><strong>Design:</strong> <?= e($order['ai']['personalize']) ?></p><?php if ($order['customer_note']): ?><p class="small mb-2"><strong>Note:</strong> <?= e($order['customer_note']) ?></p><?php endif; ?><a href="chat.php?order_number=<?= urlencode($order['order_number']) ?>">Open customer conversation</a></div></div>
            <?php endif; ?>
          </div>

          <dl class="row small mb-3">
            <dt class="col-4">Pickup</dt><dd class="col-8"><?= e($order['pickup_date']) ?> at <?= e(substr((string) $order['pickup_time'], 0, 5)) ?></dd>
            <dt class="col-4">Shop</dt><dd class="col-8"><?= e($order['store_name']) ?></dd>
            <dt class="col-4">Subtotal</dt><dd class="col-8">&#8369;<?= number_format((float) $order['subtotal'], 2) ?></dd>
            <dt class="col-4">Discount</dt><dd class="col-8">-&#8369;<?= number_format((float) $order['discount_total'], 2) ?></dd>
            <dt class="col-4">Total</dt><dd class="col-8 fw-bold">&#8369;<?= number_format((float) $order['total'], 2) ?></dd>
            <dt class="col-4">Cash status</dt><dd class="col-8"><?= e(ucfirst($order['payment_status'])) ?></dd>
          </dl>

          <?php if ($order['order_type'] === 'ai' && $order['status'] === 'Pending'): ?>
            <form method="post" class="row g-2 mb-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><div class="col"><label class="visually-hidden" for="price-<?= (int) $order['id'] ?>">AI cake price</label><input id="price-<?= (int) $order['id'] ?>" type="number" min="0.01" step="0.01" name="price" class="form-control" value="<?= (float) $order['total'] > 0 ? e($order['total']) : '' ?>" placeholder="AI cake price" required></div><div class="col-auto"><button name="ai_price" class="btn btn-outline-primary">Set price</button></div></form>
          <?php endif; ?>

          <?php if ($order['status'] === 'Pending' && (float) $order['original_total'] > 0): ?>
            <form method="post" class="row g-2 mb-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><div class="col-3"><select name="discount_type" class="form-select" aria-label="Discount type"><option value="fixed">&#8369;</option><option value="percentage">%</option></select></div><div class="col-3"><input type="number" min="0.01" step="0.01" name="discount_value" class="form-control" aria-label="Discount value" required></div><div class="col"><input name="reason" class="form-control" maxlength="500" placeholder="Discount reason" required></div><div class="col-auto"><button name="discount" class="btn btn-outline-primary">Apply</button></div></form>
          <?php endif; ?>

          <?php if ($next): ?>
            <form method="post" class="row g-2 mt-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="status" value="<?= e($next) ?>"><?php if ($next === 'Completed'): ?><div class="col"><input name="claim_code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Customer claim code" required></div><?php endif; ?><div class="col-auto"><button class="btn btn-purple">Move to <?= e($next) ?></button></div></form>
          <?php endif; ?>

          <?php if (!in_array($order['status'], ['Completed', 'Cancelled'], true)): ?>
            <form method="post" class="row g-2 mt-2" onsubmit="return confirm('Cancel this order?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><div class="col"><input name="reason" class="form-control" maxlength="500" placeholder="Cancellation reason" required></div><div class="col-auto"><button name="cancel" class="btn btn-outline-danger">Cancel</button></div></form>
          <?php endif; ?>

          <details class="mt-3"><summary>Send a customer message</summary><form method="post" class="mt-2"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$order['id']?>"><label class="form-label" for="sms-<?=(int)$order['id']?>">Message (one SMS segment)</label><textarea id="sms-<?=(int)$order['id']?>" name="message" maxlength="160" class="form-control" required></textarea><label class="form-label mt-2" for="sms-reason-<?=(int)$order['id']?>">Reason for sending</label><input id="sms-reason-<?=(int)$order['id']?>" name="reason" maxlength="255" class="form-control" required><button name="custom_sms" class="btn btn-outline-primary mt-2">Queue message</button></form></details>

          <details class="mt-3"><summary>Status history</summary><ol class="small mt-2"><?php foreach ($order['history'] as $history): ?><li><strong><?= e($history['to_status']) ?></strong> &middot; <?= e($history['created_at']) ?><?php if ($history['note']): ?><div><?= e($history['note']) ?></div><?php endif; ?></li><?php endforeach; ?></ol></details>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<?php page_end();
