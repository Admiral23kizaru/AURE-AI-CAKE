<?php
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';

require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$date = trim((string) ($_GET['pickup_date'] ?? $_GET['date'] ?? ''));
$size = trim((string) ($_GET['ai_size'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a valid pickup date.']);
    exit;
}

if ($size !== '' && !in_array($size, ['Small', 'Medium', 'Large'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a valid cake size.']);
    exit;
}

$slotSql = "SELECT id, DATE_FORMAT(pickup_time, '%H:%i') pickup_time, capacity, reserved,
                   GREATEST(capacity-reserved,0) remaining
              FROM pickup_capacities
             WHERE pickup_date=? AND active=1 AND reserved<capacity";
$slotParams = [$date];
if ($date === date('Y-m-d')) {
    $slotSql .= ' AND pickup_time > ?';
    $slotParams[] = date('H:i:s');
}
$slotSql .= ' ORDER BY pickup_time';
$slotQuery = $pdo->prepare($slotSql);
$slotQuery->execute($slotParams);
$slots = array_map(static fn(array $row): array => [
    'id' => (int) $row['id'],
    'time' => (string) $row['pickup_time'],
    'capacity' => (int) $row['capacity'],
    'reserved' => (int) $row['reserved'],
    'remaining' => (int) $row['remaining'],
], $slotQuery->fetchAll());

$aiCapacity = null;
if ($size !== '') {
    $capacityQuery = $pdo->prepare(
        'SELECT id, capacity, reserved, GREATEST(capacity-reserved,0) remaining
           FROM ai_capacities
          WHERE pickup_date=? AND size=? AND active=1
          LIMIT 1'
    );
    $capacityQuery->execute([$date, $size]);
    $row = $capacityQuery->fetch();
    if ($row) {
        $aiCapacity = [
            'id' => (int) $row['id'],
            'capacity' => (int) $row['capacity'],
            'reserved' => (int) $row['reserved'],
            'remaining' => (int) $row['remaining'],
        ];
    }
}

echo json_encode([
    'success' => true,
    'pickup_date' => $date,
    'slots' => $slots,
    'ai_size' => $size !== '' ? $size : null,
    'ai_capacity' => $aiCapacity,
], JSON_UNESCAPED_SLASHES);
