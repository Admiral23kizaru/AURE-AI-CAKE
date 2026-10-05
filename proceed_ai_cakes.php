<?php
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/page.php';
require __DIR__ . '/inc/ai_order_service.php';
require __DIR__ . '/inc/ai_design.php';

require_customer();

$id = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$number = mb_substr(trim((string) ($_GET['order_number'] ?? '')), 0, 100);
if ($id > 0) {
    $query = $pdo->prepare('SELECT * FROM ai_cake_orders WHERE id=? AND user_id=?');
    $query->execute([$id, user_id()]);
} else {
    $query = $pdo->prepare('SELECT * FROM ai_cake_orders WHERE order_number=? AND user_id=?');
    $query->execute([$number, user_id()]);
}
$order = $query->fetch();
if (!$order) {
    http_response_code(404);
    exit('AI design not found.');
}
$id = (int) $order['id'];

if (!empty($order['placed_at'])) {
    $header = $pdo->prepare('SELECT id FROM order_headers WHERE ai_order_id=? AND user_id=?');
    $header->execute([$id, user_id()]);
    $headerId = (int) $header->fetchColumn();
    if ($headerId > 0) {
        redirect('order_details.php?id=' . $headerId);
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    try {
        $date = trim((string) ($_POST['pickup_date'] ?? ''));
        $time = trim((string) ($_POST['pickup_time'] ?? ''));
        $size = trim((string) ($_POST['cake_size'] ?? ''));
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 1000);
        $payment = ($_POST['payment'] ?? 'cash') === 'gcash' ? 'gcash' : 'cash';
        $created = create_ai_order($pdo, $id, (int) user_id(), $date, $time, $size, $note, $payment);
        redirect('order_details.php?id=' . (int) $created['id']);
    } catch (Throwable $exception) {
        error_log('AI order placement: ' . $exception->getMessage());
        $error = $exception instanceof PDOException ? 'Unable to submit the AI cake order. Please try again.' : ($exception instanceof RuntimeException || $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unable to submit the AI cake order. Please try again.');
    }
}

$aiImageUrl = ai_design_image_url($order['picture']);
$hasAiImage = $aiImageUrl !== null && ai_design_image_exists($order['picture']);
page_start('Schedule AI cake');
?>
<main class="container py-4" style="max-width:860px">
  <a href="chat.php?order_id=<?= $id ?>">&larr; Back to conversation</a>
  <section class="app-card p-4 p-md-5 mt-3">
    <div class="row g-4">
      <div class="col-md-5">
        <div class="ai-design-thumbnail ai-design-thumbnail--customer"><?php if ($hasAiImage): ?><img src="<?= e($aiImageUrl) ?>" class="img-fluid" alt="Generated cake design" loading="lazy" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');"><?php endif; ?><span class="ai-design-thumbnail__fallback<?= $hasAiImage ? ' d-none' : '' ?>" role="img" aria-label="Generated cake design image unavailable"><i class="bi bi-image" aria-hidden="true"></i><span>Design image unavailable</span></span></div>
        <div class="eyebrow mt-3"><?= e($order['order_number']) ?></div>
        <p><?= e($order['personalize']) ?></p>
      </div>
      <div class="col-md-7">
        <h1 class="h3">Schedule your AI cake</h1>
        <p class="help-text">Choose an available production date and pickup time. Staff will price the design before confirming it.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" id="aiOrderForm">
          <?= csrf_field() ?>
          <input type="hidden" name="order_id" value="<?= $id ?>">
          <label class="form-label" for="cake_size">Cake size</label>
          <select id="cake_size" name="cake_size" class="form-select" required><option>Small</option><option>Medium</option><option>Large</option></select>
          <label class="form-label mt-3" for="pickup_date">Pickup date</label>
          <input id="pickup_date" type="date" name="pickup_date" min="<?= date('Y-m-d') ?>" class="form-control" required>
          <label class="form-label mt-3" for="pickup_time">Pickup time</label>
          <select id="pickup_time" name="pickup_time" class="form-select" disabled required><option value="">Choose a date and size first</option></select>
          <div id="availabilityMessage" class="help-text mt-2"></div>
          <label class="form-label mt-3" for="note">Note for the baker</label>
          <textarea id="note" name="note" maxlength="1000" class="form-control"></textarea>
          <fieldset class="mt-3"><legend class="form-label">Payment method</legend><div class="form-check"><input id="cash" type="radio" name="payment" value="cash" checked class="form-check-input"><label for="cash">Cash at pickup</label></div><div class="form-check"><input id="gcash" type="radio" name="payment" value="gcash" class="form-check-input"><label for="gcash">GCash after staff assigns the price</label></div></fieldset>
          <button id="submitOrder" class="btn btn-purple w-100 mt-4" disabled>Submit for pricing</button>
        </form>
      </div>
    </div>
  </section>
</main>
<script>
const dateInput=document.getElementById('pickup_date');
const sizeInput=document.getElementById('cake_size');
const timeInput=document.getElementById('pickup_time');
const submitButton=document.getElementById('submitOrder');
const message=document.getElementById('availabilityMessage');
function displayTime(value){const parts=value.split(':');let hour=Number(parts[0]);const suffix=hour>=12?'PM':'AM';hour=hour%12||12;return hour+':'+parts[1]+' '+suffix;}
async function loadAvailability(){
  submitButton.disabled=true;
  if(!dateInput.value)return;
  timeInput.disabled=true;
  timeInput.innerHTML='<option value="">Loading availability...</option>';
  message.textContent='';
  try{
    const response=await fetch('availability.php?pickup_date='+encodeURIComponent(dateInput.value)+'&ai_size='+encodeURIComponent(sizeInput.value),{credentials:'same-origin',cache:'no-store'});
    const data=await response.json();
    if(!response.ok||!data.success)throw new Error(data.message||'Unable to load availability.');
    timeInput.innerHTML='';
    if(!data.ai_capacity||Number(data.ai_capacity.remaining)<1){
      timeInput.innerHTML='<option value="">No AI production capacity</option>';
      message.textContent='This size is fully booked or not configured for the selected date.';
      return;
    }
    if(!Array.isArray(data.slots)||!data.slots.length){
      timeInput.innerHTML='<option value="">No pickup slots available</option>';
      message.textContent='No pickup capacity is available for the selected date.';
      return;
    }
    data.slots.forEach(slot=>{const option=document.createElement('option');option.value=String(slot.time).slice(0,5);option.textContent=displayTime(option.value)+' ('+Number(slot.remaining||0)+' remaining)';timeInput.appendChild(option);});
    timeInput.disabled=false;
    submitButton.disabled=false;
    message.textContent=Number(data.ai_capacity.remaining)+' '+sizeInput.value.toLowerCase()+' AI cake space(s) remaining.';
  }catch(error){timeInput.innerHTML='<option value="">Availability unavailable</option>';message.textContent=error.message||'Unable to load availability.';}
}
dateInput.addEventListener('change',loadAvailability);
sizeInput.addEventListener('change',loadAvailability);
document.getElementById('aiOrderForm').addEventListener('submit',()=>{submitButton.disabled=true;submitButton.textContent='Submitting...';});
</script>
<?php page_end();
