<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require_customer();

$cart = [];
$favoriteId = (int) ($_GET['favorite'] ?? 0);
$orderId = (int) ($_GET['id'] ?? 0);

if ($favoriteId > 0) {
    $q = $pdo->prepare('SELECT * FROM favorites WHERE id=? AND user_id=?');
    $q->execute([$favoriteId, user_id()]);
    $favorite = $q->fetch();
    if (!$favorite) { http_response_code(404); exit('Favorite not found.'); }
    if ($favorite['favorite_type'] === 'ai_design') {
        redirect('ai_cake.php?prompt=' . urlencode((string) $favorite['ai_prompt']));
    }
    $itemQuery = $pdo->prepare('SELECT * FROM cakes WHERE id=? AND available=1');
    $itemQuery->execute([$favorite['cake_id']]);
    $cake = $itemQuery->fetch();
    if (!$cake) exit('This cake is currently unavailable.');
    $cart[] = ['id'=>(int)$cake['id'],'cake_id'=>$cake['cake_id'],'name'=>$cake['name'],'price'=>(float)$cake['price'],'picture'=>$cake['picture'],'qty'=>1,'availableQty'=>(int)$cake['quantity'],'unitPrice'=>(float)$cake['price'],'total'=>(float)$cake['price'],'addons'=>[],'options'=>[],'addonsKey'=>''];
} else {
    $q = $pdo->prepare('SELECT * FROM order_headers WHERE id=? AND user_id=?');
    $q->execute([$orderId, user_id()]);
    $header = $q->fetch();
    if (!$header) { http_response_code(404); exit('Order not found.'); }
    if ($header['order_type'] === 'ai') {
        $q = $pdo->prepare('SELECT personalize FROM ai_cake_orders WHERE id=?');
        $q->execute([$header['ai_order_id']]);
        redirect('ai_cake.php?prompt=' . urlencode((string) $q->fetchColumn()));
    }
    $items = $pdo->prepare('SELECT oi.*,c.id product_id,c.cake_id cake_code,c.name,c.picture,c.price current_price,c.quantity FROM order_items oi JOIN cakes c ON c.id=oi.cake_id WHERE oi.order_id=? AND c.available=1');
    $items->execute([$header['normal_order_id']]);
    foreach ($items as $item) {
        $optionQuery = $pdo->prepare('SELECT option_id,option_type,option_name FROM order_item_options WHERE order_item_id=?');
        $optionQuery->execute([$item['id']]);
        $addons=[]; $options=[]; $unit=(float)$item['current_price'];
        foreach ($optionQuery as $option) {
            if ($option['option_type'] === 'Add-on') {
                $a=$pdo->prepare('SELECT id,name,price FROM addons WHERE name=?');$a->execute([$option['option_name']]);$row=$a->fetch();
                if($row){$addons[]=['id'=>(int)$row['id'],'name'=>$row['name'],'price'=>(float)$row['price']];$unit+=(float)$row['price'];}
            } elseif ($option['option_id']) {
                $o=$pdo->prepare('SELECT id,option_type,name,price_adjustment FROM cake_options WHERE id=? AND is_active=1');$o->execute([$option['option_id']]);$row=$o->fetch();
                if($row){$options[]=['id'=>(int)$row['id'],'type'=>$row['option_type'],'name'=>$row['name'],'price'=>(float)$row['price_adjustment']];$unit+=(float)$row['price_adjustment'];}
            }
        }
        $qty=max(1,(int)$item['qty']);
        $cart[]=['id'=>(int)$item['product_id'],'cake_id'=>$item['cake_code'],'name'=>$item['name'],'price'=>(float)$item['current_price'],'picture'=>$item['picture'],'qty'=>$qty,'availableQty'=>(int)$item['quantity'],'unitPrice'=>$unit,'total'=>$unit*$qty,'addons'=>$addons,'options'=>$options,'addonsKey'=>implode(',',array_column($addons,'id'))];
    }
}

if (!$cart) exit('No currently available items can be reordered.');
page_start('Prepare reorder');
?>
<main class="auth-shell"><section class="app-card auth-card p-4 p-md-5 text-center"><div class="eyebrow">Fresh availability check</div><h1 class="h3">Preparing your reorder</h1><p class="help-text">You will choose a new pickup schedule and payment method before the order is created.</p><noscript>JavaScript is required to prepare the cart.</noscript></section></main>
<script>localStorage.setItem('cart_user_<?= (int) user_id() ?>', <?=json_encode(json_encode($cart, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>);window.location.replace('checkout.php');</script>
<?php page_end();
