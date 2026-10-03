<?php
// paymongo_callback.php

// ============================
// CONFIG
// ============================

// ⚠️ Get this from PayMongo Dashboard → Webhooks
$webhookSecret = 'whsec_xxxxxxxxxxxxxxxxx';

// Log file (outside public_html if possible)
$logFile = __DIR__ . '/../logs/paymongo_webhook.log';

// ============================
// READ RAW INPUT
// ============================

$payload = file_get_contents('php://input');
$headers = getallheaders();
$signatureHeader = $headers['Paymongo-Signature'] ?? '';

if (!$payload || !$signatureHeader) {
    http_response_code(400);
    exit('Missing payload or signature');
}

// ============================
// VERIFY SIGNATURE
// ============================

function verifyPaymongoSignature($payload, $signatureHeader, $secret) {
    // Header format: t=timestamp,v1=signature
    $parts = [];
    foreach (explode(',', $signatureHeader) as $item) {
        [$k, $v] = array_map('trim', explode('=', $item, 2));
        $parts[$k] = $v;
    }

    if (!isset($parts['t'], $parts['v1'])) return false;

    $signedPayload = $parts['t'] . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    return hash_equals($expected, $parts['v1']);
}

if (!verifyPaymongoSignature($payload, $signatureHeader, $webhookSecret)) {
    file_put_contents($logFile, "Invalid signature\n", FILE_APPEND);
    http_response_code(401);
    exit('Invalid signature');
}

// ============================
// PARSE EVENT
// ============================

$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    exit('Invalid JSON');
}

$eventType = $event['data']['attributes']['type'] ?? null;
$eventData = $event['data']['attributes']['data'] ?? null;

if (!$eventType || !$eventData) {
    http_response_code(400);
    exit('Invalid event structure');
}

// ============================
// HANDLE EVENTS
// ============================

if ($eventType === 'link.payment.paid') {

    require_once __DIR__ . '/../inc/db.php';

    $paymentId   = $eventData['id'] ?? null;
    $amount      = $eventData['attributes']['amount'] ?? 0;
    $orderNumber = $eventData['attributes']['remarks'] ?? null;

    if (!$orderNumber) {
        file_put_contents(
            $logFile,
            date('c') . " PAID but missing order number in remarks\n",
            FILE_APPEND
        );
        http_response_code(200);
        exit;
    }

    // ✅ Update payment status ONLY
    $stmt = $pdo->prepare("
        UPDATE orders
        SET payment_status = 'paid',
            payment_reference = ?,
            paid_at = NOW()
        WHERE order_number = ?
        LIMIT 1
    ");
    $stmt->execute([
        $paymentId,
        $orderNumber
    ]);

    file_put_contents(
        $logFile,
        date('c') . " PAID paymentId=$paymentId amount=$amount order=$orderNumber\n",
        FILE_APPEND
    );
}


// ============================
// RESPOND OK
// ============================

http_response_code(200);
echo json_encode(['received' => true]);
