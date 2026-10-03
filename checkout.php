
<?php
// checkout.php
// Expects inc/db.php to provide a PDO instance in $pdo (adjust if your project differs)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_customer();
header('Content-Type: text/html; charset=UTF-8');

// Fetch stores from DB
$stores = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT id, name, address, open_time, close_time FROM stores WHERE is_active=1 ORDER BY id LIMIT 1");
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // ignore DB errors for UI; $stores remains empty
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Checkout - Cake Shop</title>

 <!-- Logo Tab / Favicon -->
    <link rel="icon" type="image/png" href="/uploads/logo/logotab.png">
    <link rel="shortcut icon" type="image/png" href="/uploads/logo/logotab.png">
    
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{ --theme-purple:#7b2cbf; --muted:#6b6b6b; --danger:#dc3545; --success:#198754; }
    body{ background:#faf8ff; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial;color:#222; }

    .site-header{ background:#fff; box-shadow:0 1px 0 rgba(0,0,0,.06); }
    .brand img{ height:32px; }

    .menu-text{ padding:4px 10px; font-weight:700; color:var(--theme-purple); text-decoration:none; }

    .small-muted{ color:var(--muted); font-size:.9rem; }
    .card{ border-radius:10px; box-shadow:0 6px 18px rgba(15,23,42,0.03); }

    .section-title{ font-weight:800; color:var(--theme-purple); }
    label.required::after{ content:" *"; color:var(--danger); }

    .payment-option{ border:1px solid rgba(0,0,0,0.06); border-radius:8px; padding:10px; cursor:pointer; }
    .payment-option.selected{ border-color:var(--theme-purple); background:rgba(123,44,191,.06); }

    .personal-msg{ font-style:italic; background:#fffbe8; padding:6px 10px; border-radius:6px; margin-top:5px; display:inline-block; }

    @media(max-width:767px){ .card{ box-shadow:none; } }

    main.container { margin-top: 85px; }
    .site-header { z-index: 2000 !important; }
    .card { position: relative; z-index: 1; }

    /* UI hints */
    #storeHours { font-size: .9rem; color: #444; margin-top:6px; }
    #pickupAdjustMsg { font-size: .9rem; margin-top:6px; color: var(--danger); }
  </style>
</head>
<body>

<header class="site-header position-sticky top-0">
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between py-2">
      <a class="brand ms-1" href="index.php"><img src="logo.png" alt="logo"></a>
      <a href="view-all.php" class="menu-text">Menu</a>
    </div>
  </div>
</header>

<main class="container my-4">
  <div class="row g-4">
    <div class="col-12">
      <div class="card p-3">

        <!-- ORDER ITEMS -->
        <h5 class="section-title">Order details</h5>
        <div id="checkoutItems" class="mt-3"></div>

        <!-- TOTALS -->
        <div class="mt-3">
          <div class="d-flex justify-content-between"><span class="small-muted">Subtotal</span><span id="summarySubtotal" class="fw-bold">₱0.00</span></div>
          <span id="summaryPickupFee" class="d-none">&#8369;0.00</span>
          <div class="d-flex justify-content-between mt-1"><span class="small-muted">Discount</span><span id="summaryDiscount" class="fw-bold">₱0.00</span></div>
          <hr>
          <div class="d-flex justify-content-between"><span class="section-title">Total</span><span id="summaryTotal" class="section-title">₱0.00</span></div>
        </div>

        <hr>

        <!-- PICKUP DETAILS -->
        <h6 class="section-title mt-2">Pick-up details</h6>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label required">Pick-up date</label>
            <input id="pickupDate" type="date" class="form-control">
          </div>

          <div class="col-md-4">
            <label class="form-label required">Pick-up time</label>
            <!-- dropdown of slots (display in 12-hour AM/PM) -->
            <select id="pickupTimeSelect" class="form-select"></select>
            <div id="storeHours" class="small-muted"></div>
            <div id="pickupAdjustMsg"></div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Pickup location</label>
            <select id="pickupStoreSelect" class="visually-hidden" aria-hidden="true" tabindex="-1">
              <?php if (empty($stores)): ?>
                <option value="">No stores available</option>
              <?php else: ?>
                <?php foreach($stores as $s):
                  $id = htmlspecialchars($s['id'], ENT_QUOTES);
                  $name = htmlspecialchars($s['name'], ENT_QUOTES);
                  $addr = htmlspecialchars($s['address'], ENT_QUOTES);
                  $open = htmlspecialchars(substr($s['open_time'],0,5), ENT_QUOTES);
                  $close = htmlspecialchars(substr($s['close_time'],0,5), ENT_QUOTES);
                ?>
                  <option value="<?= $id ?>" selected
                    data-name="<?= $name ?>"
                    data-address="<?= $addr ?>"
                    data-open="<?= $open ?>"
                    data-close="<?= $close ?>"
                  >
                    <?= $name ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
            <div class="form-control bg-light" aria-live="polite">
              <?= empty($stores) ? 'No pickup location configured' : htmlspecialchars((string) $stores[0]['name'], ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>

          <div class="col-12 mt-2">
            <label class="form-label">Store address</label>
            <input id="pickupAddress" type="text" class="form-control" readonly>
          </div>
        </div>

        <hr>

        <!-- CUSTOMER DETAILS -->
        <h6 class="section-title mt-2">Additional details</h6>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label required">Full name</label>
            <input id="custName" type="text" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label required">Mobile number</label>
            <input id="custPhone" type="tel" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Email (optional)</label>
            <input id="custEmail" type="email" class="form-control">

          </div>
        </div>

        <hr>

        <!-- PAYMENT METHOD -->
        <h6 class="section-title">Payment method</h6>

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <div id="optCash" class="payment-option selected">
              <strong>Cash on hand</strong><br>
              <span class="small-muted">Pay when you pick up</span>
            </div>
          </div>

          <div class="col-md-6">
            <div id="optGcash" class="payment-option" disabled>
              <strong>GCash</strong><br>
              <span class="small-muted">Reference number optional</span>
              <div id="gcashRefWrap" class="mt-2" style="display:none;">
                <input id="gcashRef" type="text" class="form-control" placeholder="GCash reference (optional)">
              </div>
            </div>
          </div>
        </div>

        <!-- PLACE ORDER -->
        <div class="d-grid">
          <button id="placeOrderBtn" class="btn btn-primary btn-lg" disabled>
            Place Order • <span id="placeOrderTotal">₱0.00</span>
          </button>
        </div>

      </div>
    </div>
  </div>
</main>


<!-- SUCCESS MODAL -->
<div class="modal fade" id="checkoutSuccessModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <i class="bi bi-check-circle-fill" style="font-size:2.5rem;color:var(--theme-purple)"></i>
      <h5 class="mt-3">Order placed!</h5>
      <p id="successMsg" class="small-muted">Thank you - your order has been recorded.</p>
      <a href="index.php" class="btn btn-outline-secondary mt-3">Continue shopping</a>
    </div>
  </div>
</div>

<!-- ERROR MODAL -->
<div class="modal fade" id="checkoutErrorModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <i class="bi bi-x-circle-fill" style="font-size:2.5rem;color:#dc3545"></i>
      <h5 class="mt-3">Error</h5>
      <p id="errorMsg" class="small-muted">Something went wrong. Try again.</p>
      <button class="btn btn-outline-secondary mt-3" data-bs-dismiss="modal">Close</button>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const CSRF_TOKEN = '<?= e(csrf_token()) ?>';
const CURRENT_USER_ID = <?= (int) user_id() ?>;
/* KEYS */
const CART_PICKUP_KEY = 'cart_pickup_schedule';
const SELECTED_STORE_ID_LS = 'selected_store_id';
const SELECTED_STORE_NAME_LS = 'selected_store_name';
const SELECTED_STORE_ADDRESS_LS = 'selected_store_address';
const SELECTED_STORE_OPEN_LS = 'selected_store_open';
const SELECTED_STORE_CLOSE_LS = 'selected_store_close';

const CART_KEY_GUEST = 'cart_guest_v1';
const CART_KEY_USER_PREFIX = 'cart_user_';
function cartStorageKey(){ return CURRENT_USER_ID ? CART_KEY_USER_PREFIX + CURRENT_USER_ID : CART_KEY_GUEST; }
function mergeGuestCart(){
  if(!CURRENT_USER_ID) return;
  let guest=[]; let customer=[];
  try { guest=JSON.parse(localStorage.getItem(CART_KEY_GUEST)||'[]'); } catch(e){}
  try { customer=JSON.parse(localStorage.getItem(cartStorageKey())||'[]'); } catch(e){}
  if(!Array.isArray(guest)||!guest.length) return;
  if(!Array.isArray(customer)) customer=[];
  const signature=item=>JSON.stringify({id:Number(item.id||0),addons:(item.addons||[]).map(a=>Number(a.id||0)).sort((a,b)=>a-b),options:(item.options||[]).map(o=>Number(o.id||0)).sort((a,b)=>a-b),message:String(item.personalization?.message||'')});
  guest.forEach(item=>{const key=signature(item);const existing=customer.find(x=>signature(x)===key);if(existing){existing.qty=Number(existing.qty||0)+Number(item.qty||0);existing.total=Number(existing.unitPrice||existing.price||0)*existing.qty;}else customer.push(item);});
  localStorage.setItem(cartStorageKey(),JSON.stringify(customer));
  localStorage.removeItem(CART_KEY_GUEST);
}
mergeGuestCart();
function readCart(){ return JSON.parse(localStorage.getItem(cartStorageKey()) || '[]'); }
function formatCurrency(n){ return '₱' + Number(n).toLocaleString(undefined,{minimumFractionDigits:2}); }

/* DOM */
const checkoutItemsEl = document.getElementById('checkoutItems');
const summarySubtotal = document.getElementById('summarySubtotal');
const summaryPickupFee = document.getElementById('summaryPickupFee');
const summaryDiscount = document.getElementById('summaryDiscount');
const summaryTotal = document.getElementById('summaryTotal');
const placeOrderTotal = document.getElementById('placeOrderTotal');

const pickupDate = document.getElementById('pickupDate');
const pickupTimeSelect = document.getElementById('pickupTimeSelect');
const pickupStoreSelect = document.getElementById('pickupStoreSelect');
const pickupAddress = document.getElementById('pickupAddress');

const storeHoursEl = document.getElementById('storeHours');
const pickupAdjustMsgEl = document.getElementById('pickupAdjustMsg');

const custName = document.getElementById('custName');
const custPhone = document.getElementById('custPhone');
const custEmail = document.getElementById('custEmail');

const optCash = document.getElementById('optCash');
const optGcash = document.getElementById('optGcash');
const gcashRefWrap = document.getElementById('gcashRefWrap');

let selectedPayment = "cash";
optCash.onclick = ()=>selectPayment("cash");
optGcash.onclick = ()=>selectPayment("gcash");
function selectPayment(m){
  selectedPayment = m;
  optCash.classList.remove("selected");
  optGcash.classList.remove("selected");
  if(m==="cash"){ optCash.classList.add("selected"); gcashRefWrap.style.display="none"; }
  else{ optGcash.classList.add("selected"); gcashRefWrap.style.display="block"; }
}

/* render cart items */
function renderCheckoutItems(){
  const items = readCart();
  checkoutItemsEl.innerHTML = "";
  items.forEach(it=>{
    const wrap = document.createElement("div");
    wrap.className = "d-flex gap-3 align-items-start mb-3";
    wrap.innerHTML = `
      <img src="${it.picture ? "uploads/"+it.picture : "assets/img/cake-placeholder.png"}"
           style="width:84px;height:64px;object-fit:cover;border-radius:8px">
      <div style="flex:1">
        <div class="fw-bold">${it.name}</div>
        <div class="small-muted">${it.qty} × ${formatCurrency(it.unitPrice || it.price)}</div>
        ${it.addons?.length ? `<div class='small-muted mt-1'>Addons: ${it.addons.map(a=>a.name+" (₱"+a.price+")").join(", ")}</div>` : ""}
        ${it.personalization?.message ? `<div class="small-muted fw-bold mt-2">Personalized Cake:</div><div class="personal-msg">${it.personalization.message}</div>` : ""}
      </div>
      <div class="fw-bold" style="min-width:120px;text-align:right;">${formatCurrency(it.total)}</div>
    `;
    checkoutItemsEl.appendChild(wrap);
  });
  updateSummary();
}
function computeSubtotal(){ return readCart().reduce((a,b)=>a + (b.total || (b.qty*b.price)), 0); }
function updateSummary(){
  const subtotal = computeSubtotal();
  summarySubtotal.textContent = formatCurrency(subtotal);
  summaryPickupFee.textContent = "₱0.00";
  summaryDiscount.textContent = "₱0.00";
  summaryTotal.textContent = formatCurrency(subtotal);
  placeOrderTotal.textContent = formatCurrency(subtotal);
  validateForm();
}

/* TIME helpers (Manila/PHT) */
function pad(n){ return String(n).padStart(2,'0'); }
function getManilaNow(){
  const localNow = new Date();
  const utcMs = localNow.getTime() + (localNow.getTimezoneOffset() * 60000);
  const manilaMs = utcMs + (8 * 60 * 60 * 1000);
  return new Date(manilaMs);
}
function getManilaHHMM(){
  const m = getManilaNow();
  return pad(m.getHours()) + ':' + pad(m.getMinutes());
}
function timeToMinutes(hhmm){ if(!hhmm) return 0; const [h,m] = hhmm.split(':').map(x=>parseInt(x,10) || 0); return h*60 + m; }
function minutesToHHMM(mins){ mins = Math.max(0, Math.round(mins)); const hh = Math.floor(mins/60); const mm = mins % 60; return pad(hh)+':'+pad(mm); }
function minutesTo12(mins){
  mins = Math.max(0, Math.round(mins));
  let hh = Math.floor(mins/60);
  const mm = mins % 60;
  const ampm = hh >= 12 ? 'PM' : 'AM';
  hh = hh % 12;
  if (hh === 0) hh = 12;
  return `${hh}:${String(mm).padStart(2,'0')} ${ampm}`;
}
function roundUpToNextInterval(mins, interval){ return Math.ceil(mins / interval) * interval; }
function addDaysToDateString(dateStr, days){ const parts = dateStr.split('-').map(Number); const d = new Date(parts[0], parts[1]-1, parts[2]); d.setDate(d.getDate()+days); const yyyy = d.getFullYear(); const mm = String(d.getMonth()+1).padStart(2,'0'); const dd = String(d.getDate()).padStart(2,'0'); return `${yyyy}-${mm}-${dd}`; }

/* compute allowed earliest/latest (mins) for a date (Manila aware) */
function computeAllowedRangeForDate(dateStr, openMins, closeMins){
  const manilaNow = getManilaNow();
  const manilaTodayStr = `${manilaNow.getFullYear()}-${String(manilaNow.getMonth()+1).padStart(2,'0')}-${String(manilaNow.getDate()).padStart(2,'0')}`;
  const dateParts = dateStr.split('-').map(Number);
  const d = new Date(dateParts[0], dateParts[1]-1, dateParts[2]);
  const selDateStr = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;

  let earliest = 0, latest = 24*60 - 1;
  if (openMins !== null) earliest = openMins;
  if (closeMins !== null) latest = closeMins;
  if (selDateStr === manilaTodayStr) {
    const manilaNowMins = timeToMinutes(getManilaHHMM());
    earliest = Math.max(earliest, manilaNowMins);
  }
  if (earliest > latest) return null;
  return { earliest, latest };
}

/* generate 15-min slots and populate select - display text in 12-hour AM/PM */
function generateLegacySlotsForDate(dateStr){
  pickupAdjustMsgEl.textContent = '';
  storeHoursEl.textContent = '';

  const selectedOpt = pickupStoreSelect.selectedOptions[0];
  const openStr = (selectedOpt && selectedOpt.dataset.open) ? selectedOpt.dataset.open : (localStorage.getItem(SELECTED_STORE_OPEN_LS) || null);
  const closeStr = (selectedOpt && selectedOpt.dataset.close) ? selectedOpt.dataset.close : (localStorage.getItem(SELECTED_STORE_CLOSE_LS) || null);

  if (openStr && closeStr) storeHoursEl.textContent = `Store hours: ${openStr} - ${closeStr} (PHT)`;
  else if (openStr) storeHoursEl.textContent = `Store opens at ${openStr} (PHT)`;
  else if (closeStr) storeHoursEl.textContent = `Store closes at ${closeStr} (PHT)`;

  const openMins = openStr ? timeToMinutes(openStr.slice(0,5)) : null;
  const closeMins = closeStr ? timeToMinutes(closeStr.slice(0,5)) : null;

  const allowed = computeAllowedRangeForDate(dateStr, openMins, closeMins);
  pickupTimeSelect.innerHTML = '';

  if (!allowed) {
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = 'No available slots';
    pickupTimeSelect.appendChild(opt);
    pickupTimeSelect.disabled = true;
    pickupAdjustMsgEl.textContent = 'Store not available for the selected date (PHT).';
    return;
  }

  const earliestSnap = roundUpToNextInterval(allowed.earliest, 15);
  const latestSnap = Math.floor(allowed.latest / 15) * 15;
  if (earliestSnap > latestSnap) {
    const opt = document.createElement('option'); opt.value = ''; opt.textContent = 'No available slots'; pickupTimeSelect.appendChild(opt); pickupTimeSelect.disabled = true;
    pickupAdjustMsgEl.textContent = 'No 15-minute slot available for this date.';
    return;
  }

  const slots = [];
  for (let m = earliestSnap; m <= latestSnap; m += 15) slots.push(m);

  // populate select: value = "HH:MM" (24h), text = "h:mm AM/PM"
  slots.forEach(mins => {
    const hhmm = minutesToHHMM(mins);
    const opt = document.createElement('option');
    opt.value = hhmm;
    opt.textContent = minutesTo12(mins);
    pickupTimeSelect.appendChild(opt);
  });
  pickupTimeSelect.disabled = false;

  // try to preserve stored schedule if same store
  const scheduleRaw = localStorage.getItem(CART_PICKUP_KEY);
  const schedule = scheduleRaw ? JSON.parse(scheduleRaw) : {};
  const selectedOptVal = selectedOpt ? selectedOpt.value : null;
  let usedStored = false;
  if (schedule && schedule.store_id && selectedOptVal && String(schedule.store_id) === String(selectedOptVal) && schedule.time) {
    const st = schedule.time === 'now' ? minutesToHHMM(roundUpToNextInterval(timeToMinutes(getManilaHHMM()), 15)) : schedule.time.slice(0,5);
    const stMins = timeToMinutes(st);
    const idx = slots.findIndex(s => s === stMins);
    if (idx !== -1) {
      pickupTimeSelect.value = minutesToHHMM(slots[idx]);
      usedStored = true;
    } else {
      if (stMins >= earliestSnap && stMins <= latestSnap) {
        const snapped = roundUpToNextInterval(stMins, 15);
        const final = Math.min(snapped, latestSnap);
        pickupTimeSelect.value = minutesToHHMM(final);
        pickupAdjustMsgEl.textContent = `Stored time snapped to nearest available slot: ${minutesTo12(final)}.`;
        usedStored = true;
      }
    }
  }

  if (!pickupTimeSelect.value) {
    pickupTimeSelect.value = minutesToHHMM(earliestSnap);
  }

  validateForm();
}

async function generateSlotsForDate(dateStr){
  pickupAdjustMsgEl.textContent='';
      pickupTimeSelect.innerHTML='<option value="">Loading available slots...</option>';
  pickupTimeSelect.disabled=true;
  try{
    const response=await fetch('availability.php?pickup_date='+encodeURIComponent(dateStr),{credentials:'same-origin',headers:{'Accept':'application/json'},cache:'no-store'});
    const data=await response.json();
    if(!response.ok||!data.success) throw new Error(data.message||'Unable to load pickup slots.');
    const slots=Array.isArray(data.slots)?data.slots:[];
    pickupTimeSelect.innerHTML='';
    if(!slots.length){
      pickupTimeSelect.innerHTML='<option value="">No configured slots available</option>';
      pickupAdjustMsgEl.textContent='No pickup capacity is available for this date.';
      validateForm();
      return;
    }
    slots.forEach(slot=>{
      const opt=document.createElement('option');
      opt.value=String(slot.time).slice(0,5);
      opt.textContent=minutesTo12(timeToMinutes(opt.value))+' ('+Number(slot.remaining||0)+' remaining)';
      pickupTimeSelect.appendChild(opt);
    });
    pickupTimeSelect.disabled=false;
    const savedRaw=localStorage.getItem(CART_PICKUP_KEY);
    const saved=savedRaw?JSON.parse(savedRaw):{};
    if(saved.date===dateStr&&saved.time&&slots.some(slot=>String(slot.time).slice(0,5)===String(saved.time).slice(0,5))){
      pickupTimeSelect.value=String(saved.time).slice(0,5);
    }
  }catch(error){
    pickupTimeSelect.innerHTML='<option value="">Slots unavailable</option>';
    pickupAdjustMsgEl.textContent=error.message||'Unable to load pickup availability.';
  }
  validateForm();
}

/* store selection persistence */
function setSelectedStoreFromOption(opt){
  if(!opt) return;
  const id = opt.value;
  const name = opt.dataset.name || '';
  const addr = opt.dataset.address || '';
  const open = opt.dataset.open || '';
  const close = opt.dataset.close || '';
  if(id) {
    localStorage.setItem(SELECTED_STORE_ID_LS, id);
    localStorage.setItem(SELECTED_STORE_NAME_LS, name);
    localStorage.setItem(SELECTED_STORE_ADDRESS_LS, addr);
    if(open) localStorage.setItem(SELECTED_STORE_OPEN_LS, open); else localStorage.removeItem(SELECTED_STORE_OPEN_LS);
    if(close) localStorage.setItem(SELECTED_STORE_CLOSE_LS, close); else localStorage.removeItem(SELECTED_STORE_CLOSE_LS);
    pickupAddress.value = addr;
  } else {
    localStorage.removeItem(SELECTED_STORE_ID_LS);
    localStorage.removeItem(SELECTED_STORE_NAME_LS);
    localStorage.removeItem(SELECTED_STORE_ADDRESS_LS);
    localStorage.removeItem(SELECTED_STORE_OPEN_LS);
    localStorage.removeItem(SELECTED_STORE_CLOSE_LS);
    pickupAddress.value = '';
  }
}
function handleStoreSelectionChange(){
  const opt = pickupStoreSelect.selectedOptions[0];
  setSelectedStoreFromOption(opt);
  const date = pickupDate.value || new Date().toISOString().slice(0,10);
  generateSlotsForDate(date);
}

/* date change handler (Manila-aware) */
function handlePickupDateChange(){
  const manilaNow = getManilaNow();
  const yyyy = manilaNow.getFullYear();
  const mm = String(manilaNow.getMonth()+1).padStart(2,'0');
  const dd = String(manilaNow.getDate()).padStart(2,'0');
  const minDate = `${yyyy}-${mm}-${dd}`;
  if (!pickupDate.min) pickupDate.min = minDate;
  if (pickupDate.value && pickupDate.value < minDate) {
    const old = pickupDate.value; pickupDate.value = minDate;
    pickupAdjustMsgEl.textContent = `Selected date (${old}) is in the past (PHT); changed to ${minDate}.`;
  } else {
    pickupAdjustMsgEl.textContent = '';
  }
  generateSlotsForDate(pickupDate.value || minDate);
}

/* select change handler */
pickupTimeSelect.addEventListener('change', ()=>{ pickupAdjustMsgEl.textContent = ''; validateForm(); });

/* basic validation */
function validateForm(){
  const email = custEmail.value.trim();

  const emailValid = !email || /^\S+@\S+\.\S+$/.test(email); //  optional

  const ok =
    custName.value.trim() &&
    custPhone.value.trim() &&
    emailValid &&
    pickupDate.value &&
    pickupTimeSelect.value &&
    pickupStoreSelect.value;

  document.getElementById('placeOrderBtn').disabled = !ok;
}


/* helper: convert data URL to blob and trigger download */
async function downloadDataUrl(dataUrl, filename) {
  try {
    if (!dataUrl) throw new Error('No dataUrl provided');
    if (dataUrl.startsWith('data:')) {
      const parts = dataUrl.split(',');
      if (parts.length === 2) {
        const meta = parts[0];
        const b64 = parts[1];
        const binary = atob(b64);
        const len = binary.length;
        const arr = new Uint8Array(len);
        for (let i = 0; i < len; i++) arr[i] = binary.charCodeAt(i);
        const mime = (meta.split(';')[0].split(':')[1]) || 'image/png';
        const blob = new Blob([arr], { type: mime });
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(()=> { URL.revokeObjectURL(blobUrl); a.remove(); }, 1000);
        return true;
      }
    }
    // fallback: open in new tab for manual save
    window.open(dataUrl, '_blank');
    return false;
  } catch (err) {
    console.error('downloadDataUrl error', err);
    try { window.open(dataUrl, '_blank'); } catch(e){ /* swallow */ }
    return false;
  }
}

/* Place order - improved: handles receipt_png auto-download */
document.getElementById('placeOrderBtn').onclick = async ()=> {

  /* ============================
    GCASH FLOW (FIXED)
   ============================ */
    if (selectedPayment === 'gcash') {
    
      const cart = readCart();
      if (!cart.length) {
        showError("Your cart is empty.");
        return;
      }
    
      const payload = {
        payment: 'gcash',
        customer_name: custName.value.trim(),
        customer_email: custEmail.value.trim(),
        customer_phone: custPhone.value.trim(),
        pickup_date: pickupDate.value,
        pickup_time: pickupTimeSelect.value,
        store_id: pickupStoreSelect.value,
        store_name: pickupStoreSelect.selectedOptions[0]?.dataset.name || '',
        store_address: pickupStoreSelect.selectedOptions[0]?.dataset.address || '',
        subtotal: computeSubtotal(),
        total: computeSubtotal(),
        cart: cart
      };
    
      try {
        const res = await fetch('gcash_payment.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN},
          body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        
        if (!data.success) {
          showError(data.message || 'GCash order failed');
          return;
        }
        
        window.location.href = data.redirect;

    
      } catch (err) {
        console.error(err);
        showError('Network error. Please try again.');
      }
    
      return; //  stop cash flow
    }


  /* ============================
      CASH FLOW (ORIGINAL)
     ============================ */

  const btn = document.getElementById('placeOrderBtn');
  btn.disabled = true;
  const originalText = btn.innerHTML;
  btn.innerHTML = 'Placing order...';

  const cart = readCart();
  if(!cart.length){
    showError("Your cart is empty.");
    btn.disabled = false;
    btn.innerHTML = originalText;
    return;
  }

  const selectedOpt = pickupStoreSelect.selectedOptions[0];
  const storeId = selectedOpt ? selectedOpt.value : '';
  const storeName = selectedOpt ? selectedOpt.dataset.name : '';
  const storeAddress = selectedOpt ? selectedOpt.dataset.address : '';

  const manilaNow = getManilaNow();
  const minDateStr = `${manilaNow.getFullYear()}-${String(manilaNow.getMonth()+1).padStart(2,'0')}-${String(manilaNow.getDate()).padStart(2,'0')}`;
  if (pickupDate.value < minDateStr) {
    showError("Pickup date cannot be in the past (PHT). Please select a valid date.");
    btn.disabled = false;
    btn.innerHTML = originalText;
    return;
  }

  const payload = {
    payment: selectedPayment,
    gcash_ref: document.getElementById('gcashRef')
      ? document.getElementById('gcashRef').value.trim()
      : '',
    customer_name: custName.value.trim(),
    customer_email: custEmail.value.trim(),
    customer_phone: custPhone.value.trim(),
    pickup_date: pickupDate.value,
    pickup_time: pickupTimeSelect.value,
    store_id: storeId,
    store_name: storeName,
    store_address: storeAddress,
    subtotal: computeSubtotal(),
    total: computeSubtotal(),
    cart: cart
  };

  try {
    const scheduleToSave = {
      date: payload.pickup_date,
      time: payload.pickup_time,
      store_id: storeId || undefined,
      open: localStorage.getItem(SELECTED_STORE_OPEN_LS) || (selectedOpt ? selectedOpt.dataset.open : undefined),
      close: localStorage.getItem(SELECTED_STORE_CLOSE_LS) || (selectedOpt ? selectedOpt.dataset.close : undefined)
    };
    localStorage.setItem(CART_PICKUP_KEY, JSON.stringify(scheduleToSave));

    if (storeId) {
      localStorage.setItem(SELECTED_STORE_ID_LS, storeId);
      localStorage.setItem(SELECTED_STORE_NAME_LS, storeName || '');
      localStorage.setItem(SELECTED_STORE_ADDRESS_LS, storeAddress || '');
      if (selectedOpt?.dataset.open) localStorage.setItem(SELECTED_STORE_OPEN_LS, selectedOpt.dataset.open);
      if (selectedOpt?.dataset.close) localStorage.setItem(SELECTED_STORE_CLOSE_LS, selectedOpt.dataset.close);
    }

    const res = await fetch('cash_payment.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN},
      body: JSON.stringify(payload),
      credentials: 'same-origin'
    });

    let data;
    try {
      data = await res.json();
    } catch (err) {
      console.error('Invalid JSON response', err);
      showError('Server error: invalid response.');
      btn.disabled = false;
      btn.innerHTML = originalText;
      return;
    }

    if (!res.ok || !data.success) {
      showError(data.message || 'Failed to place order.');
      btn.disabled = false;
      btn.innerHTML = originalText;
      return;
    }

    const modal = new bootstrap.Modal(document.getElementById('checkoutSuccessModal'));
    document.getElementById('successMsg').textContent =
      `Order placed! Order #: ${data.order_number}`;
    modal.show();

    const filename = `order_${data.order_number || Date.now()}.png`;

    if (data.receipt_png) {
      await downloadDataUrl(data.receipt_png, filename);
    }

    localStorage.removeItem(cartStorageKey());
    localStorage.setItem('last_order_number', data.order_number);
    setTimeout(()=>location.href="track_order.php", 1200);

  } catch(err){
    console.error(err);
    showError('Network or server error. Try again.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalText;
  }
};


function showError(msg){
  document.getElementById('errorMsg').textContent = msg;
  const modal = new bootstrap.Modal(document.getElementById('checkoutErrorModal'));
  modal.show();
}

/* Initialization */
document.addEventListener('DOMContentLoaded', () => {
  selectPayment("cash");
  renderCheckoutItems();

  // set Manila-based date min
  const manilaNow = getManilaNow();
  const todayStr = `${manilaNow.getFullYear()}-${String(manilaNow.getMonth()+1).padStart(2,'0')}-${String(manilaNow.getDate()).padStart(2,'0')}`;
  pickupDate.min = todayStr;

  // restore selected store from localStorage if present
  const lsStoreId = localStorage.getItem(SELECTED_STORE_ID_LS);
  if (lsStoreId) {
    const opt = Array.from(pickupStoreSelect.options).find(o => o.value === String(lsStoreId));
    if (opt) { pickupStoreSelect.value = opt.value; pickupAddress.value = opt.dataset.address || ''; setSelectedStoreFromOption(opt); }
  } else {
    for (let i=0;i<pickupStoreSelect.options.length;i++){
      const o = pickupStoreSelect.options[i];
      if (o.value) { pickupStoreSelect.value = o.value; pickupAddress.value = o.dataset.address || ''; setSelectedStoreFromOption(o); break; }
    }
  }

  // load saved schedule and set date/time if valid
  const scheduleRaw = localStorage.getItem(CART_PICKUP_KEY);
  const schedule = scheduleRaw ? JSON.parse(scheduleRaw) : {};
  const scheduledDate = schedule.date || todayStr;
  const sd = new Date(scheduledDate + 'T00:00:00');
  const manilaToday0 = new Date(manilaNow.getFullYear(), manilaNow.getMonth(), manilaNow.getDate());
  const useDate = (sd >= manilaToday0) ? scheduledDate : todayStr;
  pickupDate.value = useDate;

  // generate slots for current date (will attempt to preserve stored time if same store)
  generateSlotsForDate(pickupDate.value);

  validateForm();
});

/* listeners */
pickupStoreSelect.addEventListener('change', handleStoreSelectionChange);
pickupDate.addEventListener('input', handlePickupDateChange);
pickupDate.addEventListener('change', handlePickupDateChange);
[custName,custPhone,custEmail,pickupDate,pickupTimeSelect].forEach(el=>el.addEventListener("input",validateForm));
</script>

</body>
</html>



