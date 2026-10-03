<?php
// gcash_payment.php

require_once __DIR__ . '/inc/db.php';

// Load PayMongo config
$config = require __DIR__ . '/inc/paymongo.php';
$secretKey = $config['secret_key'] ?? null;

if (!$secretKey) {
    die("Payment configuration error.");
}

// ============================
// VALIDATE AMOUNT
// ============================

if (!isset($_GET['amount']) || !is_numeric($_GET['amount'])) {
    die("Invalid amount.");
}

$amountPhp = floatval($_GET['amount']);
if ($amountPhp < 100) {
    die("Minimum GCash payment is ₱100.");
}

$amountCentavos = (int) round($amountPhp * 100);

// ============================
// CREATE ORDER (PENDING)
// ============================

$orderNumber = 'ORD-' . date('Ymd-His') . '-' . random_int(1000, 9999);

$stmt = $pdo->prepare("
    INSERT INTO orders
    (order_number, total, payment, payment_status, status, created_at)
    VALUES (?, ?, 'gcash', 'pending', 'pending', NOW())
");

$stmt->execute([
    $orderNumber,
    $amountPhp
]);

// ============================
// PAYMONGO PAYMENT LINK
// ============================

$successUrl = "https://yourdomain.com/payment_success.php?order=" . urlencode($orderNumber);
$cancelUrl  = "https://yourdomain.com/checkout.php";

$data = [
    "data" => [
        "attributes" => [
            "amount" => $amountCentavos,
            "currency" => "PHP",
            "description" => "Cake Order Payment",
            "remarks" => $orderNumber, // 🔑 webhook reference
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

$result = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    error_log("PayMongo cURL Error: " . $curlErr);
    die("Payment gateway error.");
}

$response = json_decode($result, true);

if (!isset($response['data']['attributes']['checkout_url'])) {
    error_log("PayMongo API Error: " . $result);
    die("Unable to create GCash payment.");
}

// ============================
// REDIRECT USER
// ============================

header("Location: " . $response['data']['attributes']['checkout_url']);
exit;
