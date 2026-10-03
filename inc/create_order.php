<?php
function create_order_from_payload(PDO $pdo, array $payload): array {

    // ⬇️ COPY THESE FROM cash_payment.php
    // - generateUniqueOrderNumber()
    // - addon processing
    // - personalization
    // - order_items insert
    // - main orders insert

    // IMPORTANT:
    // DO NOT send SMS here
    // DO NOT generate receipt here

    // Return:
    return [
        'order_id' => $order_id,
        'order_number' => $order_number
    ];
}
