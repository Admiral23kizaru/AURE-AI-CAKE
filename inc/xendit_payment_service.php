<?php
declare(strict_types=1);

/**
 * Browser-return fallback for hosts that cannot receive Xendit webhooks.
 * The order must already have been loaded with customer ownership enforced.
 */
function sync_xendit_payment_for_order(PDO $pdo, array $order): bool
{
    if (($order['payment_method'] ?? '') !== 'gcash' || ($order['payment_status'] ?? '') === 'paid') {
        return false;
    }

    $legacyTable = ($order['order_type'] ?? '') === 'ai' ? 'ai_cake_orders' : 'orders';
    $legacyId = (int) (($order['order_type'] ?? '') === 'ai' ? ($order['ai_order_id'] ?? 0) : ($order['normal_order_id'] ?? 0));
    if ($legacyId < 1) return false;

    $invoiceQuery = $pdo->prepare("SELECT payment_invoice_id FROM {$legacyTable} WHERE id=? LIMIT 1");
    $invoiceQuery->execute([$legacyId]);
    $invoiceId = trim((string) $invoiceQuery->fetchColumn());
    if ($invoiceId === '') return false;

    $config = require __DIR__ . '/xendit.php';
    $secret = trim((string) ($config['secret_key'] ?? ''));
    if ($secret === '' || !function_exists('curl_init')) return false;

    $curl = curl_init('https://api.xendit.co/v2/invoices/' . rawurlencode($invoiceId));
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $secret . ':',
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $invoice = json_decode((string) $raw, true);
    if ($statusCode < 200 || $statusCode >= 300 || !is_array($invoice) || ($invoice['status'] ?? '') !== 'PAID') {
        return false;
    }
    if (!hash_equals((string) $order['order_number'], (string) ($invoice['external_id'] ?? ''))) {
        error_log('Xendit return verification rejected an invoice with a mismatched order number.');
        return false;
    }

    $pdo->beginTransaction();
    try {
        $locked = $pdo->prepare('SELECT * FROM order_headers WHERE id=? AND user_id=? FOR UPDATE');
        $locked->execute([(int) $order['id'], (int) $order['user_id']]);
        $header = $locked->fetch();
        if (!$header || $header['payment_status'] === 'paid') {
            $pdo->commit();
            return false;
        }
        $pdo->prepare("UPDATE order_headers SET payment_status='paid',payment_reference=? WHERE id=?")
            ->execute([$invoiceId, $header['id']]);
        if ($header['order_type'] === 'ai') {
            $pdo->prepare("UPDATE ai_cake_orders SET payment_status='paid',payment_reference=?,payment_invoice_id=? WHERE id=?")
                ->execute([$invoiceId, $invoiceId, $header['ai_order_id']]);
        } else {
            $pdo->prepare("UPDATE orders SET payment_status='paid',payment_reference=?,payment_invoice_id=?,paid_at=NOW() WHERE id=?")
                ->execute([$invoiceId, $invoiceId, $header['normal_order_id']]);
        }
        $pdo->prepare("INSERT INTO order_status_history(order_header_id,from_status,to_status,note) VALUES(?,?,?,'GCash payment verified after customer return')")
            ->execute([$header['id'], $header['status'], $header['status']]);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Xendit return verification failed: ' . $error->getMessage());
        return false;
    }
}
