<?php
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/storefront.php';
require_once __DIR__ . '/inc/recommendation_service.php';

if (is_logged_in() && (current_user()['role'] ?? '') === 'customer') {
    header('Location: /AI-CAKE/customer_menu.php', true, 302);
    exit;
}

$bestSellers = [];
$recommended = [];
$newCakes = [];
$categories = [];
$cakesByCategory = [];
$loadError = false;

try {
    $bestSellers = $pdo->query('SELECT * FROM cakes WHERE best_sellers = 1 AND available = 1 ORDER BY created_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $newCakes = $pdo->query('SELECT * FROM cakes WHERE is_new = 1 AND available = 1 ORDER BY created_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $categories = $pdo->query('SELECT id, name, picture FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

    try {
        $recommended = recommended_cakes_for_user($pdo,user_id(),12);
    } catch (Throwable $recommendationError) {
        error_log('Recommended cake query unavailable: ' . $recommendationError->getMessage());
    }

    $categoryQuery = $pdo->prepare('SELECT * FROM cakes WHERE category_id = ? AND available = 1 ORDER BY name ASC');
    foreach ($categories as $category) {
        $categoryQuery->execute([(int) $category['id']]);
        $cakesByCategory[(int) $category['id']] = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $error) {
    $loadError = true;
    error_log('Storefront menu load failed: ' . $error->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Browse the full cake menu from Aure Sanchez House of Cakes.">
  <title>Menu | Aure Sanchez House of Cakes</title>
  <link rel="icon" href="/AI-CAKE/uploads/logo/logotab.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="/AI-CAKE/assets/css/storefront.css?v=20260928-3" rel="stylesheet">
</head>
<body class="storefront-page">
<?php storefront_header('menu'); ?>

<main>
  <section class="menu-intro">
    <div class="container storefront-container">
      <span class="storefront-hero-label">Our cake menu</span>
      <h1>Find your next favorite.</h1>
      <p>Browse every available cake and customize it before choosing a pickup schedule at checkout.</p>
    </div>
  </section>

  <section class="storefront-section pt-3" aria-labelledby="ai-menu-title">
    <div class="container storefront-container">
      <div class="ai-storefront-callout">
        <div>
          <h2 id="ai-menu-title">Have a cake idea of your own?</h2>
          <p>Use the AI cake designer to visualize it, save it, and request a custom order.</p>
          <a class="btn storefront-primary-button" href="/AI-CAKE/ai_cake.php">Design with AI</a>
        </div>
        <div class="ai-visual" aria-hidden="true"><div class="ai-visual-inner"><img class="ai-robot-icon" src="/AI-CAKE/robot.svg" width="180" height="180" alt=""></div></div>
      </div>
    </div>
  </section>

  <div class="menu-tools">
    <div class="container storefront-container">
      <nav class="menu-category-nav" aria-label="Cake menu sections">
        <?php if ($bestSellers): ?><a href="#best-sellers">Best sellers</a><?php endif; ?>
        <?php if ($recommended): ?><a href="#recommended">Recommended</a><?php endif; ?>
        <?php if ($newCakes): ?><a href="#new">New cakes</a><?php endif; ?>
        <?php foreach ($categories as $category): ?><a href="#category-<?= (int) $category['id'] ?>"><?= storefront_escape($category['name']) ?></a><?php endforeach; ?>
      </nav>
    </div>
  </div>

  <?php if ($loadError): ?>
    <div class="container storefront-container pt-4"><div class="alert alert-light border" role="alert">The cake catalog is temporarily unavailable. Please refresh in a moment.</div></div>
  <?php endif; ?>

  <div class="container storefront-container">
    <?php if ($bestSellers): ?>
      <section id="best-sellers" class="menu-product-section" aria-labelledby="best-seller-title">
        <div class="storefront-section-header"><h2 id="best-seller-title">Best sellers</h2><p>The cakes customers come back for most often.</p></div>
        <div class="product-grid"><?php foreach ($bestSellers as $cake) storefront_product_card($cake); ?></div>
      </section>
    <?php endif; ?>

    <?php if ($recommended): ?>
      <section id="recommended" class="menu-product-section" aria-labelledby="recommended-title">
        <div class="storefront-section-header"><h2 id="recommended-title">Recommended for you</h2><p>Our preference-aware hybrid recommendation algorithm ranks eligible cakes using your favorites, categories, customizations, and completed purchases. Eligibility requires at least 50 verified reviews and an average above 8.0.</p></div>
        <div class="product-grid"><?php foreach ($recommended as $cake) storefront_product_card($cake); ?></div>
      </section>
    <?php endif; ?>

    <?php if ($newCakes): ?>
      <section id="new" class="menu-product-section" aria-labelledby="new-menu-title">
        <div class="storefront-section-header"><h2 id="new-menu-title">New cakes</h2><p>Recently added cakes now available for pickup orders.</p></div>
        <div class="product-grid"><?php foreach ($newCakes as $cake) storefront_product_card($cake, true); ?></div>
      </section>
    <?php endif; ?>

    <?php foreach ($categories as $category): $cakes = $cakesByCategory[(int) $category['id']] ?? []; ?>
      <section id="category-<?= (int) $category['id'] ?>" class="menu-product-section" aria-labelledby="category-title-<?= (int) $category['id'] ?>">
        <div class="storefront-section-header"><h2 id="category-title-<?= (int) $category['id'] ?>"><?= storefront_escape($category['name']) ?></h2></div>
        <?php if ($cakes): ?>
          <div class="product-grid"><?php foreach ($cakes as $cake) storefront_product_card($cake); ?></div>
        <?php else: ?>
          <div class="empty-storefront">No cakes are currently available in this category.</div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <?php if (!$loadError && !$bestSellers && !$recommended && !$newCakes && !$categories): ?>
      <div class="empty-storefront my-5">No cakes are currently available.</div>
    <?php endif; ?>
  </div>
</main>

<?php storefront_footer(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
