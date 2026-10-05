<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin_role();
define('AI_CAKE_ORDER_WORKSPACE', 'admin');

require __DIR__ . '/../staff/unified_orders.php';