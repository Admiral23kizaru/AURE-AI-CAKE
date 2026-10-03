<?php
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/storefront.php';
require_once __DIR__ . '/inc/recommendation_service.php';
require_customer();

$bestSellers = $newCakes = $recommended = $categories = [];
$cakesByCategory = [];
$loadError = false;

try {
    $bestSellers = $pdo->query('SELECT * FROM cakes WHERE best_sellers=1 AND available=1 ORDER BY created_at DESC,id DESC')->fetchAll();
    $newCakes = $pdo->query('SELECT * FROM cakes WHERE is_new=1 AND available=1 ORDER BY created_at DESC,id DESC')->fetchAll();
    $categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
    $recommended = recommended_cakes_for_user($pdo, user_id(), 12);
    $byCategory = $pdo->prepare('SELECT * FROM cakes WHERE category_id=? AND available=1 ORDER BY name');
    foreach ($categories as $category) {
        $byCategory->execute([(int) $category['id']]);
        $cakesByCategory[(int) $category['id']] = $byCategory->fetchAll();
    }
} catch (Throwable $error) {
    $loadError = true;
    error_log('Customer menu load failed: ' . $error->getMessage());
}

function customer_menu_cards(array $cakes, bool $showNew = false): void
{
    ?>
    <div class="customer-menu-grid">
      <?php foreach ($cakes as $cake): $id = (int) $cake['id']; ?>
        <article class="app-card customer-menu-card">
          <a class="customer-menu-card__image" href="cake_size.php?id=<?= $id ?>" aria-label="Customize <?= e($cake['name']) ?>">
            <img src="<?= e(storefront_product_image($cake['picture'] ?? '')) ?>" alt="<?= e($cake['name']) ?>" loading="lazy" width="640" height="640">
          </a>
          <div class="customer-menu-card__body">
            <?php if ($showNew && !empty($cake['is_new'])): ?><span class="customer-menu-card__tag">New</span><?php endif; ?>
            <h3><?= e($cake['name']) ?></h3>
            <div class="customer-menu-card__footer"><strong>&#8369;<?= number_format((float) $cake['price'], 2) ?></strong><a class="btn btn-outline-primary" href="cake_size.php?id=<?= $id ?>">Customize <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}

page_start('Cake menu');
?>
<main class="container py-4 py-lg-5 customer-workspace">
  <section class="customer-menu-hero">
    <div><p class="eyebrow">Customer menu</p><h1>Choose your cake</h1><p>Browse available cakes, then choose your customizations before adding one to your cart.</p></div>
    <a class="btn btn-outline-primary" href="cart.php"><i class="bi bi-bag me-2" aria-hidden="true"></i>Open cart</a>
  </section>
  <nav class="customer-workspace-nav" aria-label="Customer workspace"><a href="my_orders.php"><i class="bi bi-receipt" aria-hidden="true"></i>Orders</a><a class="is-active" href="customer_menu.php"><i class="bi bi-cake2" aria-hidden="true"></i>Menu</a><a href="favorites.php"><i class="bi bi-heart" aria-hidden="true"></i>Favorites</a><a href="account.php"><i class="bi bi-person" aria-hidden="true"></i>Profile</a><a href="ai_cake.php"><i class="bi bi-stars" aria-hidden="true"></i>AI cake design</a></nav>
  <?php if ($loadError): ?><div class="alert alert-danger" role="alert">The cake menu is temporarily unavailable. Please refresh and try again.</div><?php endif; ?>
  <nav class="customer-menu-sections" aria-label="Cake categories"><?php if ($bestSellers): ?><a href="#best-sellers">Best sellers</a><?php endif; ?><?php if ($recommended): ?><a href="#recommended">Recommended</a><?php endif; ?><?php if ($newCakes): ?><a href="#new-cakes">New cakes</a><?php endif; ?><?php foreach ($categories as $category): ?><a href="#category-<?= (int) $category['id'] ?>"><?= e($category['name']) ?></a><?php endforeach; ?></nav>
  <?php if ($bestSellers): ?><section id="best-sellers" class="customer-menu-section"><div class="customer-menu-section__heading"><div><p class="eyebrow">Popular choices</p><h2>Best sellers</h2></div><p>Customer favorites available for pickup.</p></div><?php customer_menu_cards($bestSellers); ?></section><?php endif; ?>
  <?php if ($recommended): ?><section id="recommended" class="customer-menu-section"><div class="customer-menu-section__heading"><div><p class="eyebrow">For you</p><h2>Recommended cakes</h2></div><p>Based on eligible verified ratings and your saved or completed orders.</p></div><?php customer_menu_cards($recommended); ?></section><?php endif; ?>
  <?php if ($newCakes): ?><section id="new-cakes" class="customer-menu-section"><div class="customer-menu-section__heading"><div><p class="eyebrow">Freshly added</p><h2>New cakes</h2></div></div><?php customer_menu_cards($newCakes, true); ?></section><?php endif; ?>
  <?php foreach ($categories as $category): $cakes = $cakesByCategory[(int) $category['id']] ?? []; ?><section id="category-<?= (int) $category['id'] ?>" class="customer-menu-section"><div class="customer-menu-section__heading"><div><p class="eyebrow">Cake collection</p><h2><?= e($category['name']) ?></h2></div></div><?php if ($cakes): customer_menu_cards($cakes); else: ?><div class="app-card customer-menu-empty">No cakes are available in this category right now.</div><?php endif; ?></section><?php endforeach; ?>
  <?php if (!$loadError && !$bestSellers && !$recommended && !$newCakes && !$categories): ?><section class="app-card customer-menu-empty">No cakes are available right now. Please check back soon.</section><?php endif; ?>
</main>
<?php page_end();