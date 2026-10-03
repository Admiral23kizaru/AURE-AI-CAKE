<?php
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';

require_customer();

function customer_ai_order(PDO $pdo, int $id = 0, string $number = ''): ?array
{
    if ($id > 0) {
        $query = $pdo->prepare('SELECT * FROM ai_cake_orders WHERE id=? AND user_id=? LIMIT 1');
        $query->execute([$id, user_id()]);
    } else {
        $query = $pdo->prepare('SELECT * FROM ai_cake_orders WHERE order_number=? AND user_id=? LIMIT 1');
        $query->execute([$number, user_id()]);
    }
    return $query->fetch() ?: null;
}

function ai_image_url(?string $path): string
{
    $name = basename((string) $path);
    return $name !== '' ? 'uploads/ai-cakes/' . rawurlencode($name) : '';
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $order = customer_ai_order($pdo, (int) ($_GET['order_id'] ?? 0));
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Conversation not found.']);
        exit;
    }
    $lastId = max(0, (int) ($_GET['last_id'] ?? 0));
    $messages = $pdo->prepare('SELECT id,sender,message,created_at FROM ai_cake_messages WHERE order_id=? AND id>? ORDER BY id');
    $messages->execute([$order['id'], $lastId]);
    $header = $pdo->prepare('SELECT id,status FROM order_headers WHERE ai_order_id=? AND user_id=? LIMIT 1');
    $header->execute([$order['id'], user_id()]);
    echo json_encode(['success' => true, 'messages' => $messages->fetchAll(), 'placed' => !empty($order['placed_at']), 'header' => $header->fetch() ?: null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        require_post_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'search') {
            $number = mb_substr(trim((string) ($_POST['order_number'] ?? '')), 0, 100);
            $order = customer_ai_order($pdo, 0, $number);
            if (!$order) {
                http_response_code(404);
                throw new RuntimeException('Conversation not found.');
            }
            echo json_encode(['success' => true, 'redirect' => 'chat.php?order_id=' . (int) $order['id']]);
            exit;
        }
        if ($action === 'message') {
            $order = customer_ai_order($pdo, (int) ($_POST['order_id'] ?? 0));
            if (!$order) {
                http_response_code(404);
                throw new RuntimeException('Conversation not found.');
            }
            $message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 1000);
            if ($message === '') {
                http_response_code(422);
                throw new RuntimeException('Enter a message.');
            }
            $insert = $pdo->prepare("INSERT INTO ai_cake_messages(order_id,order_number,sender,message,created_at) VALUES(?,?,'customer',?,NOW())");
            $insert->execute([$order['id'], $order['order_number'], $message]);
            echo json_encode(['success' => true, 'message' => ['id' => (int) $pdo->lastInsertId(), 'sender' => 'customer', 'message' => $message, 'created_at' => date('Y-m-d H:i:s')]]);
            exit;
        }
        http_response_code(400);
        throw new RuntimeException('Invalid request.');
    } catch (Throwable $error) {
        if (http_response_code() < 400) {
            http_response_code(422);
        }
        error_log('Customer AI chat: ' . $error->getMessage());
        echo json_encode(['success' => false, 'message' => $error instanceof PDOException ? 'Unable to process the request.' : ($error instanceof RuntimeException ? $error->getMessage() : 'Unable to process the request.')]);
        exit;
    }
}

$orderId = (int) ($_GET['order_id'] ?? 0);
$orderNumber = mb_substr(trim((string) ($_GET['order_number'] ?? '')), 0, 100);
$order = $orderId > 0 ? customer_ai_order($pdo, $orderId) : ($orderNumber !== '' ? customer_ai_order($pdo, 0, $orderNumber) : null);
if (($orderId > 0 || $orderNumber !== '') && !$order) {
    http_response_code(404);
    exit('Conversation not found.');
}
$messages = [];
$header = null;
if ($order) {
    $query = $pdo->prepare('SELECT id,sender,message,created_at FROM ai_cake_messages WHERE order_id=? ORDER BY id');
    $query->execute([$order['id']]);
    $messages = $query->fetchAll();
    $query = $pdo->prepare('SELECT id,status FROM order_headers WHERE ai_order_id=? AND user_id=? LIMIT 1');
    $query->execute([$order['id'], user_id()]);
    $header = $query->fetch() ?: null;
}

page_start('AI cake conversation');
?>
<main class="container py-4 py-lg-5 conversation-page" style="max-width:1000px">
  <a href="my_orders.php" class="customer-back-link">&larr; Back to my orders</a>
  <?php if (!$order): ?>
    <section class="app-card p-4 p-md-5 conversation-search-card">
      <div class="eyebrow">Your AI design</div>
      <h1 class="h3">Open a conversation</h1>
      <p class="help-text">Enter your AI design number to continue the conversation with the bakery.</p>
      <form id="searchForm" class="row g-2 mt-3 conversation-search-form">
        <div class="col"><label for="order_number" class="visually-hidden">AI design number</label><input id="order_number" class="form-control" placeholder="AI design number" maxlength="100" required></div>
        <div class="col-auto"><button class="btn btn-purple">Open</button></div>
      </form>
    </section>
  <?php else: ?>
    <section class="app-card p-4 p-lg-5 conversation-card">
      <div class="row g-4">
        <aside class="col-sm-4 conversation-design">
          <div class="conversation-design__image"><?php if ($order['picture']): ?><img src="<?= e(ai_image_url($order['picture'])) ?>" class="img-fluid" alt="Generated cake design"><?php else: ?><i class="bi bi-cake2" aria-hidden="true"></i><?php endif; ?></div>
          <div class="eyebrow mt-3">AI design <?= e($order['order_number']) ?></div>
          <p class="conversation-design__prompt"><?= e($order['personalize']) ?></p>
        </div>
        <div class="col-sm-8">
          <header class="conversation-header">
            <div><p class="eyebrow mb-2">Bakery conversation</p><h1 class="h3 mb-1">Design conversation</h1><p class="help-text mb-0"><?= $header ? 'Order status: ' . e($header['status']) : 'Discuss details, then schedule the design.' ?></p></div>
            <?php if (!$order['placed_at']): ?><a class="btn btn-purple" href="proceed_ai_cakes.php?order_id=<?= (int) $order['id'] ?>">Schedule order</a><?php elseif ($header): ?><a class="btn btn-outline-primary" href="order_details.php?id=<?= (int) $header['id'] ?>">View order</a><?php endif; ?>
          </header>
          <div id="messages" class="conversation-thread" role="log" aria-live="polite" aria-relevant="additions text" aria-label="Conversation messages">
            <?php if (!$messages): ?><p id="emptyMessage" class="conversation-thread__empty">No messages yet. Send the bakery a message about your design.</p><?php endif; ?>
            <?php foreach ($messages as $message): ?><article class="conversation-message <?= $message['sender'] === 'customer' ? 'is-outgoing' : 'is-incoming' ?>" data-id="<?= (int) $message['id'] ?>"><div class="conversation-message__bubble"><?= nl2br(e($message['message'])) ?></div><time class="conversation-message__time" datetime="<?= e($message['created_at']) ?>"><?= e($message['created_at']) ?></time></article><?php endforeach; ?>
          </div>
          <form id="messageForm" class="conversation-composer">
            <label for="message" class="visually-hidden">Message</label>
            <input id="message" class="form-control" maxlength="1000" placeholder="Write a message to the bakery" autocomplete="off" required>
            <button id="sendMessage" class="btn btn-purple" type="submit"><i class="bi bi-send" aria-hidden="true"></i><span>Send</span></button>
          </form>
        </div>
      </div>
    </section>
  <?php endif; ?>
</main>
<script>
const csrf=<?= json_encode(csrf_token()) ?>;
const orderId=<?= $order ? (int) $order['id'] : 0 ?>;
const messages=document.getElementById('messages');
function escapeHtml(value){return String(value||'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));}
function appendMessage(message){if(!messages)return;document.getElementById('emptyMessage')?.remove();const row=document.createElement('article');row.className='conversation-message '+(message.sender==='customer'?'is-outgoing':'is-incoming');row.dataset.id=message.id;row.innerHTML='<div class="conversation-message__bubble">'+escapeHtml(message.message).replace(/\n/g,'<br>')+'</div><time class="conversation-message__time" datetime="'+escapeHtml(message.created_at)+'">'+escapeHtml(message.created_at)+'</time>';messages.appendChild(row);messages.scrollTop=messages.scrollHeight;}
document.getElementById('searchForm')?.addEventListener('submit',async event=>{event.preventDefault();const body=new FormData();body.set('csrf_token',csrf);body.set('action','search');body.set('order_number',document.getElementById('order_number').value);const response=await fetch('chat.php',{method:'POST',body,credentials:'same-origin'});const data=await response.json();if(data.success)location.href=data.redirect;else alert(data.message||'Conversation not found.');});
document.getElementById('messageForm')?.addEventListener('submit',async event=>{event.preventDefault();const input=document.getElementById('message'),button=document.getElementById('sendMessage'),body=new FormData();body.set('csrf_token',csrf);body.set('action','message');body.set('order_id',orderId);body.set('message',input.value);button.disabled=true;button.setAttribute('aria-busy','true');button.querySelector('span').textContent='Sending...';try{const response=await fetch('chat.php',{method:'POST',body,credentials:'same-origin'});const data=await response.json();if(data.success){appendMessage(data.message);input.value='';}else alert(data.message||'Unable to send message.');}catch(_){alert('Unable to send message. Please try again.');}finally{button.disabled=false;button.removeAttribute('aria-busy');button.querySelector('span').textContent='Send';}});
if(messages)messages.scrollTop=messages.scrollHeight;
</script>
<?php page_end();
