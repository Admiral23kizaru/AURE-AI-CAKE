<?php
require_once __DIR__ . '/inc/db.php';
$config = require __DIR__ . '/inc/xendit.php';

$secretKey = $config['secret_key'] ?? '';
if (!$secretKey) die('Xendit config error: missing secret key');

$orderNumber = trim($_GET['order_number'] ?? '');
if ($orderNumber === '') die('Missing order number');

$stmt = $pdo->prepare("SELECT total, payment_status, payment_reference, payment_invoice_id FROM orders WHERE order_number = ? LIMIT 1");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die('Order not found');

if (strtolower((string)$order['payment_status']) === 'paid') {
    header('Location: track_order.php?order_number=' . urlencode($orderNumber));
    exit;
}

if (!empty($order['payment_reference']) && filter_var($order['payment_reference'], FILTER_VALIDATE_URL)) {
    header('Location: ' . $order['payment_reference']);
    exit;
}

$amount = round((float)$order['total'], 2);
if ($amount < 1) {
    die('Minimum GCash payment is ₱1.');
}

$baseUrl = rtrim($config['base_url'] ?? 'https://auresanchez.shop', '/');
$trackUrl = $baseUrl . '/track_order.php?order_number=' . urlencode($orderNumber);

$payload = [
    'external_id' => $orderNumber,
    'amount' => $amount,
    'currency' => 'PHP',
    'description' => "Cake Order Payment ({$orderNumber})",
    'payment_methods' => ['GCASH'],
    'success_redirect_url' => $trackUrl,
    'failure_redirect_url' => $trackUrl,
];

$ch = curl_init('https://api.xendit.co/v2/invoices');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $secretKey . ':',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$result = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($result === false || $http < 200 || $http >= 300) {
    error_log("Xendit invoice create failed http={$http} curl={$curlErr} response={$result}");
    die('Unable to create GCash payment. Please try again.');
}

$response = json_decode($result, true);
if (!is_array($response) || empty($response['invoice_url']) || empty($response['id'])) {
    error_log('Xendit invalid create invoice response: ' . $result);
    die('Unable to create GCash payment. Please try again.');
}

$stmt = $pdo->prepare("UPDATE orders SET payment_reference = ?, payment_invoice_id = ?, payment_status = 'pending' WHERE order_number = ?");
$stmt->execute([$response['invoice_url'], $response['id'], $orderNumber]);

header('Location: ' . $response['invoice_url']);
exit;
