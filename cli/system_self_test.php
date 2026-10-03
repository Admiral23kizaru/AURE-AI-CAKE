<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
putenv('SMS_ENABLED=false');
require dirname(__DIR__) . '/inc/db.php';
require dirname(__DIR__) . '/inc/order_service.php';
require dirname(__DIR__) . '/inc/order_admin_service.php';
require dirname(__DIR__) . '/inc/ai_order_service.php';
require dirname(__DIR__) . '/inc/inventory_service.php';
require dirname(__DIR__) . '/inc/recommendation_service.php';
require dirname(__DIR__) . '/inc/review_service.php';
require dirname(__DIR__) . '/inc/otp_service.php';

$run = 'TST' . strtoupper(bin2hex(random_bytes(4)));
$date = '2099-12-31';
$time = '16:45';
$created = ['users'=>[], 'cakes'=>[], 'addons'=>[], 'options'=>[], 'orders'=>[], 'headers'=>[], 'items'=>[], 'ai'=>[]];
$tests = 0;
$failures = [];

function check(bool $condition, string $label): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$condition) $failures[] = $label;
}

function scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $query = $pdo->prepare($sql);
    $query->execute($params);
    return $query->fetchColumn();
}

function cleanup_fixture(PDO $pdo, string $run): void
{
    $headers = $pdo->prepare('SELECT id,normal_order_id,ai_order_id FROM order_headers WHERE order_number LIKE ? OR customer_email LIKE ?');
    $headers->execute([$run . '%', strtolower($run) . '%@example.test']);
    $rows = $headers->fetchAll();
    $headerIds = array_map('intval', array_column($rows, 'id'));
    $normalIds = array_values(array_filter(array_map('intval', array_column($rows, 'normal_order_id'))));
    $aiIds = array_values(array_filter(array_map('intval', array_column($rows, 'ai_order_id'))));
    $placeholders = static fn(array $ids): string => implode(',', array_fill(0, count($ids), '?'));
    if ($normalIds) {
        $itemQuery = $pdo->prepare('SELECT id FROM order_items WHERE order_id IN (' . $placeholders($normalIds) . ')');
        $itemQuery->execute($normalIds);
        $itemIds = array_map('intval', $itemQuery->fetchAll(PDO::FETCH_COLUMN));
        if ($itemIds) {
            $pdo->prepare('DELETE FROM reviews WHERE order_item_id IN (' . $placeholders($itemIds) . ')')->execute($itemIds);
            $pdo->prepare('DELETE FROM order_item_options WHERE order_item_id IN (' . $placeholders($itemIds) . ')')->execute($itemIds);
        }
        $pdo->prepare('DELETE FROM order_items WHERE order_id IN (' . $placeholders($normalIds) . ')')->execute($normalIds);
    }
    if ($headerIds) {
        $pdo->prepare('DELETE FROM order_discounts WHERE order_header_id IN (' . $placeholders($headerIds) . ')')->execute($headerIds);
        $pdo->prepare('DELETE FROM order_status_history WHERE order_header_id IN (' . $placeholders($headerIds) . ')')->execute($headerIds);
        $pdo->prepare('DELETE FROM order_reservations WHERE order_header_id IN (' . $placeholders($headerIds) . ')')->execute($headerIds);
        $pdo->prepare('DELETE FROM reviews WHERE order_header_id IN (' . $placeholders($headerIds) . ')')->execute($headerIds);
        foreach ($headerIds as $headerId) $pdo->prepare('DELETE FROM notification_outbox WHERE event_key LIKE ?')->execute(['order:' . $headerId . ':%']);
        foreach ($headerIds as $headerId) $pdo->prepare('DELETE FROM notification_outbox WHERE event_key LIKE ?')->execute(['custom-order-' . $headerId . '-%']);
        $pdo->prepare('DELETE FROM order_headers WHERE id IN (' . $placeholders($headerIds) . ')')->execute($headerIds);
    }
    if ($normalIds) $pdo->prepare('DELETE FROM orders WHERE id IN (' . $placeholders($normalIds) . ')')->execute($normalIds);
    if ($aiIds) {
        $pdo->prepare('DELETE FROM ai_cake_messages WHERE order_id IN (' . $placeholders($aiIds) . ')')->execute($aiIds);
        $pdo->prepare('DELETE FROM ai_cake_orders WHERE id IN (' . $placeholders($aiIds) . ')')->execute($aiIds);
    }
    $cakeQuery = $pdo->prepare('SELECT id FROM cakes WHERE cake_id LIKE ?');
    $cakeQuery->execute([$run . '%']);
    $cakeIds = array_map('intval', $cakeQuery->fetchAll(PDO::FETCH_COLUMN));
    if ($cakeIds) {
        $pdo->prepare('DELETE FROM favorites WHERE cake_id IN (' . $placeholders($cakeIds) . ')')->execute($cakeIds);
        $pdo->prepare('DELETE FROM recommendation_log WHERE cake_id IN (' . $placeholders($cakeIds) . ')')->execute($cakeIds);
        $pdo->prepare('DELETE FROM reviews WHERE cake_id IN (' . $placeholders($cakeIds) . ')')->execute($cakeIds);
        $pdo->prepare('DELETE FROM cake_option_compatibility WHERE cake_id IN (' . $placeholders($cakeIds) . ')')->execute($cakeIds);
        $pdo->prepare('DELETE FROM cakes_addons WHERE cake_id IN (' . $placeholders($cakeIds) . ')')->execute($cakeIds);
        $pdo->prepare('DELETE FROM inventory_adjustments WHERE cake_id IN (' . $placeholders($cakeIds) . ')')->execute($cakeIds);
        $pdo->prepare('DELETE FROM cakes WHERE id IN (' . $placeholders($cakeIds) . ')')->execute($cakeIds);
    }
    $pdo->prepare('DELETE x FROM addon_inventory_adjustments x JOIN addons a ON a.id=x.addon_id WHERE a.name LIKE ?')->execute([$run . '%']);
    $pdo->prepare('DELETE FROM addons WHERE name LIKE ?')->execute([$run . '%']);
    $pdo->prepare('DELETE FROM cake_options WHERE name LIKE ?')->execute([$run . '%']);
    $pdo->prepare('DELETE FROM pickup_capacities WHERE pickup_date=? AND pickup_time=?')->execute(['2099-12-31', '16:45']);
    $pdo->prepare('DELETE FROM ai_capacities WHERE pickup_date=?')->execute(['2099-12-31']);
    $pdo->prepare('DELETE FROM otp_challenges WHERE email_key LIKE ?')->execute([strtolower($run) . '%@example.test']);
    $pdo->prepare('DELETE FROM users WHERE email LIKE ?')->execute([strtolower($run) . '%@example.test']);
}

cleanup_fixture($pdo, 'TST');
$countTables = [
    'users','cakes','addons','cake_options','orders','order_headers','order_items',
    'order_item_options','order_reservations','order_status_history','order_discounts',
    'reviews','favorites','recommendation_log','notification_outbox','ai_cake_orders',
    'ai_cake_messages','pickup_capacities','ai_capacities','inventory_adjustments',
    'addon_inventory_adjustments','otp_challenges',
];
$before = [];
foreach ($countTables as $table) $before[$table] = (int) scalar($pdo, 'SELECT COUNT(*) FROM ' . $table);

try {
    $email = strtolower($run) . 'customer@example.test';
    $phone = '+639170000001';
    $pdo->prepare("INSERT INTO users(name,email,phone,password_hash,role,is_active,phone_verified_at) VALUES(?,?,?,?, 'customer',1,NOW())")->execute([$run . ' Customer', $email, $phone, password_hash('TestPass123!', PASSWORD_DEFAULT)]);
    $customerId = (int) $pdo->lastInsertId();
    $created['users'][] = $customerId;
    $pdo->prepare("INSERT INTO otp_challenges(user_id,purpose,destination,email_key,code_hash,attempts_left,expires_at,resend_after) VALUES(?,'registration',?,?,?,5,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NOW())")->execute([$customerId, $phone, $email, password_hash('123456', PASSWORD_DEFAULT)]);
    $otpId = (int) $pdo->lastInsertId();
    $wrongOtp = false;
    try { verify_otp($pdo, $otpId, '000000'); } catch (RuntimeException) { $wrongOtp = true; }
    check($wrongOtp && (int) scalar($pdo, 'SELECT attempts_left FROM otp_challenges WHERE id=?', [$otpId]) === 4, 'Wrong OTP is rejected and decrements attempts.');
    verify_otp($pdo, $otpId, '123456');
    check((bool) scalar($pdo, 'SELECT consumed_at IS NOT NULL FROM otp_challenges WHERE id=?', [$otpId]), 'Correct OTP is consumed.');
    $reusedOtp = false;
    try { verify_otp($pdo, $otpId, '123456'); } catch (RuntimeException) { $reusedOtp = true; }
    check($reusedOtp, 'Consumed OTP cannot be reused.');
    $pdo->prepare("INSERT INTO otp_challenges(user_id,purpose,destination,email_key,code_hash,attempts_left,expires_at,resend_after) VALUES(?,'registration',?,?,?,5,DATE_SUB(NOW(),INTERVAL 1 MINUTE),NOW())")->execute([$customerId, $phone, $email, password_hash('654321', PASSWORD_DEFAULT)]);
    $expiredOtpId = (int) $pdo->lastInsertId();
    $expiredOtp = false;
    try { verify_otp($pdo, $expiredOtpId, '654321'); } catch (RuntimeException) { $expiredOtp = true; }
    check($expiredOtp, 'Expired OTP is rejected.');
    $pdo->prepare("INSERT INTO users(name,email,password_hash,role,is_active,phone_verified_at) VALUES(?,?,?,'staff',1,NOW())")->execute([$run . ' Staff', strtolower($run) . 'staff@example.test', password_hash('TestPass123!', PASSWORD_DEFAULT)]);
    $staffId = (int) $pdo->lastInsertId();
    $created['users'][] = $staffId;

    $pdo->prepare('INSERT INTO cakes(cake_id,name,price,available,quantity,description) VALUES(?,?,125,1,10,?)')->execute([$run . 'CAKE', $run . ' Normal Cake', 'Automated fixture']);
    $cakeId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO addons(name,price,quantity) VALUES(?,20,10)')->execute([$run . ' Addon']);
    $addonId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO cakes_addons(cake_id,addon_id) VALUES(?,?)')->execute([$cakeId, $addonId]);
    $pdo->prepare("INSERT INTO cake_options(option_type,name,price_adjustment,stock,is_active) VALUES('Flavor',?,15,10,1)")->execute([$run . ' Flavor']);
    $optionId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO cake_option_compatibility(cake_id,option_id,is_required) VALUES(?,?,1)')->execute([$cakeId, $optionId]);
    $adjustedStock=adjust_cake_inventory($pdo,$cakeId,3,'Automated positive inventory correction',$staffId,'correction');
    check($adjustedStock===13 && (int)scalar($pdo,'SELECT resulting_stock FROM inventory_adjustments WHERE cake_id=? ORDER BY id DESC LIMIT 1',[$cakeId])===13,'Cake stock corrections record actor, reason, and resulting stock.');
    $restoredStock=adjust_cake_inventory($pdo,$cakeId,-3,'Automated negative inventory correction',$staffId,'correction');
    check($restoredStock===10 && (int)scalar($pdo,'SELECT COUNT(*) FROM inventory_adjustments WHERE cake_id=?',[$cakeId])===2,'Negative corrections remain append-only and restore the expected stock.');
    $pdo->prepare('INSERT INTO pickup_capacities(pickup_date,pickup_time,capacity,reserved,active) VALUES(?,?,2,0,1)')->execute([$date, $time]);
    $payload = ['payment'=>'cash','pickup_date'=>$date,'pickup_time'=>$time,'total'=>0.01,'cart'=>[['id'=>$cakeId,'qty'=>2,'price'=>0.01,'addons'=>[['id'=>$addonId,'qty'=>99]],'options'=>[['id'=>$optionId]]]]];
    $first = create_normal_order($pdo, $payload, $customerId);
    check(abs((float) $first['total'] - 320.0) < 0.001, 'Normal order ignores browser prices and recalculates cake, option, and add-on totals.');
    check((int) scalar($pdo, 'SELECT quantity FROM cakes WHERE id=?', [$cakeId]) === 8, 'Cake inventory reserved.');
    check((int) scalar($pdo, 'SELECT stock FROM cake_options WHERE id=?', [$optionId]) === 8, 'Option inventory reserved.');
    check((int) scalar($pdo, 'SELECT quantity FROM addons WHERE id=?', [$addonId]) === 8, 'Add-on inventory follows cake quantity.');
    check((int) scalar($pdo, 'SELECT reserved FROM pickup_capacities WHERE pickup_date=? AND pickup_time=?', [$date, $time]) === 1, 'Pickup capacity reserved.');
    cancel_order($pdo, (int) $first['id'], $customerId, 'Automated cancellation test');
    check((int) scalar($pdo, 'SELECT quantity FROM cakes WHERE id=?', [$cakeId]) === 10, 'Cancellation restores cake inventory exactly once.');
    check((int) scalar($pdo, 'SELECT stock FROM cake_options WHERE id=?', [$optionId]) === 10, 'Cancellation restores option inventory.');
    check((int) scalar($pdo, 'SELECT quantity FROM addons WHERE id=?', [$addonId]) === 10, 'Cancellation restores add-on inventory.');
    check((int) scalar($pdo, 'SELECT reserved FROM pickup_capacities WHERE pickup_date=? AND pickup_time=?', [$date, $time]) === 0, 'Cancellation restores pickup capacity.');

    $secondPayload = $payload;
    $secondPayload['cart'][0]['qty'] = 1;
    $secondPayload['cart'][0]['addons'][0]['qty'] = 2;
    $second = create_normal_order($pdo, $secondPayload, $customerId, $staffId, 'staff_assisted');
    check((string)scalar($pdo,'SELECT order_source FROM order_headers WHERE id=?',[$second['id']])==='staff_assisted' && (int)scalar($pdo,'SELECT created_by_user_id FROM order_headers WHERE id=?',[$second['id']])===$staffId,'Assisted order retains customer ownership and staff attribution.');
    check((int)scalar($pdo,'SELECT quantity FROM order_reservations WHERE order_header_id=? AND resource_type=\'addon\'',[$second['id']])===2,'Assisted order preserves its explicit add-on quantity.');
    queue_custom_sms($pdo,(int)$second['id'],$staffId,$phone,'Your assisted order is ready for staff review.','Automated audit test');
    check((int)scalar($pdo,"SELECT COUNT(*) FROM notification_outbox WHERE actor_user_id=? AND reason='Automated audit test' AND template_name='custom_message' AND final_state='disabled'",[$staffId])===1,'Custom SMS is audited and remains non-delivery in automated tests.');
    update_order_status($pdo, (int) $second['id'], 'Confirmed', $staffId);
    $blocked = false;
    try { cancel_order($pdo, (int) $second['id'], $customerId, 'Should be blocked'); } catch (RuntimeException) { $blocked = true; }
    check($blocked, 'Customer cancellation is blocked after confirmation.');
    update_order_status($pdo, (int) $second['id'], 'Preparing', $staffId);
    update_order_status($pdo, (int) $second['id'], 'Ready for Pickup', $staffId);
    $wrongClaim = false;
    try { update_order_status($pdo, (int) $second['id'], 'Completed', $staffId, '000000'); } catch (RuntimeException) { $wrongClaim = true; }
    check($wrongClaim, 'Incorrect claim code is rejected.');
    update_order_status($pdo, (int) $second['id'], 'Completed', $staffId, (string) $second['claim_code']);
    check((string) scalar($pdo, 'SELECT payment_status FROM order_headers WHERE id=?', [$second['id']]) === 'paid', 'Cash is marked paid only after valid claim completion.');
    $completedItemId = (int) scalar($pdo, 'SELECT oi.id FROM order_items oi JOIN order_headers h ON h.normal_order_id=oi.order_id WHERE h.id=? ORDER BY oi.id LIMIT 1', [$second['id']]);
    $invalidRating = false;
    try { submit_verified_review($pdo, (int) $second['id'], $completedItemId, $customerId, 11); } catch (InvalidArgumentException) { $invalidRating = true; }
    check($invalidRating, 'Review ratings outside 1-10 are rejected.');
    $unauthorizedReview = false;
    try { submit_verified_review($pdo, (int) $second['id'], $completedItemId, $staffId, 9); } catch (RuntimeException) { $unauthorizedReview = true; }
    check($unauthorizedReview, 'A different account cannot review another customer\'s order item.');
    $cancelledItemId = (int) scalar($pdo, 'SELECT oi.id FROM order_items oi JOIN order_headers h ON h.normal_order_id=oi.order_id WHERE h.id=? ORDER BY oi.id LIMIT 1', [$first['id']]);
    $incompleteReview = false;
    try { submit_verified_review($pdo, (int) $first['id'], $cancelledItemId, $customerId, 9); } catch (RuntimeException) { $incompleteReview = true; }
    check($incompleteReview, 'Cancelled or incomplete orders cannot be reviewed.');
    $reviewId = submit_verified_review($pdo, (int) $second['id'], $completedItemId, $customerId, 9, 'Automated verified review');
    check($reviewId > 0 && (int) scalar($pdo, 'SELECT is_verified FROM reviews WHERE id=?', [$reviewId]) === 1, 'The owning verified customer can review a completed paid item.');
    $duplicateReview = false;
    try { submit_verified_review($pdo, (int) $second['id'], $completedItemId, $customerId, 8); } catch (RuntimeException) { $duplicateReview = true; }
    check($duplicateReview, 'An order item can be reviewed only once.');

    $existingPicture = (string) scalar($pdo, 'SELECT picture FROM ai_cake_orders WHERE picture IS NOT NULL ORDER BY id LIMIT 1');
    $aiNumber = $run . 'AI001';
    $pdo->prepare("INSERT INTO ai_cake_orders(order_number,customer_name,user_id,picture,personalize,status,is_ai,payment_status) VALUES(?,?,?,?,?,'pending',1,'unpaid')")->execute([$aiNumber, $run . ' Customer', $customerId, $existingPicture, 'Automated saved AI design']);
    $aiId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO ai_capacities(pickup_date,size,capacity,reserved,active) VALUES(?,'Medium',1,0,1)")->execute([$date]);
    $wrongOwner = false;
    try { create_ai_order($pdo, $aiId, $staffId, $date, $time, 'Medium', 'Wrong owner attempt'); } catch (RuntimeException) { $wrongOwner = true; }
    check($wrongOwner, 'Another account cannot schedule a customer AI design.');
    $aiOrder = create_ai_order($pdo, $aiId, $customerId, $date, $time, 'Medium', 'Automated AI order test');
    $pdo->prepare("INSERT INTO ai_cake_messages(order_id,order_number,sender,message) VALUES(?,?,'customer',?)")->execute([$aiId,$aiNumber,$run.' temporary chat message']);
    check((int)scalar($pdo,'SELECT COUNT(*) FROM ai_cake_messages WHERE order_id=? AND message=?',[$aiId,$run.' temporary chat message'])===1,'AI conversation accepts a tagged customer test message.');
    check((int) scalar($pdo, 'SELECT reserved FROM ai_capacities WHERE pickup_date=? AND size=\'Medium\'', [$date]) === 1, 'AI size capacity is reserved transactionally.');
    check((int) scalar($pdo, 'SELECT reserved FROM pickup_capacities WHERE pickup_date=? AND pickup_time=?', [$date, $time]) === 2, 'AI order also reserves pickup capacity.');
    $duplicateAi = false;
    try { create_ai_order($pdo, $aiId, $customerId, $date, $time, 'Medium', 'Duplicate'); } catch (RuntimeException) { $duplicateAi = true; }
    check($duplicateAi, 'Duplicate AI submission is rejected.');
    $unpricedAi = false;
    try { update_order_status($pdo, (int) $aiOrder['id'], 'Confirmed', $staffId); } catch (RuntimeException) { $unpricedAi = true; }
    check($unpricedAi, 'AI confirmation is blocked until staff assigns a positive price.');
    set_ai_price($pdo, (int) $aiOrder['id'], 750.00, $staffId);
    update_order_status($pdo, (int) $aiOrder['id'], 'Confirmed', $staffId);
    cancel_order($pdo, (int) $aiOrder['id'], $staffId, 'Automated AI cancellation', true);
    check((int) scalar($pdo, 'SELECT reserved FROM ai_capacities WHERE pickup_date=? AND size=\'Medium\'', [$date]) === 0, 'AI cancellation restores size capacity exactly once.');
    check((int) scalar($pdo, 'SELECT reserved FROM pickup_capacities WHERE pickup_date=? AND pickup_time=?', [$date, $time]) === 1, 'AI cancellation restores its pickup reservation.');

    $pdo->prepare('INSERT INTO cakes(cake_id,name,price,available,quantity,description) VALUES(?,?,500,1,100,?)')->execute([$run . 'REC', $run . ' Recommendation Cake', 'Recommendation boundary fixture']);
    $recommendationCakeId = (int) $pdo->lastInsertId();
    $verifiedReviewIds = [];
    for ($index = 1; $index <= 50; $index++) {
        $fixtureEmail = strtolower($run) . str_pad((string) $index, 2, '0', STR_PAD_LEFT) . '@example.test';
        $fixturePhone = '+63918' . str_pad((string) $index, 7, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO users(name,email,phone,password_hash,role,is_active,phone_verified_at) VALUES(?,?,?,?, 'customer',1,NOW())")->execute([$run . ' Reviewer ' . $index, $fixtureEmail, $fixturePhone, password_hash('TestPass123!', PASSWORD_DEFAULT)]);
        $reviewerId = (int) $pdo->lastInsertId();
        $orderNumber = $run . 'R' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO orders(order_number,customer_name,customer_email,customer_phone,total,payment,addons,pickup_date,pickup_time,store_name,status,is_ai,payment_status) VALUES(?,?,?,?,500,'cash','[]',?,?,'Fixture Shop','completed',0,'paid')")->execute([$orderNumber, $run . ' Reviewer ' . $index, $fixtureEmail, $fixturePhone, $date, $time]);
        $legacyId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO order_headers(order_number,user_id,normal_order_id,order_type,customer_name,customer_email,customer_phone,status,pickup_date,pickup_time,payment_method,payment_status,subtotal,original_total,total) VALUES(?,?,?,'normal',?,?,?,'Completed',?,?,'cash','paid',500,500,500)")->execute([$orderNumber, $reviewerId, $legacyId, $run . ' Reviewer ' . $index, $fixtureEmail, $fixturePhone, $date, $time]);
        $headerId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO order_items(order_id,cake_id,qty,price,is_ai) VALUES(?,?,1,500,0)')->execute([$legacyId, $recommendationCakeId]);
        $itemId = (int) $pdo->lastInsertId();
        if ($index <= 49) {
            $pdo->prepare('INSERT INTO reviews(user_id,order_header_id,order_item_id,cake_id,rating,is_verified) VALUES(?,?,?,?,9,1)')->execute([$reviewerId, $headerId, $itemId, $recommendationCakeId]);
            $verifiedReviewIds[] = (int) $pdo->lastInsertId();
        }
    }
    $stats = refresh_recommendation($pdo, $recommendationCakeId);
    check(!$stats['qualified'] && $stats['count'] === 49, 'Forty-nine high verified reviews remain hidden.');
    $pdo->prepare('UPDATE reviews SET rating=8 WHERE cake_id=?')->execute([$recommendationCakeId]);
    $lastHeader = (int) scalar($pdo, 'SELECT id FROM order_headers WHERE order_number=?', [$run . 'R050']);
    $lastItem = (int) scalar($pdo, 'SELECT oi.id FROM order_items oi JOIN order_headers h ON h.normal_order_id=oi.order_id WHERE h.id=?', [$lastHeader]);
    $lastUser = (int) scalar($pdo, 'SELECT user_id FROM order_headers WHERE id=?', [$lastHeader]);
    $pdo->prepare('INSERT INTO reviews(user_id,order_header_id,order_item_id,cake_id,rating,is_verified) VALUES(?,?,?,?,8,1)')->execute([$lastUser, $lastHeader, $lastItem, $recommendationCakeId]);
    $lastReviewId = (int) $pdo->lastInsertId();
    $stats = refresh_recommendation($pdo, $recommendationCakeId);
    check(!$stats['qualified'] && $stats['count'] === 50 && abs($stats['average'] - 8.0) < 0.001, 'Fifty reviews averaging exactly 8.0 remain hidden.');
    $pdo->prepare('UPDATE reviews SET rating=9 WHERE id=?')->execute([$lastReviewId]);
    $stats = refresh_recommendation($pdo, $recommendationCakeId);
    check($stats['qualified'] && $stats['count'] === 50 && $stats['average'] > 8.0, 'Fifty reviews averaging above 8.0 are recommended automatically.');
    $pdo->prepare('UPDATE cakes SET available=0 WHERE id=?')->execute([$recommendationCakeId]);
    check((int)scalar($pdo,'SELECT COUNT(*) FROM recommended_cakes WHERE id=?',[$recommendationCakeId])===0,'Archived cakes never appear in storefront recommendations.');
    $pdo->prepare('UPDATE cakes SET available=1 WHERE id=?')->execute([$recommendationCakeId]);
    $pdo->prepare('DELETE FROM reviews WHERE id=?')->execute([$lastReviewId]);
    $stats = refresh_recommendation($pdo, $recommendationCakeId);
    check(!$stats['qualified'] && $stats['count'] === 49, 'Recommendation is removed when the threshold later becomes false.');
    $pdo->prepare('INSERT INTO reviews(user_id,order_header_id,order_item_id,cake_id,rating,is_verified) VALUES(?,?,?,?,9,1)')->execute([$lastUser,$lastHeader,$lastItem,$recommendationCakeId]);
    refresh_recommendation($pdo,$recommendationCakeId);
    $pdo->prepare('INSERT INTO cakes(cake_id,name,price,available,quantity,description) VALUES(?,?,500,1,100,?)')->execute([$run.'REC2',$run.' Favorite Recommendation Cake','Hybrid ranking fixture']);
    $favoriteCakeId=(int)$pdo->lastInsertId();
    for($index=1;$index<=50;$index++){$header=(int)scalar($pdo,'SELECT id FROM order_headers WHERE order_number=?',[$run.'R'.str_pad((string)$index,3,'0',STR_PAD_LEFT)]);$legacy=(int)scalar($pdo,'SELECT normal_order_id FROM order_headers WHERE id=?',[$header]);$reviewer=(int)scalar($pdo,'SELECT user_id FROM order_headers WHERE id=?',[$header]);$pdo->prepare('INSERT INTO order_items(order_id,cake_id,qty,price,is_ai) VALUES(?,?,1,500,0)')->execute([$legacy,$favoriteCakeId]);$newItem=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO reviews(user_id,order_header_id,order_item_id,cake_id,rating,is_verified) VALUES(?,?,?,?,9,1)')->execute([$reviewer,$header,$newItem,$favoriteCakeId]);}
    refresh_recommendation($pdo,$favoriteCakeId);$pdo->prepare("INSERT INTO favorites(user_id,favorite_type,cake_id) VALUES(?,'cake',?)")->execute([$customerId,$favoriteCakeId]);$ranked=recommended_cakes_for_user($pdo,$customerId,10);
    check((int)($ranked[0]['id']??0)===$favoriteCakeId && (int)($ranked[0]['preference_score']??0)>=5,'Hybrid recommendations rank a directly favored eligible cake first.');
    $fallback=recommended_cakes_for_user($pdo,null,10);check(count($fallback)>=2 && (float)$fallback[0]['average_rating']>8.0,'Customers without preference history receive rating-based fallback ordering.');
    check((int) scalar($pdo, "SELECT COUNT(*) FROM notification_outbox WHERE event_key LIKE 'order:%' AND final_state='sent'") === 0, 'No SMS was sent during automated testing.');
} catch (Throwable $error) {
    $failures[] = 'Unexpected test exception: ' . $error->getMessage();
    fwrite(STDERR, '[ERROR] ' . $error->getMessage() . PHP_EOL);
} finally {
    cleanup_fixture($pdo, $run);
}

$after = [];
foreach ($countTables as $table) $after[$table] = (int) scalar($pdo, 'SELECT COUNT(*) FROM ' . $table);
foreach ($after as $table => $count) check($count === $before[$table], 'Cleanup restored original ' . $table . ' row count (' . $before[$table] . ' -> ' . $count . ').');

echo PHP_EOL . 'Tests: ' . $tests . '; failures: ' . count($failures) . PHP_EOL;
if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    exit(1);
}
exit(0);
