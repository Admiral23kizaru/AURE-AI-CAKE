<?php
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/storefront.php';

$categories = [];
$newCakes = [];
$bestSellers = [];
$loadError = false;

try {
    $categories = $pdo->query('SELECT id, name, picture FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    $newCakes = $pdo->query('SELECT * FROM cakes WHERE is_new = 1 AND available = 1 ORDER BY created_at DESC, id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
    $bestSellers = $pdo->query('SELECT * FROM cakes WHERE best_sellers = 1 AND available = 1 ORDER BY created_at DESC, id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    $loadError = true;
    error_log('Storefront landing load failed: ' . $error->getMessage());
}

$heroCakes = array_values(array_merge($bestSellers, $newCakes));
$heroPrimary = $heroCakes[0]['picture'] ?? '';
$heroSecondary = $heroCakes[1]['picture'] ?? $heroPrimary;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Browse cakes for pickup from Aure Sanchez House of Cakes or create a custom AI cake design.">
  <title>Aure Sanchez House of Cakes</title>
  <link rel="icon" href="/AI-CAKE/uploads/logo/logotab.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="/AI-CAKE/assets/css/storefront.css?v=20260928-3" rel="stylesheet">
</head>
<body class="storefront-page">
<?php storefront_header('home'); ?>

<main>
  <section class="storefront-hero" aria-labelledby="hero-title">
    <div class="container storefront-container">
      <div class="storefront-hero-grid">
        <div class="storefront-hero-copy">
          <span class="storefront-hero-label">Made for your moment</span>
          <h1 id="hero-title">Cakes worth celebrating.</h1>
          <p>Choose a house favorite, schedule your pickup, or turn your own idea into a custom cake.</p>
          <div class="storefront-hero-actions">
            <a class="btn storefront-primary-button" href="/AI-CAKE/view-all.php">Browse cakes</a>
            <a class="btn storefront-secondary-button" href="/AI-CAKE/ai_cake.php">Design with AI</a>
          </div>
        </div>
        <div class="storefront-hero-media">
          <img src="<?= storefront_escape(storefront_product_image($heroPrimary)) ?>" width="900" height="900" alt="Featured cake from Aure Sanchez House of Cakes" fetchpriority="high">
          <?php if ($heroSecondary !== ''): ?>
            <img class="hero-secondary-photo" src="<?= storefront_escape(storefront_product_image($heroSecondary)) ?>" width="260" height="260" alt="Another customer favorite">
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <?php if ($loadError): ?>
    <div class="container storefront-container"><div class="alert alert-light border" role="alert">The cake catalog is temporarily unavailable. Please refresh in a moment.</div></div>
  <?php endif; ?>

  <section class="storefront-section" aria-labelledby="category-title">
    <div class="container storefront-container">
      <div class="section-heading-row">
        <h2 id="category-title">Choose your kind of cake</h2>
        <a class="section-link" href="/AI-CAKE/view-all.php">See the full menu</a>
      </div>
      <?php if ($categories): ?>
        <div class="category-rail" role="list">
          <?php foreach ($categories as $category): ?>
            <a class="category-link" role="listitem" href="/AI-CAKE/view-all.php#category-<?= (int) $category['id'] ?>">
              <img src="<?= storefront_escape(storefront_product_image('categories/' . ($category['picture'] ?? ''))) ?>" width="48" height="48" alt="">
              <span><?= storefront_escape($category['name']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-storefront">Categories will appear here when they are available.</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="storefront-section" aria-labelledby="new-title">
    <div class="container storefront-container">
      <div class="section-heading-row">
        <h2 id="new-title">Fresh from the kitchen</h2>
        <a class="section-link" href="/AI-CAKE/view-all.php#new">View all new cakes</a>
      </div>
      <?php if ($newCakes): ?>
        <div class="product-grid">
          <?php foreach (array_slice($newCakes, 0, 4) as $cake) storefront_product_card($cake, true); ?>
        </div>
      <?php else: ?>
        <div class="empty-storefront">New cakes will be added here soon.</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="storefront-section" aria-labelledby="favorite-title">
    <div class="container storefront-container">
      <div class="section-heading-row">
        <h2 id="favorite-title">Customer favorites</h2>
        <a class="section-link" href="/AI-CAKE/view-all.php#best-sellers">See all favorites</a>
      </div>
      <?php if ($bestSellers): ?>
        <div class="product-grid">
          <?php foreach (array_slice($bestSellers, 0, 4) as $cake) storefront_product_card($cake); ?>
        </div>
      <?php else: ?>
        <div class="empty-storefront">Best sellers will appear after products are marked by the shop.</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="storefront-section" aria-labelledby="ai-title">
    <div class="container storefront-container">
      <div class="ai-storefront-callout">
        <div>
          <h2 id="ai-title">Start with an idea. See your cake.</h2>
          <p>Describe the look you want, generate a design, then submit it for pricing and pickup scheduling.</p>
          <a class="btn storefront-primary-button" href="/AI-CAKE/ai_cake.php">Create an AI cake</a>
        </div>
        <div class="ai-visual" aria-hidden="true"><div class="ai-visual-inner"><img class="ai-robot-icon" src="/AI-CAKE/robot.svg" width="180" height="180" alt=""></div></div>
      </div>
    </div>
  </section>
</main>

<?php storefront_footer(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
