<?php
declare(strict_types=1);
require_once __DIR__ . '/order_service.php';

function create_ai_order(PDO $pdo, int $aiId, int $uid, string $date, string $time, string $size, string $note, string $payment = 'cash'): array
{
    if (!in_array($size, ['Small', 'Medium', 'Large'], true)) throw new InvalidArgumentException('Choose a valid cake size.');
    validate_pickup_schedule($date, $time);
    $payment = $payment === 'gcash' ? 'gcash' : 'cash';
    $note = mb_substr(trim($note), 0, 1000);
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT * FROM ai_cake_orders WHERE id=? AND user_id=? FOR UPDATE');
        $lock->execute([$aiId, $uid]);
        $ai = $lock->fetch();
        if (!$ai) throw new RuntimeException('AI design not found.');
        if (!empty($ai['placed_at'])) throw new RuntimeException('This AI design was already submitted.');
        $existing = $pdo->prepare('SELECT id FROM order_headers WHERE ai_order_id=? FOR UPDATE');
        $existing->execute([$aiId]);
        if ($existing->fetchColumn()) throw new RuntimeException('This AI design was already submitted.');
        $capacityQuery = $pdo->prepare('SELECT * FROM ai_capacities WHERE pickup_date=? AND size=? AND active=1 FOR UPDATE');
        $capacityQuery->execute([$date, $size]);
        $capacity = $capacityQuery->fetch();
        if (!$capacity) throw new RuntimeException('AI production capacity is not configured for that date and size.');
        if ((int) $capacity['reserved'] >= (int) $capacity['capacity']) throw new RuntimeException('AI production capacity is full for that date and size.');
        $userQuery = $pdo->prepare('SELECT * FROM users WHERE id=? AND is_active=1');
        $userQuery->execute([$uid]);
        $user = $userQuery->fetch();
        if (!$user) throw new RuntimeException('Your account is not active.');
        if ($user['role'] !== 'customer' || empty($user['phone_verified_at'])) throw new RuntimeException('Verify your mobile number before placing an order.');
        $store = $pdo->query('SELECT * FROM stores WHERE is_active=1 ORDER BY id LIMIT 1')->fetch();
        if (!$store) throw new RuntimeException('No active pickup shop is configured.');
        $claim = (string) random_int(100000, 999999);
        $paymentStatus = $payment === 'gcash' ? 'pending' : 'unpaid';
        $pdo->prepare('UPDATE ai_cake_orders SET customer_name=?,customer_email=?,customer_number=?,pickup_date=?,pickup_time=?,store_name=?,payment=?,payment_status=?,cake_size=?,customer_note=?,status=?,placed_at=NOW() WHERE id=?')->execute([$user['name'], $user['email'], $user['phone'], $date, $time, $store['name'], $payment, $paymentStatus, $size, $note, 'pending', $aiId]);
        $insert = $pdo->prepare("INSERT INTO order_headers(order_number,user_id,ai_order_id,order_type,customer_name,customer_email,customer_phone,pickup_date,pickup_time,store_id,store_name,payment_method,payment_status,customer_note,subtotal,original_total,total,claim_code_hash,claim_code_cipher) VALUES(?,?,?,'ai',?,?,?,?,?,?,?,?,?,?,0,0,0,?,?)");
        $insert->execute([$ai['order_number'], $uid, $aiId, $user['name'], $user['email'], $user['phone'], $date, $time, $store['id'], $store['name'], $payment, $paymentStatus, $note, password_hash($claim, PASSWORD_DEFAULT), claim_encrypt($claim)]);
        $headerId = (int) $pdo->lastInsertId();
        reserve_pickup($pdo, $date, $time, $headerId);
        $pdo->prepare('UPDATE ai_capacities SET reserved=reserved+1 WHERE id=?')->execute([$capacity['id']]);
        $pdo->prepare("INSERT INTO order_reservations(order_header_id,resource_type,resource_id,quantity) VALUES(?,'ai_capacity',?,1)")->execute([$headerId, $capacity['id']]);
        $pdo->prepare("INSERT INTO order_status_history(order_header_id,to_status,note) VALUES(?,'Pending','AI cake submitted for staff pricing')")->execute([$headerId]);
        $pdo->commit();
        return ['id' => $headerId, 'order_number' => $ai['order_number'], 'claim_code' => $claim];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
