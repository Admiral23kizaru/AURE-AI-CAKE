<?php
require_once __DIR__ . '/inc/auth.php';
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Your Cart — Cake Shop</title>
  
   <!-- Logo Tab / Favicon -->
    <link rel="icon" type="image/png" href="/uploads/logo/logotab.png">
    <link rel="shortcut icon" type="image/png" href="/uploads/logo/logotab.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{ --theme-purple:#7b2cbf; --muted:#6b6b6b; --danger:#dc3545; }
    body{ background:#faf8ff; font-family:system-ui, -apple-system, "Segoe UI", Roboto, Arial; color:#222; }

    .site-header{ background:#fff; box-shadow:0 1px 0 rgba(0,0,0,.06); }
    .brand img{ height:32px; }

    .menu-text { padding:4px 10px; font-weight:700; color:var(--theme-purple); text-decoration:none; }

    /* Desktop thumbnails */
    .cart-item-img{
      width:84px; height:64px; object-fit:cover; border-radius:.4rem; background:#fff; cursor:pointer;
    }

    /* smaller +/- buttons */
    .qty-btn {
      width:28px;
      height:28px;
      padding:0;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      border-radius:6px;
      font-size:0.85rem;
    }
    .qty-num { min-width:36px; display:inline-block; text-align:center; font-weight:700; }

    .item-row {
      /* switch to a 3-column grid: thumbnail | info | actions/price */
      display: grid;
      grid-template-columns: 84px 1fr 110px;
      gap: 12px;
      align-items: start;
      margin-bottom:1rem;
      padding: 10px 8px;
      border-radius: 8px;
      background: #ffffff;
      box-shadow: 0 6px 18px rgba(15,23,42,0.03);
    }
    .item-info { min-width:0; }
    .item-right { text-align:right; min-width:110px; display:flex; flex-direction:column; align-items:flex-end; gap:8px; }

    .small-muted { color:var(--muted); font-size:.9rem; }
    .small-danger { color: var(--danger); font-weight:700; }

    .btn-delete { color:var(--danger); border:0; background:transparent; padding:0; font-weight:700; cursor:pointer; }
    .btn-edit { border:0; background:transparent; padding:0 6px; color:var(--theme-purple); font-weight:700; cursor:pointer; }

    /* Zoom modal styling */
    #zoomModal .modal-content { background: transparent; border: 0; }
    #zoomModal .modal-body { display:flex; align-items:center; justify-content:center; padding:1rem; background:#000; }
    #zoomModal img { max-width:100%; max-height:80vh; object-fit:contain; }

    .btn-primary { background: linear-gradient(180deg, var(--theme-purple), #a05bdf); border-color: var(--theme-purple); color:#fff; }

    .addon-chip { display:inline-block; padding:4px 8px; border-radius:999px; font-size:0.82rem; background:#f4f3fb; margin-right:6px; margin-top:6px; }

    .personal-msg { font-style:italic; color:#444; background:#fffaf0; border-radius:6px; padding:6px 8px; margin-top:8px; display:inline-block; max-width:100%; word-break:break-word; }
    .personal-label { font-size:0.82rem; color:var(--muted); margin-top:6px; display:block; }

    /* Desktop: keep a subtle separation for the right column */
    .item-right .line-total { font-weight:800; color:var(--theme-purple); }

    /* Responsive adjustments */
    @media (max-width: 767.98px) {

      /* slightly smaller thumbnail on phones */
      .cart-item-img{ width:64px; height:56px; }

      /* grid becomes 2 columns on mobile: thumb + content; actions move under the content */
      .item-row {
        grid-template-columns: 64px 1fr;
        gap:10px;
        padding: 10px;
      }

      /* place the price/actions area below the info (full width) */
      .item-right {
        grid-column: 1 / -1;
        width: 100%;
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed rgba(0,0,0,0.05);
      }

      /* make the line total more prominent and aligned left on mobile */
      .item-right .line-total {
        font-weight:800;
        color:var(--theme-purple);
      }

      /* put edit/delete next to the total (compact) */
      .btn-edit, .btn-delete { font-size:0.95rem; padding-left:0.5rem; padding-right:0.5rem; }

      /* allow addon chips to wrap */
      .addon-chip { font-size:0.78rem; padding:3px 7px; }

      /* ensure personalization uses full width */
      .personal-msg { display:block; width:100%; margin-left:0; padding:8px; }

      /* make qty controls more compact & stacked if needed */
      .qty-btn { width:32px; height:32px; font-size:0.9rem; }
      .qty-num { min-width:34px; }

      /* subtotal/checkout card full width under list */
      .col-lg-4 { margin-top: 16px; }
      .card { box-shadow: none; border:1px solid rgba(0,0,0,0.03); }
    }

    @media (min-width: 768px) {
      /* slightly lift desktop rows */
      .item-row { background: transparent; box-shadow: none; padding: 0; border-radius: 0; }
    }

  </style>
</head>
<body>

<header class="site-header position-sticky top-0">
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between py-2">
      <div class="d-flex align-items-center gap-3">
        <button class="btn d-lg-none p-0" data-bs-toggle="offcanvas" data-bs-target="#leftSidebar" aria-label="Open menu">
          <i class="bi bi-list" style="font-size:1.4rem; color:var(--theme-purple)"></i>
        </button>

        <a class="brand ms-1" href="index.php" aria-label="Home">
          <img src="logo.png" alt="Cake Shop Logo">
        </a>
      </div>

      <div>
        <a id="openCartBtn" href="view-all.php" class="menu-text">Menu</a>
      </div>
    </div>
  </div>

  <div class="offcanvas offcanvas-start" tabindex="-1" id="leftSidebar">
    <div class="offcanvas-header">
      <a href="index.php" class="d-flex align-items-center gap-2 text-decoration-none">
        <img src="logo.png" alt="Cake Shop Logo" style="height:100px; width:auto;">
      </a>
      <button class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <a class="btn btn-primary w-100 mb-3" href="login.php">Login / Sign Up</a>
      <ul class="list-unstyled">
        <li><a href="view-all.php" class="text-decoration-none d-block py-2" style="color: var(--theme-purple);">Menu</a></li>
      </ul>
    </div>
  </div>
</header>

<main class="container my-4">
  <div class="row">
    <div class="col-lg-8">
      <h4>Your Cart</h4>
      <div id="cartList" class="mt-3" aria-live="polite"></div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <h6>Items</h6>
          <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
            <div class="small text-muted">Subtotal</div>
            <strong id="subtotalDisplay">₱0</strong>
          </div>
          <button id="checkoutBtn" class="btn btn-primary w-100 mt-3">Checkout</button>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Zoom modal -->
<div class="modal fade" id="zoomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-body position-relative">
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
        <img id="zoomModalImg" src="" alt="">
      </div>
    </div>
  </div>
</div>

<!-- Remove item confirm modal -->
<div class="modal fade" id="removeConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger">Remove Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="removeConfirmText">Are you sure you want to remove this item from your cart?</p>
      </div>
      <div class="modal-footer">
        <button id="removeCancel" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="removeConfirm" type="button" class="btn btn-danger">Remove</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
window.APP = window.APP || {};
window.APP.user = <?= json_encode(current_user(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
const CART_KEY_GUEST = 'cart_guest_v1';
const CART_KEY_USER_PREFIX = 'cart_user_';
const ASSETS_PLACEHOLDER = 'assets/img/cake-placeholder.png';

function isLoggedIn(){ return !!(window.APP && window.APP.user && window.APP.user.id); }
function getUserId(){ return isLoggedIn() ? window.APP.user.id : null; }
function cartStorageKey(){ return isLoggedIn() ? CART_KEY_USER_PREFIX + getUserId() : CART_KEY_GUEST; }

function mergeGuestCart(){
  if(!isLoggedIn()) return;
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

function escapeHtml(s){ return String(s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }
function readCart(){ try { return JSON.parse(localStorage.getItem(cartStorageKey()) || '[]'); } catch(e){ return []; } }

function sumAddons(addons){
  if(!Array.isArray(addons)) return 0;
  return addons.reduce((s,a)=> s + (Number(a.price || 0) || 0), 0);
}

function normalizeCart(items){
  items.forEach(it=>{
    it.qty = Number(it.qty || 0);
    if(!Number.isFinite(it.qty) || it.qty < 0) it.qty = 0;
    const base = Number(it.price || 0);
    const addonsSum = sumAddons(it.addons || []);
    it.unitPrice = Number(it.unitPrice || (base + addonsSum));
    it.total = Number(it.unitPrice * it.qty);
    it.availableQty = Number(it.availableQty || 0);
    // ensure personalization object shape (optional)
    if(typeof it.personalization === 'undefined') it.personalization = null;
  });
  return items;
}

function writeCart(items){
  const key = cartStorageKey();
  const normalized = normalizeCart(items);
  localStorage.setItem(key, JSON.stringify(normalized));
  updateSubtotal();
  document.dispatchEvent(new Event('cartUpdated'));
  window.dispatchEvent(new Event('cartUpdated'));
}

function computeSubtotal(){
  const items = readCart();
  let sum = 0;
  items.forEach(it=>{
    const t = Number(it.total || (Number(it.unitPrice || it.price || 0) * Number(it.qty || 0)));
    sum += isFinite(t) ? t : 0;
  });
  return sum;
}

function updateSubtotal(){
  const total = computeSubtotal();
  const el = document.getElementById('subtotalDisplay');
  if(el) el.textContent = formatCurrency(total);
}

function formatCurrency(n){
  if(!isFinite(n)) n = 0;
  return '₱' + Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
}

function buildSrcs(picture){
  if(!picture || String(picture).trim() === '') return [ASSETS_PLACEHOLDER];
  const fn = String(picture).trim();
  return ['uploads/' + fn, '/' + 'uploads/' + fn, ASSETS_PLACEHOLDER];
}

let pendingRemoveIndex = null;
let pendingRowRefs = null;

function renderCart(){
  const container = document.getElementById('cartList');
  const items = readCart();
  container.innerHTML = '';

  if(!items || items.length === 0){
    container.innerHTML = `<div class="text-muted">Your cart is empty. <a href="view-all.php">Continue shopping</a></div>`;
    updateSubtotal();
    return;
  }

  normalizeCart(items);

  items.forEach((it, idx) => {
    it.qty = Number(it.qty || 0);
    if(!Number.isFinite(it.qty) || it.qty < 0) it.qty = 0;
    const available = Number(it.availableQty ?? 0);

    const row = document.createElement('div');
    row.className = 'item-row';
    row.dataset.idx = String(idx);

    const srcs = buildSrcs(it.picture || it.img || '');
    const img = document.createElement('img');
    img.className = 'cart-item-img';
    img.alt = it.name || '';
    img.dataset.srcList = JSON.stringify(srcs);
    img.dataset.srcIndex = '0';
    img.src = srcs[0];
    img.onerror = function(){
      try {
        const list = JSON.parse(this.dataset.srcList || '[]');
        let k = Number(this.dataset.srcIndex || 0);
        k++;
        if(k < list.length){
          this.dataset.srcIndex = String(k);
          this.src = list[k];
        } else {
          this.src = ASSETS_PLACEHOLDER;
        }
      } catch(e){
        this.src = ASSETS_PLACEHOLDER;
      }
    };
    img.addEventListener('click', function(){
      document.getElementById('zoomModalImg').src = this.src || srcs[0] || ASSETS_PLACEHOLDER;
      new bootstrap.Modal(document.getElementById('zoomModal')).show();
    });
    row.appendChild(img);

    const info = document.createElement('div');
    info.className = 'item-info';
    const title = document.createElement('div'); title.className = 'fw-bold text-truncate'; title.textContent = it.name || 'Item';

    const unitPrice = Number(it.unitPrice || (Number(it.price || 0) + sumAddons(it.addons || [])));
    const meta = document.createElement('div'); meta.className = 'small text-muted';
    meta.innerHTML = `${formatCurrency(unitPrice)} × <strong class="qtyLabel">${it.qty}</strong>`;

    info.appendChild(title);
    info.appendChild(meta);

    // Addons chip list
    if(Array.isArray(it.addons) && it.addons.length > 0){
      const addonsWrap = document.createElement('div');
      addonsWrap.className = 'mt-2';
      it.addons.forEach(a=>{
        const chip = document.createElement('span');
        chip.className = 'addon-chip';
        chip.textContent = `${a.name}${(Number(a.price||0) ? ' • ₱' + Number(a.price).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : ' • Free')}`;
        addonsWrap.appendChild(chip);
      });
      info.appendChild(addonsWrap);
    }

    // Personalization: show only when present and not empty
    if(it.personalization && typeof it.personalization.message !== 'undefined' && String(it.personalization.message || '').trim() !== ''){
      const label = document.createElement('div');
      label.className = 'personal-label';
      label.textContent = 'Personalization';
      const pmsg = document.createElement('div');
      pmsg.className = 'personal-msg';
      pmsg.textContent = String(it.personalization.message || '');
      info.appendChild(label);
      info.appendChild(pmsg);
    }

    const controls = document.createElement('div'); controls.className = 'mt-2';
    const minus = document.createElement('button'); minus.className = 'btn btn-outline-secondary qty-btn'; minus.setAttribute('aria-label','Decrease'); minus.innerHTML = '<i class="bi bi-dash"></i>';
    const num = document.createElement('span'); num.className = 'qty-num'; num.textContent = it.qty;
    const plus = document.createElement('button'); plus.className = 'btn btn-outline-secondary qty-btn'; plus.setAttribute('aria-label','Increase'); plus.innerHTML = '<i class="bi bi-plus"></i>';
    const availEl = document.createElement('div'); availEl.className = 'small-muted mt-1'; availEl.textContent = `Available: ${available}`;
    controls.appendChild(minus); controls.appendChild(num); controls.appendChild(plus); controls.appendChild(availEl);
    info.appendChild(controls);

    const right = document.createElement('div'); right.className = 'item-right';
    const lineTotal = document.createElement('div'); lineTotal.className = 'line-total fw-bold mb-2';
    const computedLineTotal = Number(it.total || (unitPrice * it.qty));
    lineTotal.textContent = formatCurrency(computedLineTotal);

    // Delete button
    const deleteBtn = document.createElement('button'); deleteBtn.className = 'btn-delete me-2'; deleteBtn.type='button'; deleteBtn.textContent='Delete';
    // Edit button - only show when item has addons (so user can edit addon choices)
    let editBtn = null;
    if(Array.isArray(it.addons) && it.addons.length > 0){
      editBtn = document.createElement('button');
      editBtn.className = 'btn-edit me-2';
      editBtn.type = 'button';
      editBtn.title = 'Edit item (change addons/qty)';
      editBtn.textContent = 'Edit';
    }

    // append: show line total + actions
    right.appendChild(lineTotal);
    const actionsWrap = document.createElement('div');
    actionsWrap.style.display = 'flex';
    actionsWrap.style.gap = '8px';
    if(editBtn) actionsWrap.appendChild(editBtn);
    actionsWrap.appendChild(deleteBtn);
    right.appendChild(actionsWrap);

    row.appendChild(info); row.appendChild(right);
    container.appendChild(row);

    plus.disabled = (available <= 0) || (it.qty >= available);
    minus.disabled = (available <= 0);

    plus.addEventListener('click', function(){
      const key = cartStorageKey();
      const cartNow = JSON.parse(localStorage.getItem(key) || '[]');
      if(!cartNow[idx]) return;
      const cap = Number(cartNow[idx].availableQty || 9999);
      let curQty = Number(cartNow[idx].qty || 0);
      if(curQty < cap){
        curQty++;
        cartNow[idx].qty = curQty;
        const addonsSum = sumAddons(cartNow[idx].addons || []);
        cartNow[idx].unitPrice = Number(cartNow[idx].unitPrice || (Number(cartNow[idx].price || 0) + addonsSum));
        cartNow[idx].total = Number(cartNow[idx].unitPrice * cartNow[idx].qty);
        localStorage.setItem(key, JSON.stringify(cartNow));
        document.dispatchEvent(new Event('cartUpdated'));
        window.dispatchEvent(new Event('cartUpdated'));
        num.textContent = curQty;
        meta.querySelector('.qtyLabel').textContent = curQty;
        lineTotal.textContent = formatCurrency(cartNow[idx].total);
        plus.disabled = curQty >= cap;
        minus.disabled = false;
        updateSubtotal();
      } else {
        const original = plus.innerHTML;
        plus.innerHTML = 'Max';
        setTimeout(()=> plus.innerHTML = original, 700);
      }
    });

    minus.addEventListener('click', function(){
      const key = cartStorageKey();
      const cartNow = JSON.parse(localStorage.getItem(key) || '[]');
      if(!cartNow[idx]) return;
      let curQty = Number(cartNow[idx].qty || 0);

      if(curQty > 1){
        curQty = Math.max(1, curQty - 1);
        cartNow[idx].qty = curQty;
        const addonsSum = sumAddons(cartNow[idx].addons || []);
        cartNow[idx].unitPrice = Number(cartNow[idx].unitPrice || (Number(cartNow[idx].price || 0) + addonsSum));
        cartNow[idx].total = Number(cartNow[idx].unitPrice * cartNow[idx].qty);
        localStorage.setItem(cartStorageKey(), JSON.stringify(cartNow));
        document.dispatchEvent(new Event('cartUpdated'));
        window.dispatchEvent(new Event('cartUpdated'));
        num.textContent = curQty;
        meta.querySelector('.qtyLabel').textContent = curQty;
        lineTotal.textContent = formatCurrency(cartNow[idx].total);
        plus.disabled = curQty >= Number(cartNow[idx].availableQty || 9999);
        minus.disabled = false;
        updateSubtotal();
      } else {
        num.textContent = '0';
        meta.querySelector('.qtyLabel').textContent = '0';
        lineTotal.textContent = formatCurrency(0);
        pendingRemoveIndex = idx;
        pendingRowRefs = { num, meta, lineTotal, plus, minus };
        document.getElementById('removeConfirmText').textContent = `Remove "${cartNow[idx].name}" — Are you sure to remove this item from your cart?`;
        new bootstrap.Modal(document.getElementById('removeConfirmModal')).show();
      }
    });

    deleteBtn.addEventListener('click', function(){
      pendingRemoveIndex = idx;
      pendingRowRefs = { num, meta, lineTotal, plus, minus };
      document.getElementById('removeConfirmText').textContent = `Remove "${it.name}" — Are you sure to remove this item from your cart?`;
      new bootstrap.Modal(document.getElementById('removeConfirmModal')).show();
    });

    if(editBtn){
      editBtn.addEventListener('click', function(){
        // build addons id list (comma separated)
        const addonIds = (Array.isArray(it.addons) && it.addons.length>0) ? it.addons.map(a=>a.id).join(',') : '';
        const params = new URLSearchParams();
        params.set('id', it.id);
        params.set('qty', it.qty || 1);
        if(addonIds) params.set('addons', addonIds);
        // add return param so cake_add.php can redirect back
        params.set('return', 'cart.php');
        // If there's personalization, pass it to cake_add.php so user sees existing text
        if(it.personalization && typeof it.personalization.message !== 'undefined' && String(it.personalization.message || '').trim() !== ''){
          params.set('pmsg', String(it.personalization.message || ''));
        }
        // navigate
        window.location.href = 'cake_add.php?' + params.toString();
      });
    }

  }); // end foreach

  updateSubtotal();
}

document.getElementById('removeConfirm').addEventListener('click', function(){
  if(pendingRemoveIndex === null){
    bootstrap.Modal.getInstance(document.getElementById('removeConfirmModal'))?.hide();
    return;
  }
  const idx = pendingRemoveIndex;
  const key = cartStorageKey();
  const cartNow = JSON.parse(localStorage.getItem(key) || '[]');
  if(idx >= 0 && idx < cartNow.length){
    cartNow.splice(idx, 1);
    writeCart(cartNow);
  }
  pendingRemoveIndex = null;
  pendingRowRefs = null;
  bootstrap.Modal.getInstance(document.getElementById('removeConfirmModal'))?.hide();
  renderCart();
});

document.getElementById('removeCancel').addEventListener('click', function(){
  if(pendingRemoveIndex !== null && pendingRowRefs){
    const cartNow = readCart();
    const idx = pendingRemoveIndex;
    const storedQty = (cartNow[idx] && Number(cartNow[idx].qty)) ? Number(cartNow[idx].qty) : 1;
    try {
      pendingRowRefs.num.textContent = storedQty;
      const qLabel = pendingRowRefs.meta.querySelector('.qtyLabel');
      if(qLabel) qLabel.textContent = storedQty;
      pendingRowRefs.lineTotal.textContent = formatCurrency(Number(cartNow[idx]?.total || (Number(cartNow[idx]?.unitPrice || cartNow[idx]?.price || 0) * storedQty)));
      pendingRowRefs.plus.disabled = storedQty >= Number(cartNow[idx]?.availableQty || 9999);
      pendingRowRefs.minus.disabled = false;
    } catch(e){}
  }
  pendingRemoveIndex = null;
  pendingRowRefs = null;
});

// Updated: Checkout button now redirects to checkout.php (no confirm modal)
document.getElementById('checkoutBtn').addEventListener('click', function(){
  const items = readCart();
  if(!items || items.length === 0){ alert('Cart is empty'); return; }
  // Redirect to checkout page
  window.location.href = 'checkout.php';
});

// Removed confirmPlaceOrder modal logic — checkout now handled on checkout.php

document.addEventListener('cartUpdated', function(){ renderCart(); });
window.addEventListener('cartUpdated', function(){ renderCart(); });

window.addEventListener('storage', function(e){
  if(e.key === cartStorageKey()) renderCart();
});

document.addEventListener('DOMContentLoaded', function(){ renderCart(); updateSubtotal(); });

</script>

</body>
</html>
