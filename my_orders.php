<?php
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require_customer();

$query = $pdo->prepare('SELECT * FROM order_headers WHERE user_id=? ORDER BY created_at DESC');
$query->execute([user_id()]);
$orders = $query->fetchAll();
page_start('My orders');
?>

<main class="container py-4 py-lg-5 customer-workspace">
  <section class="customer-hero">
    <div><p class="eyebrow">Your cake account</p><h1>Orders and pickup</h1><p>Track every cake order, view your pickup details, and keep your next celebration moving.</p></div>
    <a class="btn btn-purple" href="customer_menu.php"><i class="bi bi-cake2 me-2" aria-hidden="true"></i>Order a cake</a>
  </section>

  <nav class="customer-workspace-nav" aria-label="Customer workspace">
    <a class="is-active" href="my_orders.php"><i class="bi bi-receipt" aria-hidden="true"></i>Orders</a><a href="customer_menu.php"><i class="bi bi-cake2" aria-hidden="true"></i>Menu</a>
    <a href="favorites.php"><i class="bi bi-heart" aria-hidden="true"></i>Favorites</a>
    <a href="account.php"><i class="bi bi-person" aria-hidden="true"></i>Profile</a>
    <a href="ai_cake.php"><i class="bi bi-stars" aria-hidden="true"></i>AI cake design</a>
  </nav>

  <?php if (!$orders): ?>
    <section class="app-card customer-empty-state text-center"><span class="customer-empty-state__icon"><i class="bi bi-box2-heart" aria-hidden="true"></i></span><h2>No linked orders yet</h2><p class="help-text">New orders placed while signed in will appear here. You can also securely link an older guest order.</p><div class="d-flex flex-wrap justify-content-center gap-2"><a class="btn btn-purple" href="customer_menu.php">Browse cakes</a><a class="btn btn-outline-primary" href="legacy_claim.php">Claim a guest order</a></div></section>
  <?php else: ?>
    <section class="customer-order-grid" aria-label="Your orders">
      <?php foreach ($orders as $order): ?>
        <article class="app-card customer-order-card">
          <div class="customer-order-card__top"><div><p class="eyebrow mb-1"><?= e($order['order_type'] === 'ai' ? 'AI cake order' : 'Cake order') ?></p><h2><?= e($order['order_number']) ?></h2></div><span class="status-pill"><?= e($order['status']) ?></span></div>
          <dl class="customer-order-card__details"><div><dt><i class="bi bi-calendar3" aria-hidden="true"></i>Pickup</dt><dd><?= e($order['pickup_date']) ?> · <?= e(substr((string) $order['pickup_time'], 0, 5)) ?></dd></div><div><dt><i class="bi bi-wallet2" aria-hidden="true"></i>Total</dt><dd>₱<?= number_format((float) $order['total'], 2) ?></dd></div><div><dt><i class="bi bi-credit-card" aria-hidden="true"></i>Payment</dt><dd><?= e(ucfirst((string) $order['payment_status'])) ?></dd></div></dl>
          <a class="btn btn-outline-primary w-100" href="order_details.php?id=<?= (int) $order['id'] ?>">Track and view details <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>

<?php page_end(); ?>
