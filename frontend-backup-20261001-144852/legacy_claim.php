<?php
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require __DIR__ . '/inc/otp_service.php';
require_customer();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    try {
        if (isset($_POST['request'])) {
            $number = mb_substr(trim((string) ($_POST['order_number'] ?? '')), 0, 100);
            $query = $pdo->prepare('SELECT id,customer_phone FROM order_headers WHERE order_number=? AND user_id IS NULL LIMIT 1');
            $query->execute([$number]);
            $order = $query->fetch();
            if (!$order || empty($order['customer_phone'])) {
                throw new RuntimeException('No eligible guest order was found.');
            }

            $otp = issue_otp($pdo, user_id(), 'legacy_claim', (string) $order['customer_phone'], current_user()['email']);
            $_SESSION['claim_challenge_id'] = (int) $otp['challenge_id'];
            $_SESSION['claim_order_id'] = (int) $order['id'];
            $feedback = otp_delivery_feedback($otp);
            $message = $feedback['message'];
        } elseif (isset($_POST['verify'])) {
            $challengeId = (int) ($_SESSION['claim_challenge_id'] ?? 0);
            $orderId = (int) ($_SESSION['claim_order_id'] ?? 0);
            if ($challengeId < 1 || $orderId < 1) {
                throw new RuntimeException('Start the order-linking request again.');
            }

            $challenge = verify_otp($pdo, $challengeId, trim((string) ($_POST['code'] ?? '')));
            if (
                $challenge['purpose'] !== 'legacy_claim'
                || (int) $challenge['user_id'] !== (int) user_id()
            ) {
                throw new RuntimeException('This verification code cannot be used for that order.');
            }

            $pdo->beginTransaction();
            try {
                $query = $pdo->prepare('SELECT id,customer_phone,order_type,ai_order_id FROM order_headers WHERE id=? AND user_id IS NULL FOR UPDATE');
                $query->execute([$orderId]);
                $order = $query->fetch();
                if (!$order || !hash_equals((string) $challenge['destination'], (string) $order['customer_phone'])) {
                    throw new RuntimeException('This order is no longer eligible to be linked.');
                }
                $pdo->prepare('UPDATE order_headers SET user_id=? WHERE id=? AND user_id IS NULL')->execute([user_id(), $orderId]);
                if ($order['order_type'] === 'ai' && $order['ai_order_id']) {
                    $pdo->prepare('UPDATE ai_cake_orders SET user_id=? WHERE id=?')->execute([user_id(), $order['ai_order_id']]);
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }

            unset($_SESSION['claim_challenge_id'], $_SESSION['claim_order_id']);
            redirect('my_orders.php');
        }
    } catch (Throwable $error) {
        error_log('Legacy order claim: ' . $error->getMessage());
        $message = $error instanceof RuntimeException || $error instanceof InvalidArgumentException
            ? $error->getMessage()
            : 'Unable to link the order.';
    }
}

page_start('Claim previous order');
?>
<main class="auth-shell"><section class="app-card auth-card p-4 p-md-5">
  <div class="eyebrow">Existing guest order</div><h1 class="h3">Link an order</h1>
  <p class="help-text">Enter the order number. We only send the code to the mobile number already recorded on that order.</p>
  <?php if ($message): ?><div class="alert alert-info"><?= e($message) ?></div><?php endif; ?>
  <?php if (empty($_SESSION['claim_challenge_id'])): ?>
    <form method="post"><?= csrf_field() ?><label for="order_number" class="form-label">Order number</label><input id="order_number" name="order_number" maxlength="100" class="form-control" required><button name="request" class="btn btn-purple w-100 mt-3">Send verification code</button></form>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?><label for="code" class="form-label">Six-digit code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="form-control" required><button name="verify" class="btn btn-purple w-100 mt-3">Verify and link order</button></form>
  <?php endif; ?>
</section></main>
<?php page_end();
