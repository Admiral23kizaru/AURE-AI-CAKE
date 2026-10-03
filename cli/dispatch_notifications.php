<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/inc/db.php';
require dirname(__DIR__) . '/inc/notification_service.php';
require dirname(__DIR__) . '/inc/cli_lock.php';
$lock=acquire_cli_lock('notification-dispatcher');

$dryRun = in_array('--dry-run', $argv, true);
if (!$dryRun && !env_bool('SMS_ENABLED')) {
    echo "SMS disabled; nothing dispatched.\n";
    exit;
}

$query = $pdo->query("SELECT * FROM notification_outbox WHERE final_state='pending' AND next_attempt_at<=NOW() AND attempts<5 ORDER BY id LIMIT 30");
$rows = $query->fetchAll();
if ($dryRun) {
    foreach ($rows as $row) {
        $data = json_decode($row['template_data'], true) ?: [];
        $recipient = (string) $row['recipient'];
        $masked = substr($recipient, 0, 4) . str_repeat('*', max(0, strlen($recipient) - 7)) . substr($recipient, -3);
        echo '#' . $row['id'] . ' ' . $masked . ' [' . $row['template_name'] . '] ' . render_sms_template($row['template_name'], $data) . "\n";
    }
    echo 'Dry run complete: ' . count($rows) . " message(s); no provider call and no database update.\n";
    exit;
}

$apiKey = (string) env_value('SEMAPHORE_API_KEY', '');
if ($apiKey === '') {
    fwrite(STDERR, "Semaphore API key is not configured.\n");
    exit(1);
}

foreach ($rows as $row) {
    $data = json_decode($row['template_data'], true) ?: [];
    $fields = [
        'apikey' => $apiKey,
        'number' => semaphore_phone_number((string)$row['recipient']),
        'message' => render_sms_template($row['template_name'], $data),
    ];
    $sender = trim((string) env_value('SEMAPHORE_SENDER_NAME', ''));
    if ($sender !== '') {
        $fields['sendername'] = $sender;
    }
    $curl = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($curl);
    $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    $decoded = json_decode((string) $raw, true);
    $provider = is_array($decoded) && isset($decoded[0]) ? $decoded[0] : $decoded;
    $attempt = (int) $row['attempts'] + 1;
    if ($http >= 200 && $http < 300) {
        $update = $pdo->prepare("UPDATE notification_outbox SET attempts=?,provider_message_id=?,provider_status=?,final_state='sent',sent_at=NOW(),last_error=NULL WHERE id=?");
        $update->execute([$attempt, $provider['message_id'] ?? null, $provider['status'] ?? 'sent', $row['id']]);
    } else {
        $finalState = $attempt >= 5 ? 'failed' : 'pending';
        $delay = min(60, (2 ** $attempt) * 5);
        $update = $pdo->prepare('UPDATE notification_outbox SET attempts=?,final_state=?,next_attempt_at=DATE_ADD(NOW(),INTERVAL ? MINUTE),last_error=? WHERE id=?');
        $update->execute([$attempt, $finalState, $delay, mb_substr($error ?: ('Provider HTTP ' . $http), 0, 255), $row['id']]);
    }
}
echo 'Dispatch completed: ' . count($rows) . " message(s) processed.\n";
