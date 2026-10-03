<?php
declare(strict_types=1);
$config=require __DIR__.'/../config.php'; $dsn="mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
try { $pdo=new PDO($dsn,$config['user'],$config['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); }
catch(Throwable $e){ http_response_code(503); error_log('Database connection failed: '.$e->getMessage()); exit('<h2>Service temporarily unavailable</h2><p>The database connection could not be established.</p>'); }
