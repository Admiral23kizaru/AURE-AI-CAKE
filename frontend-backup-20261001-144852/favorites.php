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
<main class="container py-4">
  <div class="d-flex justify-content-between align-items-end"><div><div class="eyebrow">Saved for later</div><h1 class="h3 mb-0">Favorites</h1></div><a href="view-all.php" class="btn btn-purple">Browse cakes</a></div>
  <p class="help-text mt-2">Reordering creates a fresh draft so pickup and current availability can be checked again.</p>
  <?php if (!$favorites): ?><div class="app-card p-5 text-center">You have no saved favorites yet.</div><?php endif; ?>
  <div class="row g-3"><?php foreach ($favorites as $favorite): ?><div class="col-md-4"><article class="app-card p-3 h-100 d-flex flex-column"><h2 class="h5"><?= e($favorite['favorite_type'] === 'cake' ? $favorite['name'] : 'AI cake design') ?></h2><?php if ($favorite['favorite_type'] === 'ai_design'): ?><img class="img-fluid rounded mb-2" src="uploads/ai-cakes/<?= e(basename((string) $favorite['ai_image'])) ?>" alt="Saved AI cake design"><p class="small"><?= e($favorite['ai_prompt']) ?></p><?php else: ?><?php if ($favorite['picture']): ?><img class="img-fluid rounded mb-2" src="uploads/<?= e($favorite['picture']) ?>" alt="<?= e($favorite['name']) ?>"><?php endif; ?><p class="fw-bold">&#8369;<?= number_format((float) $favorite['price'], 2) ?></p><?php endif; ?><div class="mt-auto d-flex gap-2"><a href="reorder.php?favorite=<?= (int) $favorite['id'] ?>" class="btn btn-outline-primary btn-sm">Reorder</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="favorite_id" value="<?= (int) $favorite['id'] ?>"><button class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this favorite?')">Remove</button></form></div></article></div><?php endforeach; ?></div>
</main>
<?php page_end();
