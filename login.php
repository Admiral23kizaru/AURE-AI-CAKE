<?php
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require __DIR__ . '/inc/otp_service.php';

if (is_logged_in()) {
    redirect(role_home_path());
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $query = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $query->execute([$email]);
    $user = $query->fetch();

    $valid = $user && (
        password_verify($password, $user['password_hash'])
        || (preg_match('/^[a-f0-9]{32}$/i', $user['password_hash']) && hash_equals(strtolower($user['password_hash']), md5($password)))
    );

    if (!$valid) {
        $error = 'Email or password is incorrect.';
    } elseif ($user['role'] === 'customer' && !$user['phone_verified_at']) {
        try {
            if (empty($user['phone'])) throw new RuntimeException('A mobile number must be assigned before verification.');
            $otp=issue_otp($pdo,(int)$user['id'],'registration',(string)$user['phone'],(string)$user['email']);
            $_SESSION['otp_challenge_id']=$otp['challenge_id'];
            $feedback=otp_delivery_feedback($otp);$_SESSION['otp_notice']=$feedback['message'];$_SESSION['otp_notice_type']=$feedback['type'];
            redirect('/AI-CAKE/verify_otp.php');
        } catch (Throwable $otpError) {
            $error=$otpError->getMessage();
        }
    } elseif (!(int) $user['is_active']) {
        $error = 'This account is inactive. Please contact the shop.';
    } else {
        if (preg_match('/^[a-f0-9]{32}$/i', $user['password_hash'])) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $user['id']]);
            $pdo->prepare('UPDATE admins SET password = ? WHERE user_id = ?')->execute([$newHash, $user['id']]);
        }
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        login_user($user);
        redirect(role_home_path((string) $user['role']));
    }
}

page_start('Log in', false);
?>
<main class="auth-shell auth-shell-login">
  <section class="app-card login-card" aria-labelledby="login-title">
        <h1 id="login-title">Welcome back</h1>
        <p class="help-text auth-intro">Sign in to view and manage your orders.</p>

        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" name="email" type="email" autocomplete="email" required class="form-control" value="<?= e($_POST['email'] ?? '') ?>">
          </div>
          <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="form-control">
          </div>
          <button class="btn btn-purple w-100 auth-submit">Log in</button>
        </form>

        <div class="auth-divider" aria-hidden="true"><span>New here?</span></div>
        <a class="btn auth-create-link w-100" href="register.php">Create an account</a>
  </section>
</main>
<?php page_end(); ?>
