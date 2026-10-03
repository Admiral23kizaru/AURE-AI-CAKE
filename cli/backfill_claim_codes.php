<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/inc/db.php';
require dirname(__DIR__) . '/inc/order_service.php';
$rows = $pdo->query('SELECT id FROM order_headers WHERE claim_code_hash IS NULL OR claim_code_cipher IS NULL')->fetchAll();
$update = $pdo->prepare('UPDATE order_headers SET claim_code_hash=?, claim_code_cipher=? WHERE id=? AND claim_code_hash IS NULL');
foreach ($rows as $row) {
    $code = (string) random_int(100000, 999999);
    $update->execute([password_hash($code, PASSWORD_DEFAULT), claim_encrypt($code), $row['id']]);
}
echo count($rows) . " legacy claim codes backfilled.\n";
