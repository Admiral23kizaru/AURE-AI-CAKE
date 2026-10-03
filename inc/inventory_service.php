<?php
declare(strict_types=1);

/**
 * Apply an append-only cake stock adjustment and return the resulting stock.
 */
function adjust_cake_inventory(
    PDO $pdo,
    int $cakeId,
    int $quantityChange,
    string $reason,
    int $actorUserId,
    string $adjustmentType = 'correction'
): int {
    $reason = trim($reason);
    $allowedTypes = ['opening', 'purchase', 'cancellation', 'correction'];
    if ($cakeId <= 0 || $quantityChange === 0) {
        throw new InvalidArgumentException('Choose a cake and enter a non-zero stock change.');
    }
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
        throw new InvalidArgumentException('Enter a reason between 3 and 255 characters.');
    }
    if (!in_array($adjustmentType, $allowedTypes, true)) {
        throw new InvalidArgumentException('Invalid inventory adjustment type.');
    }

    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT quantity FROM cakes WHERE id=? FOR UPDATE');
        $query->execute([$cakeId]);
        $current = $query->fetchColumn();
        if ($current === false) {
            throw new RuntimeException('Cake not found.');
        }
        $resultingStock = (int) $current + $quantityChange;
        if ($resultingStock < 0) {
            throw new RuntimeException('Stock cannot be reduced below zero.');
        }

        $pdo->prepare('UPDATE cakes SET quantity=? WHERE id=?')->execute([$resultingStock, $cakeId]);
        $pdo->prepare(
            'INSERT INTO inventory_adjustments(cake_id,qty_change,adjustment_type,resulting_stock,note,actor_user_id)
             VALUES(?,?,?,?,?,?)'
        )->execute([$cakeId, $quantityChange, $adjustmentType, $resultingStock, $reason, $actorUserId]);
        $pdo->commit();
        return $resultingStock;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
