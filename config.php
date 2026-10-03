<?php
declare(strict_types=1);
require_once __DIR__.'/inc/env.php';
return ['host'=>env_value('DB_HOST','127.0.0.1'),'dbname'=>env_value('DB_NAME','u147648417_auresanchez'),'user'=>env_value('DB_USER','root'),'pass'=>env_value('DB_PASS',''),'charset'=>'utf8mb4'];
