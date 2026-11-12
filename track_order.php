<?php declare(strict_types=1);require __DIR__.'/inc/auth.php';if(is_logged_in()&&current_user()['role']==='customer')redirect(app_url('my_orders.php'));redirect(app_url('login.php'));
