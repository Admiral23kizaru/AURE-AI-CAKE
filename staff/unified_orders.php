<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/page.php';
require_once __DIR__ . '/../inc/order_admin_service.php';
require_once __DIR__ . '/../inc/ai_design.php';

require_staff_or_admin();

if (!defined('AI_CAKE_ORDER_WORKSPACE') && (current_user()['role'] ?? '') === 'admin') {
    $query = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?: '');
    redirect('/AI-CAKE/admin/orders.php' . ($query !== '' ? '?' . $query : ''));
}
$isAdminOrderWorkspace = defined('AI_CAKE_ORDER_WORKSPACE') && AI_CAKE_ORDER_WORKSPACE === 'admin';
$orderListUrl = $isAdminOrderWorkspace ? '/AI-CAKE/admin/orders.php' : '/AI-CAKE/staff/unified_orders.php';
$pickupScheduleUrl = $isAdminOrderWorkspace ? '/AI-CAKE/admin/pickup_schedule.php' : '/AI-CAKE/staff/pickup_schedule.php';
$conversationUrl = $isAdminOrderWorkspace ? '/AI-CAKE/admin/chat.php' : '/AI-CAKE/staff/chat.php';
$workspaceLabel = $isAdminOrderWorkspace ? 'Administrator workspace' : 'Staff workspace';

function unified_orders_query(array $source, bool $includePage = true): string
{
    $params = [];
    $search = mb_substr(trim((string) ($source['search'] ?? '')), 0, 100);
    $status = trim((string) ($source['status'] ?? ''));
    $type = trim((string) ($source['order_type'] ?? ''));
    $date = trim((string) ($source['pickup_date'] ?? ''));
    $time = trim((string) ($source['pickup_time'] ?? ''));
    if ($search !== '') { $params['search'] = $search; }
    if (in_array($status, ORDER_STATUSES, true)) { $params['status'] = $status; }
    if (in_array($type, ['normal', 'ai'], true)) { $params['order_type'] = $type; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $params['pickup_date'] = $date; }
    if (preg_match('/^\d{2}:\d{2}$/', $time)) { $params['pickup_time'] = $time; }
    if ($includePage) { $params['page'] = max(1, (int) ($source['page'] ?? 1)); }
    return http_build_query($params);
}

function unified_orders_redirect_query(): string
{
    $raw = (string) ($_POST['return_query'] ?? '');
    parse_str($raw, $parameters);
    return unified_orders_query(is_array($parameters) ? $parameters : []);
}

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
        } elseif (isset($_POST['legacy_ai_size'])) {
            record_legacy_ai_size($pdo, $id, (string) ($_POST['cake_size'] ?? ''), user_id());
        } elseif (isset($_POST['custom_sms'])) {
            $orderQuery = $pdo->prepare('SELECT customer_phone FROM order_headers WHERE id=?');
            $orderQuery->execute([$id]);
            $phone = $orderQuery->fetchColumn();
            if (!$phone) { throw new RuntimeException('The order has no valid customer phone number.'); }
            queue_custom_sms($pdo, $id, user_id(), (string) $phone, (string) ($_POST['message'] ?? ''), (string) ($_POST['reason'] ?? ''));
        }
        $returnQuery = unified_orders_redirect_query();
        redirect($orderListUrl . ($returnQuery !== '' ? '?' . $returnQuery : ''));
    } catch (Throwable $error) {
        error_log('Staff order action: ' . $error->getMessage());
        $message = $error instanceof PDOException ? 'Unable to update the order.' : ($error instanceof RuntimeException || $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to update the order.');
    }
}

$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
$status = trim((string) ($_GET['status'] ?? ''));
$type = trim((string) ($_GET['order_type'] ?? ''));
$date = trim((string) ($_GET['pickup_date'] ?? ''));
$time = trim((string) ($_GET['pickup_time'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$where = [];
$arguments = [];
if ($search !== '') { $where[] = '(order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)'; $term = '%' . $search . '%'; array_push($arguments, $term, $term, $term); }
if ($status !== '' && in_array($status, ORDER_STATUSES, true)) { $where[] = 'status=?'; $arguments[] = $status; }
if (in_array($type, ['normal', 'ai'], true)) { $where[] = 'order_type=?'; $arguments[] = $type; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $where[] = 'pickup_date=?'; $arguments[] = $date; }
if (preg_match('/^\d{2}:\d{2}$/', $time)) { $where[] = 'TIME_FORMAT(pickup_time,"%H:%i")=?'; $arguments[] = $time; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$count = $pdo->prepare('SELECT COUNT(*) FROM order_headers' . $whereSql);
$count->execute($arguments);
$totalOrders = (int) $count->fetchColumn();
$totalPages = max(1, (int) ceil($totalOrders / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$query = $pdo->prepare('SELECT * FROM order_headers' . $whereSql . ' ORDER BY created_at DESC,id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
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

$filterQuery = unified_orders_query(['search' => $search, 'status' => $status, 'order_type' => $type, 'pickup_date' => $date, 'pickup_time' => $time], false);
$returnQuery = unified_orders_query(['search' => $search, 'status' => $status, 'order_type' => $type, 'pickup_date' => $date, 'pickup_time' => $time, 'page' => $page]);
$pageUrl = static function (int $targetPage) use ($filterQuery, $orderListUrl): string {
    $query = $filterQuery === '' ? '' : $filterQuery . '&';
    return $orderListUrl . '?' . $query . 'page=' . $targetPage;
};
$firstResult = $totalOrders === 0 ? 0 : $offset + 1;
$lastResult = min($offset + $perPage, $totalOrders);

page_start('Order management');
?>
<main class="container-fluid px-3 px-lg-5 py-4">
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div><div class="eyebrow"><?= e($workspaceLabel) ?></div><h1 class="h3 mb-0">Order management</h1></div>
    <a href="<?= e($pickupScheduleUrl) ?>" class="btn btn-outline-primary">Pickup schedule</a>
  </div>
  <?php if ($message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
  <form class="app-card p-3 mb-4 row g-2" method="get">
    <div class="col-md-3"><label class="form-label" for="search">Order or customer</label><input id="search" name="search" class="form-control" value="<?= e($search) ?>"></div><div class="col-md-3"><label class="form-label" for="pickup_date">Pickup date</label><input id="pickup_date" type="date" name="pickup_date" class="form-control" value="<?= e($date) ?>"></div>
    <div class="col-md-2"><label class="form-label" for="pickup_time">Time</label><input id="pickup_time" type="time" name="pickup_time" class="form-control" value="<?= e($time) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select"><option value="">All statuses</option><?php foreach (ORDER_STATUSES as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label" for="order_type">Type</label><select id="order_type" name="order_type" class="form-select"><option value="">All types</option><option value="normal" <?= $type === 'normal' ? 'selected' : '' ?>>Normal</option><option value="ai" <?= $type === 'ai' ? 'selected' : '' ?>>AI</option></select></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-purple w-100">Filter</button></div>
  </form>

  <div class="order-list-meta mb-3" role="status">Showing <?= $firstResult ?>–<?= $lastResult ?> of <?= $totalOrders ?> order<?= $totalOrders === 1 ? '' : 's' ?> <span aria-hidden="true">·</span> Newest first</div>
  <?php if (!$orders): ?><div class="app-card p-5 text-center"><h2 class="h5">No orders match these filters</h2></div><?php endif; ?>
  <div class="row g-4">
    <?php foreach ($orders as $order): $next = ['Pending' => 'Confirmed', 'Confirmed' => 'Preparing', 'Preparing' => 'Ready for Pickup', 'Ready for Pickup' => 'Completed'][$order['status']] ?? null; ?>
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
              <?php $imageUrl = ai_design_image_url($order['ai']['picture']); $hasImage = $imageUrl !== null && ai_design_image_exists($order['ai']['picture']); $hasSize = trim((string) $order['ai']['cake_size']) !== ''; ?>
              <div class="row g-3 align-items-start"><div class="col-4"><div class="ai-design-thumbnail ai-design-thumbnail--dashboard"><?php if ($hasImage): ?><img src="<?= e($imageUrl) ?>" class="img-fluid" alt="AI cake design" loading="lazy" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');"><?php endif; ?><span class="ai-design-thumbnail__fallback<?= $hasImage ? ' d-none' : '' ?>" role="img" aria-label="AI cake design image unavailable"><i class="bi bi-image" aria-hidden="true"></i><span>Image unavailable</span></span></div></div><div class="col-8"><strong><?= e($hasSize ? $order['ai']['cake_size'] : 'Size not recorded') ?></strong><p class="small mb-1"><strong>Design:</strong> <?= e($order['ai']['personalize']) ?></p><?php if ($order['customer_note']): ?><p class="small mb-2"><strong>Note:</strong> <?= e($order['customer_note']) ?></p><?php endif; ?><a href="<?= e($conversationUrl) ?>?order_number=<?= urlencode($order['order_number']) ?>">Open customer conversation</a><?php if (!$hasSize): ?><form method="post" class="row g-2 mt-2"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><div class="col"><label class="visually-hidden" for="legacy-size-<?= (int) $order['id'] ?>">Record legacy AI cake size</label><select id="legacy-size-<?= (int) $order['id'] ?>" name="cake_size" class="form-select form-select-sm" required><option value="">Record known size</option><option>Small</option><option>Medium</option><option>Large</option></select></div><div class="col-auto"><button name="legacy_ai_size" class="btn btn-sm btn-outline-primary">Save size</button></div></form><?php endif; ?></div></div>
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

          <?php if ($order['order_type'] === 'ai' && $order['status'] === 'Pending'): ?><form method="post" class="row g-2 mb-2"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><div class="col"><label class="visually-hidden" for="price-<?= (int) $order['id'] ?>">AI cake price</label><input id="price-<?= (int) $order['id'] ?>" type="number" min="0.01" step="0.01" name="price" class="form-control" value="<?= (float) $order['total'] > 0 ? e($order['total']) : '' ?>" placeholder="AI cake price" required></div><div class="col-auto"><button name="ai_price" class="btn btn-outline-primary">Set price</button></div></form><?php endif; ?>
          <?php if ($order['status'] === 'Pending' && (float) $order['original_total'] > 0): ?><form method="post" class="row g-2 mb-2"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><div class="col-3"><select name="discount_type" class="form-select" aria-label="Discount type"><option value="fixed">&#8369;</option><option value="percentage">%</option></select></div><div class="col-3"><input type="number" min="0.01" step="0.01" name="discount_value" class="form-control" aria-label="Discount value" required></div><div class="col"><input name="reason" class="form-control" maxlength="500" placeholder="Discount reason" required></div><div class="col-auto"><button name="discount" class="btn btn-outline-primary">Apply</button></div></form><?php endif; ?>
          <?php if ($next): ?><form method="post" class="row g-2 mt-3"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="status" value="<?= e($next) ?>"><?php if ($next === 'Completed'): ?><div class="col"><input name="claim_code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Customer claim code" required></div><?php endif; ?><div class="col-auto"><button class="btn btn-purple">Move to <?= e($next) ?></button></div></form><?php endif; ?>
          <?php if (!in_array($order['status'], ['Completed', 'Cancelled'], true)): ?><form method="post" class="row g-2 mt-2" onsubmit="return confirm('Cancel this order?')"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><div class="col"><input name="reason" class="form-control" maxlength="500" placeholder="Cancellation reason" required></div><div class="col-auto"><button name="cancel" class="btn btn-outline-danger">Cancel</button></div></form><?php endif; ?>
          <details class="mt-3"><summary>Send a customer message</summary><form method="post" class="mt-2"><input type="hidden" name="return_query" value="<?= e($returnQuery) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><label class="form-label" for="sms-<?= (int) $order['id'] ?>">Message (one SMS segment)</label><textarea id="sms-<?= (int) $order['id'] ?>" name="message" maxlength="160" class="form-control" required></textarea><label class="form-label mt-2" for="sms-reason-<?= (int) $order['id'] ?>">Reason for sending</label><input id="sms-reason-<?= (int) $order['id'] ?>" name="reason" maxlength="255" class="form-control" required><button name="custom_sms" class="btn btn-outline-primary mt-2">Queue message</button></form></details>
          <details class="mt-3"><summary>Status history</summary><ol class="small mt-2"><?php foreach ($order['history'] as $history): ?><li><strong><?= e($history['to_status']) ?></strong> &middot; <?= e($history['created_at']) ?><?php if ($history['note']): ?><div><?= e($history['note']) ?></div><?php endif; ?></li><?php endforeach; ?></ol></details>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($totalOrders > 0): ?>
    <?php $startPage = max(1, $page - 2); $endPage = min($totalPages, $page + 2); ?>
    <nav class="orders-pagination mt-4" aria-label="Order pages"><ul class="pagination mb-0"><li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><?php if ($page > 1): ?><a class="page-link" href="<?= e($pageUrl($page - 1)) ?>" aria-label="Previous order page">Previous</a><?php else: ?><span class="page-link" aria-disabled="true">Previous</span><?php endif; ?></li><?php for ($number = $startPage; $number <= $endPage; $number++): ?><li class="page-item<?= $number === $page ? ' active' : '' ?>"><?php if ($number === $page): ?><span class="page-link" aria-current="page"><?= $number ?></span><?php else: ?><a class="page-link" href="<?= e($pageUrl($number)) ?>" aria-label="Order page <?= $number ?>"><?= $number ?></a><?php endif; ?></li><?php endfor; ?><li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>"><?php if ($page < $totalPages): ?><a class="page-link" href="<?= e($pageUrl($page + 1)) ?>" aria-label="Next order page">Next</a><?php else: ?><span class="page-link" aria-disabled="true">Next</span><?php endif; ?></li></ul></nav>
  <?php endif; ?>
</main>
<?php page_end();