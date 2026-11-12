<?php
declare(strict_types=1);

require __DIR__ . '/inc/auth.php';

// Legacy public order-number lookup was retired because it exposed customer
// order data without ownership verification. Tracking now uses unified,
// authenticated customer history.
if (is_logged_in() && current_user()['role'] === 'customer') {
    redirect(app_url('my_orders.php'));
}
redirect(app_url('login.php'));
