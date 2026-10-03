<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? 'add');
    if ($action === 'remove') {
        $pdo->prepare('DELETE FROM favorites WHERE id=? AND user_id=?')->execute([(int) ($_POST['favorite_id'] ?? 0), user_id()]);
    } elseif (($_POST['favorite_type'] ?? '') === 'cake') {
        $cakeId = (int) ($_POST['cake_id'] ?? 0);
        $pdo->prepare("INSERT IGNORE INTO favorites(user_id,favorite_type,cake_id) SELECT ?,'cake',id FROM cakes WHERE id=? AND available=1")->execute([user_id(), $cakeId]);
    } elseif (($_POST['favorite_type'] ?? '') === 'ai_design') {
        $aiId = (int) ($_POST['ai_order_id'] ?? 0);
        $query = $pdo->prepare('SELECT picture,personalize,cake_size FROM ai_cake_orders WHERE id=? AND user_id=?');
        $query->execute([$aiId, user_id()]);
        $ai = $query->fetch();
        if ($ai) {
            $pdo->prepare("INSERT INTO favorites(user_id,favorite_type,ai_image,ai_prompt,ai_size,description) VALUES(?,'ai_design',?,?,?,'Saved AI cake design')")->execute([user_id(), basename((string) $ai['picture']), $ai['personalize'], $ai['cake_size']]);
        }
    }
    redirect('favorites.php');
}
$query = $pdo->prepare('SELECT f.*,c.name,c.picture,c.price FROM favorites f LEFT JOIN cakes c ON c.id=f.cake_id WHERE f.user_id=? ORDER BY f.created_at DESC');
$query->execute([user_id()]);
$favorites = $query->fetchAll();
page_start('Favorites');
?>
<main class="container py-4 py-lg-5 customer-workspace">
  <section class="workspace-heading"><div><p class="workspace-heading__eyebrow">Saved for later</p><h1>Favorites</h1><p>Keep normal cakes and AI design ideas ready for your next order.</p></div><a href="customer_menu.php" class="btn btn-purple"><i class="bi bi-cake2 me-2" aria-hidden="true"></i>Browse cakes</a></section>
  <nav class="customer-workspace-nav" aria-label="Customer workspace"><a href="my_orders.php"><i class="bi bi-receipt" aria-hidden="true"></i>Orders</a><a href="customer_menu.php"><i class="bi bi-cake2" aria-hidden="true"></i>Menu</a><a class="is-active" href="favorites.php"><i class="bi bi-heart" aria-hidden="true"></i>Favorites</a><a href="account.php"><i class="bi bi-person" aria-hidden="true"></i>Profile</a><a href="ai_cake.php"><i class="bi bi-stars" aria-hidden="true"></i>AI cake design</a></nav>
  <p class="help-text mb-4">Reordering creates a fresh draft so current availability and pickup capacity can be checked again.</p>
  <?php if (!$favorites): ?><section class="app-card customer-empty-state text-center"><span class="customer-empty-state__icon"><i class="bi bi-heart" aria-hidden="true"></i></span><h2>No favorites yet</h2><p class="help-text">Save a cake or an AI design so it is easy to find later.</p><a href="customer_menu.php" class="btn btn-purple">Browse cakes</a></section><?php endif; ?>
  <section class="favorite-grid" aria-label="Saved favorites"><?php foreach ($favorites as $favorite): ?><article class="app-card favorite-card"><div class="favorite-card__media"><?php if ($favorite['favorite_type'] === 'ai_design'): ?><img src="uploads/ai-cakes/<?= e(basename((string) $favorite['ai_image'])) ?>" alt="Saved AI cake design"><?php elseif ($favorite['picture']): ?><img src="uploads/<?= e($favorite['picture']) ?>" alt="<?= e($favorite['name']) ?>"><?php else: ?><span><i class="bi bi-cake2" aria-hidden="true"></i></span><?php endif; ?></div><div class="favorite-card__content"><p class="eyebrow mb-1"><?= $favorite['favorite_type'] === 'cake' ? 'Cake favorite' : 'AI cake design' ?></p><h2><?= e($favorite['favorite_type'] === 'cake' ? $favorite['name'] : 'Saved design') ?></h2><?php if ($favorite['favorite_type'] === 'ai_design'): ?><p class="help-text mb-3"><?= e($favorite['ai_prompt']) ?></p><?php else: ?><p class="favorite-card__price">₱<?= number_format((float) $favorite['price'], 2) ?></p><?php endif; ?><div class="mt-auto d-flex flex-wrap gap-2"><a href="reorder.php?favorite=<?= (int) $favorite['id'] ?>" class="btn btn-outline-primary">Reorder</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="favorite_id" value="<?= (int) $favorite['id'] ?>"><button class="btn btn-outline-danger" onclick="return confirm('Remove this favorite?')">Remove</button></form></div></div></article><?php endforeach; ?></section>
</main>
<?php page_end();
