<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

// Retained only for the retired PayMongo code path. The public route and old
// callbacks are blocked; no provider credential may be stored in source code.
return [
    'secret_key' => (string) env_value('PAYMONGO_SECRET_KEY', ''),
];
