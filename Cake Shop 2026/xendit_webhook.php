<?php
require_once __DIR__ . '/inc/db.php';
$config = require __DIR__ . '/inc/xendit.php';

@is_dir(__DIR__ . '/logs') || @mkdir(__DIR__ . '/logs', 0777, true);
$logFile = __DIR__ . '/logs/xendit_webhook.log';

$secretKey = $config['secret_key'] ?? '';
$callbackToken = $config['callback_token'] ?? '';

function log_xendit($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL, FILE_APPEND);
}

function xendit_get_invoice(string $secretKey, string $invoiceId): ?array {
    $ch = curl_init('https://api.xendit.co/v2/invoices/' . urlencode($invoiceId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $http < 200 || $http >= 300) {
        log_xendit("GET_INVOICE_FAILED id={$invoiceId} http={$http} err={$err} resp={$resp}");
        return null;
    }
    $json = json_decode($resp, true);
    return is_array($json) ? $json : null;
}

function mark_order_paid(PDO $pdo, string $table, string $orderNumber, string $invoiceId): bool {
    if ($table === 'orders') {
        $sql = "UPDATE orders
                SET payment_status='paid', payment_invoice_id=?, paid_at=NOW()
                WHERE order_number=? AND (payment_status IS NULL OR payment_status <> 'paid')";
    } else {
        $sql = "UPDATE ai_cake_orders
                SET payment_status='paid', payment_invoice_id=?, paid_at=NOW(), updated_at=NOW()
                WHERE order_number=? AND (payment_status IS NULL OR payment_status <> 'paid')";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$invoiceId, $orderNumber]);
    return $stmt->rowCount() === 1;
}

$payload = file_get_contents('php://input');
$headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
http_response_code(200);

if ($secretKey === '' || $secretKey === 'PASTE_YOUR_XENDIT_LIVE_SECRET_KEY_HERE') {
    log_xendit('MISSING_SECRET_KEY');
    exit('MISSING_SECRET_KEY');
}

if ($callbackToken !== '') {
    $incomingToken = $headers['x-callback-token'] ?? '';
    if (!hash_equals($callbackToken, $incomingToken)) {
        log_xendit('INVALID_CALLBACK_TOKEN');
        exit('INVALID_CALLBACK_TOKEN');
    }
}

$data = json_decode($payload, true);
log_xendit('WEBHOOK ' . $payload);

$invoiceId = trim((string)($data['id'] ?? ''));
if ($invoiceId === '') exit('IGNORED_NO_ID');

$invoice = xendit_get_invoice($secretKey, $invoiceId);
if (!$invoice) exit('FAILED_TO_VERIFY');

$status = strtoupper((string)($invoice['status'] ?? ''));
$orderNumber = trim((string)($invoice['external_id'] ?? ''));

if ($orderNumber === '') exit('MISSING_EXTERNAL_ID');
if (!in_array($status, ['PAID', 'SETTLED'], true)) exit('NOT_PAID_' . $status);

// Normal cake orders
$stmt = $pdo->prepare('SELECT id FROM orders WHERE order_number=? LIMIT 1');
$stmt->execute([$orderNumber]);
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    $changed = mark_order_paid($pdo, 'orders', $orderNumber, $invoiceId);
    log_xendit(($changed ? 'PAID_UPDATED orders ' : 'ALREADY_PAID orders ') . $orderNumber);
    echo json_encode(['ok' => true, 'table' => 'orders', 'updated' => $changed, 'order_number' => $orderNumber]);
    exit;
}

// AI cake orders
$stmt = $pdo->prepare('SELECT id FROM ai_cake_orders WHERE order_number=? LIMIT 1');
$stmt->execute([$orderNumber]);
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    $changed = mark_order_paid($pdo, 'ai_cake_orders', $orderNumber, $invoiceId);
    log_xendit(($changed ? 'PAID_UPDATED ai_cake_orders ' : 'ALREADY_PAID ai_cake_orders ') . $orderNumber);
    echo json_encode(['ok' => true, 'table' => 'ai_cake_orders', 'updated' => $changed, 'order_number' => $orderNumber]);
    exit;
}

log_xendit('ORDER_NOT_FOUND ' . $orderNumber);
echo 'ORDER_NOT_FOUND';
