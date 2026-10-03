<?php declare(strict_types=1);require __DIR__.'/inc/auth.php';if(is_logged_in()&&current_user()['role']==='customer')redirect('/AI-CAKE/my_orders.php');redirect('/AI-CAKE/login.php');
