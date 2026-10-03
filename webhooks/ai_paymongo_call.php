<?php
// webhook/ai_paymongo_call.php

require_once __DIR__ . '/../inc/db.php';

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!isset($data['data']['attributes']['remarks'])) {
    http_response_code(400);
    exit;
}

$orderNumber = $data['data']['attributes']['remarks'];
$status = $data['data']['attributes']['status'] ?? '';

if ($status !== 'paid') {
    http_response_code(200);
    exit;
}

// ============================
// UPDATE AI ORDER
// ============================

$stmt = $pdo->prepare("
    UPDATE ai_cake_orders
    SET payment_status = 'paid',
        status = 'processing',
        paid_at = NOW()
    WHERE order_number = :order_number
    LIMIT 1
");
$stmt->execute([':order_number' => $orderNumber]);

http_response_code(200);
echo "OK";
