<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require __DIR__ . '/inc/review_service.php';
require_customer();

$orderId = (int) ($_GET['order'] ?? 0);
$items = reviewable_order_items($pdo, $orderId, user_id());
if (!$items) {
    http_response_code(403);
    exit('This order is not eligible for reviews.');
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    try {
        submit_verified_review($pdo, $orderId, $itemId, user_id(), $rating, (string) ($_POST['comment'] ?? ''));
        $message = 'Thank you. Your verified review was recorded.';
    } catch (Throwable $error) {
        $message = $error instanceof PDOException ? 'Unable to record the review.' : $error->getMessage();
    }
}
page_start('Review cakes');
?>
<main class="container py-4 py-lg-5 customer-review-page" style="max-width:760px"><section class="customer-page-heading"><p class="eyebrow">Verified purchase</p><h1>Share your cake experience</h1><p class="help-text">Only completed purchases count toward recommendations. Cakes need at least 50 verified ratings and an average strictly above 8.0.</p></section><?php if ($message): ?><div class="alert alert-info"><?= e($message) ?></div><?php endif; ?><?php foreach ($items as $item): ?><form method="post" class="app-card customer-review-card p-4 mb-3"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>"><div class="customer-form-heading mb-4"><span><i class="bi bi-star" aria-hidden="true"></i></span><div><h2 class="h5"><?= e($item['name']) ?></h2><p>Give this completed purchase a rating from 1 to 10.</p></div></div><label class="form-label" for="rating-<?= (int) $item['item_id'] ?>">Rating (1-10)</label><input id="rating-<?= (int) $item['item_id'] ?>" type="number" min="1" max="10" name="rating" class="form-control" required><label class="form-label mt-3" for="comment-<?= (int) $item['item_id'] ?>">Comment (optional)</label><textarea id="comment-<?= (int) $item['item_id'] ?>" name="comment" maxlength="2000" class="form-control"></textarea><button class="btn btn-purple mt-3">Submit verified review</button></form><?php endforeach; ?></main>
<?php page_end();
