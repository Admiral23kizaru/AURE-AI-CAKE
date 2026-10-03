<?php
declare(strict_types=1);

require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/page.php';

require_staff_or_admin();

$number = trim((string) ($_GET['order_number'] ?? $_POST['order_number'] ?? ''));
$order = null;
$msg = '';
if ($number !== '') {
    $q = $pdo->prepare('SELECT * FROM ai_cake_orders WHERE order_number=?');
    $q->execute([$number]);
    $order = $q->fetch();
    if (!$order) {
        $msg = 'AI order not found.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    require_post_csrf();
    if (!$order) {
        throw new RuntimeException('Order not found.');
    }
    $text = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 1500);
    if ($text === '') {
        $msg = 'Enter a message.';
    } else {
        $pdo->prepare("INSERT INTO ai_cake_messages(order_id,order_number,sender,message) VALUES(?,?,'owner',?)")->execute([$order['id'], $order['order_number'], $text]);
        redirect('chat.php?order_number=' . urlencode($number));
    }
}
$messages = [];
if ($order) {
    $q = $pdo->prepare('SELECT * FROM ai_cake_messages WHERE order_id=? ORDER BY created_at');
    $q->execute([$order['id']]);
    $messages = $q->fetchAll();
}

page_start('AI order chat');
?>
<main class="container py-4 py-lg-5 staff-conversation-page" style="max-width:1000px">
  <header class="conversation-page-heading">
    <div><p class="eyebrow">Staff workspace</p><h1>AI cake conversation</h1><p class="help-text mb-0">Review the customer’s design request and keep the order conversation in one place.</p></div>
    <a href="unified_orders.php?order_type=ai" class="btn btn-outline-primary">Manage AI orders</a>
  </header>
  <form method="get" class="app-card p-3 my-4 conversation-search-form">
    <div class="flex-grow-1"><label for="order_number" class="visually-hidden">AI order number</label><input id="order_number" name="order_number" value="<?= e($number) ?>" class="form-control" placeholder="AI order number" required></div>
    <button class="btn btn-purple">Open conversation</button>
  </form>
  <?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($order): ?>
    <section class="app-card p-4 p-lg-5 conversation-card staff-conversation-card">
      <header class="conversation-header">
        <div><p class="eyebrow mb-2">Order <?= e($order['order_number']) ?></p><h2 class="h3 mb-1"><?= e($order['customer_name']) ?></h2><p class="help-text mb-0">Customer design discussion</p></div>
        <a href="unified_orders.php?order_type=ai" class="btn btn-outline-primary">Pricing and status</a>
      </header>
      <div class="conversation-thread conversation-thread--staff" role="log" aria-live="polite" aria-label="Customer conversation">
        <?php if (!$messages): ?><p class="conversation-thread__empty">No messages yet. Reply when the customer needs design guidance.</p><?php endif; ?>
        <?php foreach ($messages as $message): ?><article class="conversation-message <?= $message['sender'] === 'owner' ? 'is-outgoing' : 'is-incoming' ?>"><div class="conversation-message__bubble"><?= nl2br(e($message['message'])) ?></div><time class="conversation-message__time" datetime="<?= e($message['created_at']) ?>"><?= e($message['created_at']) ?></time></article><?php endforeach; ?>
      </div>
      <form method="post" class="conversation-composer conversation-composer--staff">
        <?= csrf_field() ?><input type="hidden" name="order_number" value="<?= e($number) ?>">
        <label for="message" class="visually-hidden">Reply to customer</label>
        <textarea id="message" name="message" maxlength="1500" class="form-control" placeholder="Write a reply to the customer" required></textarea>
        <button name="send" class="btn btn-purple" type="submit"><i class="bi bi-send" aria-hidden="true"></i><span>Send reply</span></button>
      </form>
    </section>
  <?php endif; ?>
</main>
<?php page_end();
