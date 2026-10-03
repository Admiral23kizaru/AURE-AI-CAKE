<?php
declare(strict_types=1);

require_once __DIR__ . '/recommendation_service.php';

function reviewable_order_items(PDO $pdo, int $orderHeaderId, int $userId): array
{
    $query = $pdo->prepare(
        "SELECT h.id header_id,oi.id item_id,oi.cake_id,c.name
           FROM order_headers h
           JOIN users u ON u.id=h.user_id
           JOIN order_items oi ON oi.order_id=h.normal_order_id
           JOIN cakes c ON c.id=oi.cake_id
          WHERE h.id=? AND h.user_id=?
            AND h.order_type='normal'
            AND h.status='Completed'
            AND h.payment_status='paid'
            AND u.is_active=1
            AND u.phone_verified_at IS NOT NULL
          ORDER BY oi.id"
    );
    $query->execute([$orderHeaderId, $userId]);
    return $query->fetchAll();
}

function submit_verified_review(PDO $pdo, int $orderHeaderId, int $orderItemId, int $userId, int $rating, string $comment = ''): int
{
    if ($rating < 1 || $rating > 10) {
        throw new InvalidArgumentException('Choose a rating from 1 to 10.');
    }
    $comment = mb_substr(trim($comment), 0, 2000);
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare(
            "SELECT oi.cake_id
               FROM order_headers h
               JOIN users u ON u.id=h.user_id
               JOIN order_items oi ON oi.order_id=h.normal_order_id
              WHERE h.id=? AND h.user_id=? AND oi.id=?
                AND h.order_type='normal'
                AND h.status='Completed'
                AND h.payment_status='paid'
                AND u.is_active=1
                AND u.phone_verified_at IS NOT NULL
              FOR UPDATE"
        );
        $query->execute([$orderHeaderId, $userId, $orderItemId]);
        $cakeId = $query->fetchColumn();
        if ($cakeId === false) {
            throw new RuntimeException('This order item is not eligible for a review.');
        }
        try {
            $pdo->prepare('INSERT INTO reviews(user_id,order_header_id,order_item_id,cake_id,rating,comment,is_verified) VALUES(?,?,?,?,?,?,1)')
                ->execute([$userId, $orderHeaderId, $orderItemId, $cakeId, $rating, $comment]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') throw new RuntimeException('This order item has already been reviewed.');
            throw $error;
        }
        $reviewId = (int) $pdo->lastInsertId();
        refresh_recommendation($pdo, (int) $cakeId);
        $pdo->commit();
        return $reviewId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
