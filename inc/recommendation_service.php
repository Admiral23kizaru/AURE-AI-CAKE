<?php
declare(strict_types=1);

function refresh_recommendation(PDO $pdo, int $cakeId): array
{
    $query = $pdo->prepare(
        "SELECT COUNT(*) review_count,COALESCE(AVG(r.rating),0) average_rating
           FROM reviews r
           JOIN order_headers h ON h.id=r.order_header_id
           JOIN users u ON u.id=r.user_id
          WHERE r.cake_id=?
            AND r.is_verified=1
            AND r.user_id=h.user_id
            AND h.order_type='normal'
            AND h.status='Completed'
            AND h.payment_status='paid'
            AND u.phone_verified_at IS NOT NULL"
    );
    $query->execute([$cakeId]);
    $stats = $query->fetch();
    $count = (int) $stats['review_count'];
    $average = (float) $stats['average_rating'];
    $qualified = $count >= 50 && $average > 8.0;
    $current = $pdo->prepare('SELECT is_recommended FROM cakes WHERE id=?');
    $current->execute([$cakeId]);
    $wasQualified = (bool) $current->fetchColumn();
    $pdo->prepare('UPDATE cakes SET is_recommended=? WHERE id=?')->execute([$qualified ? 1 : 0, $cakeId]);
    if ($wasQualified !== $qualified) {
        $pdo->prepare('INSERT INTO recommendation_log(cake_id,action,verified_review_count,average_rating) VALUES(?,?,?,?)')->execute([$cakeId, $qualified ? 'qualified' : 'removed', $count, $average]);
    }
    return ['qualified' => $qualified, 'count' => $count, 'average' => $average];
}

function recommended_cakes_for_user(PDO $pdo, ?int $userId, int $limit = 12): array
{
    $limit=max(1,min(50,$limit));
    $sql="SELECT rc.*,0 AS preference_score FROM recommended_cakes rc";
    $params=[];
    if($userId){
        $sql="SELECT rc.*,
          (CASE WHEN EXISTS(SELECT 1 FROM favorites f WHERE f.user_id=? AND f.favorite_type='cake' AND f.cake_id=rc.id) THEN 5 ELSE 0 END)
          + LEAST(9,3*(
              CASE WHEN EXISTS(SELECT 1 FROM favorites f JOIN cakes fc ON fc.id=f.cake_id WHERE f.user_id=? AND f.favorite_type='cake' AND fc.category_id=rc.category_id) THEN 1 ELSE 0 END
            + CASE WHEN EXISTS(SELECT 1 FROM order_headers h JOIN orders o ON o.id=h.normal_order_id JOIN order_items oi ON oi.order_id=o.id JOIN cakes pc ON pc.id=oi.cake_id WHERE h.user_id=? AND h.status='Completed' AND h.payment_status='paid' AND pc.category_id=rc.category_id) THEN 1 ELSE 0 END
            + CASE WHEN EXISTS(SELECT 1 FROM favorites f WHERE f.user_id=? AND f.favorite_type='cake' AND f.cake_id=rc.id) THEN 1 ELSE 0 END
          ))
          + LEAST(6,2*(SELECT COUNT(DISTINCT co.option_type) FROM order_headers h JOIN orders o ON o.id=h.normal_order_id JOIN order_items oi ON oi.order_id=o.id JOIN order_item_options io ON io.order_item_id=oi.id JOIN cake_options co ON co.id=io.option_id JOIN cake_option_compatibility pc ON pc.option_id=co.id AND pc.cake_id=rc.id WHERE h.user_id=? AND h.status='Completed' AND h.payment_status='paid' AND co.option_type IN ('Flavor','Size','Design')))
          + LEAST(3,(SELECT COUNT(*) FROM order_headers h JOIN orders o ON o.id=h.normal_order_id JOIN order_items oi ON oi.order_id=o.id WHERE h.user_id=? AND h.status='Completed' AND h.payment_status='paid' AND oi.cake_id=rc.id)) AS preference_score
          FROM recommended_cakes rc";
        $params=array_fill(0,6,$userId);
    }
    $sql.=' ORDER BY preference_score DESC,average_rating DESC,verified_review_count DESC,name ASC LIMIT '.$limit;
    $stmt=$pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}
