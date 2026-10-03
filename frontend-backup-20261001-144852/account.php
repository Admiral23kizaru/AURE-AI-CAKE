<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require __DIR__ . '/inc/otp_service.php';
require_customer();

$query = $pdo->prepare('SELECT * FROM users WHERE id=?');
$query->execute([user_id()]);
$user = $query->fetch();
$message = '';
$success = trim((string) ($_GET['updated'] ?? '')) !== '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    try {
        if (isset($_POST['profile'])) {
            $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (mb_strlen($name) < 2) throw new RuntimeException('Enter your full name.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) throw new RuntimeException('Enter a valid email address.');
            $duplicate = $pdo->prepare('SELECT 1 FROM users WHERE email=? AND id<>?');
            $duplicate->execute([$email, user_id()]);
            if ($duplicate->fetchColumn()) throw new RuntimeException('That email address is already registered.');
            $pdo->prepare('UPDATE users SET name=?,email=? WHERE id=?')->execute([$name, $email, user_id()]);
            $_SESSION['auth_name'] = $name;
            $_SESSION['auth_email'] = $email;
            redirect('account.php?updated=profile');
        }
        if (isset($_POST['phone'])) {
            $phone = normalize_ph_phone((string) ($_POST['new_phone'] ?? ''));
            if (!$phone) throw new RuntimeException('Enter a valid Philippine mobile number.');
            if ($phone === $user['phone']) throw new RuntimeException('Enter a different mobile number.');
            $duplicate = $pdo->prepare('SELECT 1 FROM users WHERE phone=? AND id<>?');
            $duplicate->execute([$phone, user_id()]);
            if ($duplicate->fetchColumn()) throw new RuntimeException('That mobile number is already registered.');
            $otp = issue_otp($pdo, user_id(), 'phone_change', $phone, $user['email']);
            $pdo->prepare('UPDATE users SET phone_verified_at=NULL WHERE id=?')->execute([user_id()]);
            $_SESSION['otp_challenge_id'] = $otp['challenge_id'];
            $feedback=otp_delivery_feedback($otp);$_SESSION['otp_notice']=$feedback['message'];$_SESSION['otp_notice_type']=$feedback['type'];
            redirect('verify_otp.php');
        }
        if (isset($_POST['password'])) {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            if (!password_verify($current, $user['password_hash'])) throw new RuntimeException('The current password is incorrect.');
            if (strlen($new) < 8) throw new RuntimeException('The new password must be at least 8 characters.');
            if ($new !== (string) ($_POST['password_confirmation'] ?? '')) throw new RuntimeException('The new passwords do not match.');
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), user_id()]);
            redirect('account.php?updated=password');
        }
    } catch (Throwable $error) {
        $message = $error instanceof PDOException ? 'Unable to update your account.' : ($error instanceof RuntimeException || $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to update your account.');
    }
}
page_start('Account');
?>
<main class="container py-4" style="max-width:980px">
  <div class="eyebrow">Customer account</div><h1 class="h3">Profile and security</h1>
  <?php if ($message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success">Your account was updated.</div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-6"><form method="post" class="app-card p-4 h-100"><?= csrf_field() ?><h2 class="h5">Personal details</h2><label class="form-label" for="name">Name</label><input id="name" name="name" maxlength="150" value="<?= e($user['name']) ?>" class="form-control" required><label class="form-label mt-3" for="email">Email</label><input id="email" type="email" name="email" maxlength="190" value="<?= e($user['email']) ?>" class="form-control" required><button name="profile" class="btn btn-purple mt-3">Save details</button></form></div>
    <div class="col-lg-6"><form method="post" class="app-card p-4 h-100"><?= csrf_field() ?><h2 class="h5">Verified mobile</h2><p><?= e($user['phone']) ?> <?= $user['phone_verified_at'] ? '<span class="badge text-bg-success">Verified</span>' : '<span class="badge text-bg-warning">Verification required</span>' ?></p><label class="form-label" for="new_phone">New mobile number</label><input id="new_phone" name="new_phone" inputmode="tel" placeholder="09XXXXXXXXX" class="form-control" required><p class="help-text mt-2">Changing your number requires a new OTP before login is allowed again.</p><button name="phone" class="btn btn-outline-primary">Verify new number</button></form></div>
    <div class="col-12"><form method="post" class="app-card p-4"><?= csrf_field() ?><h2 class="h5">Change password</h2><div class="row g-3"><div class="col-md-4"><label class="form-label" for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" class="form-control" required></div><div class="col-md-4"><label class="form-label" for="new_password">New password</label><input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" class="form-control" required></div><div class="col-md-4"><label class="form-label" for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" class="form-control" required></div></div><button name="password" class="btn btn-purple mt-3">Change password</button></form></div>
  </div>
</main>
<?php page_end();
