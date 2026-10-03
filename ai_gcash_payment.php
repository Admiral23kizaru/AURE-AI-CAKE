<?php
// ai_gcash_payment.php

require_once __DIR__ . '/inc/db.php';

$config = require __DIR__ . '/inc/paymongo.php';
$secretKey = $config['secret_key'] ?? null;

if (!$secretKey) die("Payment configuration error.");

// ============================
// VALIDATE INPUT
// ============================

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$amount  = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

if ($orderId <= 0 || $amount <= 0) {
    die("Invalid payment request.");
}

if ($amount < 100) {
    die("Minimum GCash payment is ₱100.");
}

// ============================
// VERIFY AI ORDER
// ============================

$stmt = $pdo->prepare("
    SELECT order_number, total
    FROM ai_cake_orders
    WHERE id = :id AND payment = 'gcash'
    LIMIT 1
");
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("AI order not found.");
}

$amountCentavos = (int) round($order['total'] * 100);

// ============================
// PAYMONGO PAYMENT LINK
// ============================

$successUrl = "https://yourdomain.com/ai_track_orders.php?order_number=" . urlencode($order['order_number']);
$cancelUrl  = "https://yourdomain.com/proceed_ai_cakes.php?order_id=" . $orderId;

$data = [
    "data" => [
        "attributes" => [
            "amount" => $amountCentavos,
            "currency" => "PHP",
            "description" => "AI Cake Order Payment",
            "remarks" => $order['order_number'], // 🔑 webhook key
            "success_url" => $successUrl,
            "cancel_url"  => $cancelUrl,
            "payment_method_types" => ["gcash"]
        ]
    ]
];

// ============================
// SEND TO PAYMONGO
// ============================

$ch = curl_init("https://api.paymongo.com/v1/links");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Basic " . base64_encode($secretKey . ":")
    ],
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    error_log("PayMongo cURL Error: " . $err);
    die("Payment gateway error.");
}

$res = json_decode($response, true);

if (empty($res['data']['attributes']['checkout_url'])) {
    error_log("PayMongo API Error: " . $response);
    die("Unable to create GCash payment.");
}

// ============================
// REDIRECT TO GCASH
// ============================

header("Location: " . $res['data']['attributes']['checkout_url']);
exit;
