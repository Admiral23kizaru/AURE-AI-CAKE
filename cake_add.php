<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
header('Content-Type: text/html; charset=UTF-8');

// Helper for images (cakes)
function cake_image($path){
    if(!$path) return 'assets/img/cake-placeholder.png';
    return 'uploads/' . $path;
}
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES|ENT_SUBSTITUTE); }

// Robust quantity helper (prefers your 'quantity' column)
function get_quantity_value(array $row) {
    $possible = ['quantity', 'stock', 'qty', 'remaining', 'available_qty', 'instock'];
    foreach ($possible as $col) {
        if (array_key_exists($col, $row)) {
            $val = $row[$col];
            if ($val === null || $val === '') return 0;
            return intval($val);
        }
    }
    return null;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0){
    header('Location: view-all.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT c.*, cat.name AS category_name FROM cakes c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $cake = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$cake){
        header('Location: view-all.php');
        exit;
    }

    // available quantity (null if no such column)
    $availableQty = get_quantity_value($cake);
    if ($availableQty === null) {
        // fallback: if 'available' exists treat available=1 as 1, else 0
        $availableQty = isset($cake['available']) ? ($cake['available'] ? 1 : 0) : 0;
    }

    $price = (float)($cake['price'] ?? 0.0);

    // Fetch addons attached to this cake (if any)
    $addonsStmt = $pdo->prepare('SELECT a.id, a.name, a.is_free, a.price, a.quantity, a.picture FROM cakes_addons ca JOIN addons a ON a.id = ca.addon_id WHERE ca.cake_id = ? ORDER BY a.name');
    $addonsStmt->execute([$cake['id']]);
    $cakeAddons = $addonsStmt->fetchAll(PDO::FETCH_ASSOC);

    $optionsStmt = $pdo->prepare('SELECT o.*,co.is_required FROM cake_option_compatibility co JOIN cake_options o ON o.id=co.option_id WHERE co.cake_id=? AND o.is_active=1 ORDER BY o.option_type,o.name');
    $optionsStmt->execute([$cake['id']]);
    $cakeOptions = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);
    $optionsByType=[]; foreach($cakeOptions as $opt){ $optionsByType[$opt['option_type']][]=$opt; }

} catch (Exception $e) {
    header('Location: view-all.php');
    exit;
}

// helper to build addon image path (server)
function addon_image_path($filename){
    if (!$filename) return '';
    // relative to this script: uploads/addons/<file>
    return 'uploads/addons/' . $filename;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= esc($cake['name']) ?> — Add to cart</title>
  
   <!-- Logo Tab / Favicon -->
    <link rel="icon" type="image/png" href="/uploads/logo/logotab.png">
    <link rel="shortcut icon" type="image/png" href="/uploads/logo/logotab.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --theme-purple:#7b2cbf;
      --theme-light:#e9d8fd;
      --accent:#a05bdf;
      --muted:#6b6b6b;
      --card-bg:#fff;
      --selected-bg: #f6eefb;
      --selected-border: rgba(123,44,191,0.15);
    }

    html,body{ height:100%; }
    body{ font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial; background:#faf8ff; color:#222; margin:0; }

    header.py-2.bg-white.shadow-sm { background: #fff; box-shadow: 0 1px 0 rgba(0,0,0,.06); }
    header.py-2.bg-white.shadow-sm .container { padding: .5rem 1rem; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .brand { color: var(--theme-purple); font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
    .brand img { height:36px; width:auto; object-fit:contain; }
    .small-back { color:var(--theme-purple); text-decoration:none; font-weight:700; }

    .btn-primary {
      background: linear-gradient(180deg, var(--theme-purple), var(--accent));
      border-color: var(--theme-purple);
      color: #fff;
    }

    .page-main { padding: 14px 12px; max-width:1280px; margin:0 auto; }
    .card { border-radius:.6rem; box-shadow:0 8px 30px rgba(0,0,0,.04); background:var(--card-bg); border:0; display:flex; flex-direction:column; }

    .product-image { width:100%; height:420px; background:#f4f4f6; display:flex; align-items:center; justify-content:center; overflow:hidden; border-radius:.5rem; }
    .product-image img { width:100%; height:100%; object-fit:cover; display:block; }

    .name { font-size:1.28rem; font-weight:900; margin:0 0 6px 0; }
    .category { font-size:0.86rem; color:var(--muted); margin-bottom:.35rem; }
    .price { font-size:1.1rem; font-weight:900; color:var(--theme-purple); }

    .qty-controls { display:flex; gap:8px; align-items:center; }
    .qty-controls button { width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center; padding:0; border-radius:8px; font-size:1rem; }
    .qty-display { min-width:44px; text-align:center; font-weight:800; font-size:0.96rem; }

    .total-line { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:9px; border-radius:8px; background:#faf7ff; border:1px solid rgba(123,44,191,0.06); margin-top:10px; }
    .total-line .label { color:var(--muted); font-weight:700; font-size:.92rem; }
    .total-line .value { font-weight:900; color:var(--theme-purple); font-size:0.98rem; }

    .page-head { padding: 20px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .heading-title { margin:0; font-size:1.15rem; font-weight:800; color:#222; }

    /* Compact header */
    header.py-2.bg-white.shadow-sm .container { padding: 3px; }
    .brand img { height: 26px; }

    /* make description small and close under name */
    .desc-small { font-size:0.92rem; color:#444; margin-bottom:6px; }

    /* footer inside card that stays at bottom of the card */
    .card-body { padding:1rem; }
    .card-footer { padding:0.75rem; border-top:0; background:transparent; }
    .card-footer .footer-row { display:flex; gap:12px; align-items:center; }
    .footer-left { display:flex; gap:12px; align-items:center; }
    .footer-right { margin-left:auto; display:flex; gap:8px; align-items:center; }

    @media (max-width:576px){
      .product-image { height:220px; }
      .footer-right { margin-left:0; }
      .card-footer .footer-row { flex-direction:column; align-items:stretch; gap:8px; }
      .footer-right { justify-content:space-between; }
    }

    /* make available compressed */
    .available-small { font-size:0.78rem; color:var(--muted); font-weight:700; background:transparent; padding:4px 6px; border-radius:6px; }

    /* Addons section */
    .addons-note { margin-top:8px; font-size:.92rem; color:var(--muted); background:#fff7ff; padding:8px 10px; border-radius:8px; border:1px solid rgba(255,217,102,0.15); }
    .addons-list { margin-top:8px; padding:6px; border-radius:8px; background:#fff; border:1px solid rgba(0,0,0,0.04); box-shadow: 0 6px 18px rgba(15,23,42,0.03); }
    .addon-item {
      display:flex; align-items:center; gap:10px;
      padding:8px; border-radius:8px; cursor:pointer;
      transition: all .14s ease;
      border: 1px solid transparent;
      margin-bottom:6px;
      user-select: none;
      background: #fff;
    }
    .addon-item:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(12,16,30,0.04); }
    .addon-item.selected { background: var(--selected-bg); border-color: var(--selected-border); box-shadow: none; }
    .addon-item:last-child { margin-bottom: 0; }
    .addon-thumb { width:52px; height:52px; border-radius:6px; object-fit:cover; flex-shrink:0; background:#f4f4f6; display:inline-block; }
    .addon-name { font-weight:700; font-size:.95rem; }
    .addon-price { font-weight:700; color:var(--theme-purple); font-size:.92rem; margin-left:auto; }

    .no-addons { color:var(--muted); font-size:.92rem; padding:8px; }

    /* personalization block */
    .personalize { margin-top:12px; padding:10px; border-radius:8px; background:#fff; border:1px solid rgba(0,0,0,0.04); box-shadow: 0 4px 12px rgba(15,23,42,0.02); }
    .personalize label { font-weight:700; font-size:0.92rem; color:#333; }
    .personalize .form-control { border-radius:8px; }

    /* checkbox styling tweak */
    .form-check-input { margin-right: .5rem; width:1.05rem; height:1.05rem; }
    .addon-checkbox { display:inline-block; margin-right:10px; flex-shrink:0; }

    /* Disabled addon */
    .addon-item.disabled {
      opacity: 0.45;
      pointer-events: none;
      background: #f5f5f5;
    }
    
    .addon-out {
      font-size: 0.75rem;
      font-weight: 700;
      color: #dc3545;
    }
    
    .addon-stock {
      font-size: 0.72rem;
      font-weight: 700;
      color: #555;
    }
    
    .addon-stock.out {
      color: #dc3545;
    }


  </style>
</head>
<body>

  <!-- HEADER -->
  <header class="py-2 bg-white shadow-sm">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="view-all.php" class="brand d-flex align-items-center gap-2" aria-label="Home">
        <img src="logo.png" alt="Logo" style="height:36px; width:auto; object-fit:contain;">
      </a>
      <div>
        <a href="view-all.php" class="small-back">Menu</a>
      </div>
    </div>
  </header>

  <main class="page-main">
    <div class="page-head">
      <div>
        <div class="small text-muted">Add to cart</div>
        <h2 class="heading-title visually-hidden">Add to cart</h2>
      </div>
      <div class="text-muted small align-self-end">&nbsp;</div>
    </div>

    <div class="row g-4 flex-column flex-md-row">
      <!-- LEFT: Image -->
      <div class="col-12 col-md-6">
        <div class="product-image mb-3">
          <img id="zoomImage" src="<?= esc(cake_image($cake['picture'])) ?>" alt="<?= esc($cake['name']) ?>" style="cursor:zoom-in;">
        </div>

        <!-- Zoom Modal -->
        <div class="modal fade" id="zoomModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content bg-transparent border-0 shadow-none position-relative">
              <button type="button" class="btn-close btn-close-white position-absolute end-0 me-3 mt-3" data-bs-dismiss="modal" aria-label="Close"></button>
              <img src="<?= esc(cake_image($cake['picture'])) ?>" class="img-fluid rounded" alt="<?= esc($cake['name']) ?>" style="max-height:90vh; object-fit:contain;">
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: All details + footer -->
      <div class="col-12 col-md-6 d-flex">
        <div class="card flex-fill d-flex flex-column">
          <div class="card-body">
            <h1 class="name"><?= esc($cake['name']) ?></h1><form method="post" action="favorites.php" class="mb-2"><?=csrf_field()?><input type="hidden" name="favorite_type" value="cake"><input type="hidden" name="cake_id" value="<?=(int)$cake['id']?>"><button class="btn btn-sm btn-outline-primary">♡ Save favorite</button></form>
            <div class="desc-small"><?= nl2br(esc($cake['description'] ?? 'No description available.')) ?></div>

            <div class="d-flex align-items-center justify-content-between mt-2 mb-2">
              <div class="category available-small"><?= esc($cake['category_name'] ?? '') ?></div>
              <div class="price" id="basePrice">₱<?= number_format($price, 2) ?></div>
            </div>

            <div class="mb-3">
              <span class="small text-muted">Available</span>
              <div class="available-small d-inline-block ms-2" id="availableQtyDisplay"><?= $availableQty <= 0 ? '0' : (int)$availableQty ?></div>

              <?php foreach($optionsByType as $type=>$opts): $required=count(array_filter($opts,fn($x)=>(int)$x['is_required']===1))>0; ?><div class="mb-3"><label class="form-label fw-semibold" for="option-<?=esc($type)?>"><?=esc($type)?><?=$required?' *':' (optional)'?></label><select id="option-<?=esc($type)?>" class="form-select cake-option" data-type="<?=esc($type)?>" data-required="<?=$required?1:0?>" <?=$required?'required':''?>><option value="">Choose <?=esc(strtolower($type))?></option><?php foreach($opts as $opt):?><option value="<?=(int)$opt['id']?>" data-name="<?=esc($opt['name'])?>" data-price="<?=esc($opt['price_adjustment'])?>"><?=esc($opt['name'])?><?=((float)$opt['price_adjustment']!==0.0)?' (₱'.number_format((float)$opt['price_adjustment'],2).')':''?></option><?php endforeach;?></select></div><?php endforeach;?>              <!-- ADDONS NOTE -->
              <?php if(!empty($cakeAddons)): ?>
                <div class="addons-note">
                  Check free if you want to include in your order; if not, uncheck.
                </div>

                <div class="addons-list">
                <?php foreach($cakeAddons as $ad):
                    $qty = (int)($ad['quantity'] ?? 0);
                    $disabled = $qty <= 0;
                    $ad_price = (float)$ad['price'];
                    $add_img = $ad['picture'] ? addon_image_path($ad['picture']) : '';
                    $img_exists = $add_img && file_exists(__DIR__ . '/' . $add_img);
                ?>
                  <div class="addon-item <?= $disabled ? 'disabled' : '' ?>"
                       data-addon-id="<?= (int)$ad['id'] ?>"
                       data-addon-price="<?= $ad_price ?>"
                       data-addon-qty="<?= $qty ?>">
                
                    <input type="checkbox"
                           class="form-check-input addon-checkbox"
                           data-id="<?= (int)$ad['id'] ?>"
                           data-name="<?= esc($ad['name']) ?>"
                           data-price="<?= $ad_price ?>"
                           data-qty="<?= $qty ?>"
                           <?= $disabled ? 'disabled' : '' ?>>
                
                    <?php if($img_exists): ?>
                      <img src="<?= esc($add_img) ?>" class="addon-thumb">
                    <?php else: ?>
                      <div class="addon-thumb"></div>
                    <?php endif; ?>
                
                    <div>
                      <div class="addon-name"><?= esc($ad['name']) ?></div>
                        <div class="small text-muted">
                          <?= $ad['is_free'] ? 'Free' : 'Addon' ?>
                        
                          <?php if($qty > 0): ?>
                            <!--<div class="addon-stock">Stock: <?= $qty ?></div>-->
                          <?php else: ?>
                            <div class="addon-stock out">Out of stock</div>
                          <?php endif; ?>
                        </div>

                    </div>
                
                    <div class="addon-price">
                      <?= $ad['is_free'] ? 'Free' : '₱'.number_format($ad_price,2) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
                </div>


                <!-- PERSONALIZATION: only shown when addons exist -->
                <div class="personalize" aria-live="polite">
                  <label for="personalMsg">Personalize your cake (free)</label>
                  <div class="mt-2 mb-2">
                    <textarea id="personalMsg" rows="3" class="form-control" placeholder="Write a message to include with your order (e.g. 'Happy Birthday, Anna!')"></textarea>
                  </div>
                  <div class="form-text small text-muted mt-2">This message will be included with your order and shown in the cart. It is free.</div>
                </div>

              <?php else: ?>
                <div class="no-addons mt-2">No addons for this cake.</div>
                <!-- personalization intentionally not shown when no addons -->
              <?php endif; ?>

            </div>
          </div>

          <div class="card-footer mt-auto">
            <div class="footer-row">
              <div class="footer-left">
                <div>
                  <label class="form-label small text-muted mb-1">Quantity</label>
                  <div class="qty-controls" role="group" aria-label="Quantity controls">
                    <button id="qtyMinus" type="button" class="btn btn-outline-secondary" <?= $availableQty <= 0 ? 'disabled' : '' ?> aria-label="Decrease"><i class="bi bi-dash"></i></button>
                    <div id="qtyDisplay" class="qty-display"><?= $availableQty > 0 ? 1 : 0 ?></div>
                    <button id="qtyPlus" type="button" class="btn btn-outline-secondary" <?= $availableQty <= 0 ? 'disabled' : '' ?> aria-label="Increase"><i class="bi bi-plus"></i></button>
                  </div>
                </div>

                <div class="ms-3 d-none d-sm-block">
                  <button id="addToCartBtn" class="btn btn-primary">
                    <i class="bi bi-cart3 me-1"></i> Add to cart
                  </button>
                </div>
              </div>

              <div class="footer-right">
                <div class="total-line">
                  <div class="label">Total</div>
                  <div class="value" id="totalPrice">₱<?= number_format($price, 2) ?></div>
                </div>

                <div class="d-block d-sm-none">
                  <button id="addToCartBtnMobile" class="btn btn-primary w-100">
                    <i class="bi bi-cart3 me-1"></i> Add to cart
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </main>

<script>
(function(){
  window.APP = window.APP || {};
  window.APP.user = <?= json_encode(current_user(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  const cake = {
    id: <?= json_encode((int)$cake['id']) ?>,
    cake_id: <?= json_encode($cake['cake_id'] ?? '') ?>,
    name: <?= json_encode($cake['name']) ?>,
    price: <?= json_encode((float)$price) ?>,
    picture: <?= json_encode($cake['picture'] ?? '') ?>,
    availableQty: <?= json_encode((int)$availableQty) ?>,
  };

  const CART_KEY_GUEST = 'cart_guest_v1';
  const CART_KEY_USER_PREFIX = 'cart_user_';
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
    const signature = item => JSON.stringify({id:Number(item.id||0),addons:(item.addons||[]).map(a=>Number(a.id||0)).sort((a,b)=>a-b),options:(item.options||[]).map(o=>Number(o.id||0)).sort((a,b)=>a-b),message:String(item.personalization?.message||'')});
    guest.forEach(item=>{const key=signature(item);const existing=customer.find(x=>signature(x)===key);if(existing){existing.qty=Number(existing.qty||0)+Number(item.qty||0);existing.total=Number(existing.unitPrice||existing.price||0)*existing.qty;}else customer.push(item);});
    localStorage.setItem(cartStorageKey(),JSON.stringify(customer));
    localStorage.removeItem(CART_KEY_GUEST);
  }
  mergeGuestCart();

  function readCart(){
    try { return JSON.parse(localStorage.getItem(cartStorageKey()) || '[]'); }
    catch(e){ return []; }
  }
  function writeCart(items){
    localStorage.setItem(cartStorageKey(), JSON.stringify(items));
    document.dispatchEvent(new Event('cartUpdated'));
    window.dispatchEvent(new Event('cartUpdated'));
  }

  const qtyDisplay = document.getElementById('qtyDisplay');
  const qtyMinus = document.getElementById('qtyMinus');
  const qtyPlus = document.getElementById('qtyPlus');
  const addToCartBtn = document.getElementById('addToCartBtn');
  const addToCartBtnMobile = document.getElementById('addToCartBtnMobile');
  const totalPriceEl = document.getElementById('totalPrice');
  const availableQtyDisplay = document.getElementById('availableQtyDisplay');

  // personalization node (may not exist if no addons)
  const personalMsgInput = document.getElementById('personalMsg');

  // addon nodes
  const addonCheckboxes = Array.from(document.querySelectorAll('.addon-checkbox'));
  const addonPanels = Array.from(document.querySelectorAll('.addon-item'));
  const optionInputs = Array.from(document.querySelectorAll('.cake-option'));
  function getSelectedOptions(){ return optionInputs.filter(x=>x.value).map(x=>({id:Number(x.value),type:x.dataset.type,name:x.options[x.selectedIndex].dataset.name||'',price:Number(x.options[x.selectedIndex].dataset.price||0)})); }
  function requiredOptionsValid(){ return optionInputs.every(x=>x.dataset.required!=='1'||x.value); }

  // parse url params for edit flow
  const urlParams = new URLSearchParams(window.location.search);
  const urlQty = Number(urlParams.get('qty') || 0);
  const urlAddonsRaw = urlParams.get('addons') || ''; // e.g. "3,7"
  const urlReturn = urlParams.get('return') || ''; // e.g. "cart.php"
  // compute original addons key used by cart to match/update the exact line (if provided)
  function addonsKeyFromIdsRaw(raw){
    if(!raw) return '';
    const arr = String(raw).split(',').map(x=>Number(x)).filter(n=>!isNaN(n));
    return arr.sort((a,b)=>a-b).join(',');
  }
  const originalAddonsKey = addonsKeyFromIdsRaw(urlAddonsRaw);

  // If the URL contains personalization params (optional), prefill them
  const urlPersonalMsg = urlParams.get('pmsg') || '';

  // local draft key for personalization so user typing persists between reloads
  const PERSONAL_DRAFT_KEY = 'personal_draft_' + cake.id;

  let qty = cake.availableQty > 0 ? (urlQty > 0 ? urlQty : 1) : 0;
  const maxQty = cake.availableQty;

  function parsePrice(v){
    const n = Number(v);
    return isNaN(n) ? 0 : n;
  }

    function getSelectedAddons(){
      const list = [];
      addonCheckboxes.forEach(cb=>{
        const qty = Number(cb.dataset.qty || 0);
        if(cb.checked && qty > 0){
          list.push({
            id: parseInt(cb.dataset.id,10),
            name: cb.dataset.name || '',
            price: parsePrice(cb.dataset.price || 0)
          });
        }
      });
      return list;
    }


  function sumAddons(addons){
    return addons.reduce((s,a)=> s + (Number(a.price) || 0), 0);
  }

  function unitPriceWithAddons(){
    const addons = getSelectedAddons();
    const addonsSum = sumAddons(addons);
    return Number(cake.price || 0) + addonsSum + getSelectedOptions().reduce((sum,x)=>sum+Number(x.price||0),0);
  }

  function formatCurrency(n){
    return '₱' + Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
  }

  function updateTotal(){
    const unit = unitPriceWithAddons();
    const total = unit * qty;
    totalPriceEl.textContent = formatCurrency(total);
  }

  function updateUi(){
    qtyDisplay.textContent = qty;
    qtyMinus.disabled = qty <= 1;
    qtyPlus.disabled = qty >= maxQty;
    const disabled = (maxQty <= 0 || qty <= 0);
    if(addToCartBtn) addToCartBtn.disabled = disabled;
    if(addToCartBtnMobile) addToCartBtnMobile.disabled = disabled;
    if(maxQty <= 0){
      availableQtyDisplay.textContent = '0';
    } else {
      availableQtyDisplay.textContent = maxQty;
    }
    // update selected panel states
    addonCheckboxes.forEach(cb=>{
      const panel = cb.closest('.addon-item');
      if(panel){
        if(cb.checked) panel.classList.add('selected'); else panel.classList.remove('selected');
      }
    });
    updateTotal();
  }

  // preselect addons provided by URL (edit flow)
  function preselectAddonsFromUrl(){
    if(urlAddonsRaw){
      const wanted = (urlAddonsRaw || '').split(',').map(x=>String(x).trim()).filter(Boolean);
      addonCheckboxes.forEach(cb=>{
        const id = String(cb.dataset.id || cb.getAttribute('id')?.replace('addon_','') || '');
        if(wanted.includes(id)) cb.checked = true;
        cb.dispatchEvent(new Event('change', {bubbles:true}));
      });
    }
  }

  // attempt to prefill personalization and qty from cart when editing (so Edit in cart shows past personalization)
  function prefillFromCartIfEdit(){
    // personalization priority:
    // 1) matching cart entry personalization (if urlReturn present and entry exists)
    // 2) draft saved in localStorage (PERSONAL_DRAFT_KEY)
    // 3) urlPersonalMsg
    if(!urlReturn){
      // not an edit flow — still load draft or url param
      const draft = localStorage.getItem(PERSONAL_DRAFT_KEY);
      if(personalMsgInput){
        if(draft !== null){
          personalMsgInput.value = draft;
        } else if(urlPersonalMsg){
          personalMsgInput.value = urlPersonalMsg;
        }
      }
      return;
    }

    const items = readCart();
    // try to find matching cart entry by cake id + originalAddonsKey
    const idx = items.findIndex(i => Number(i.id) === Number(cake.id) && String(i.addonsKey || '') === String(originalAddonsKey || ''));
    if(idx >= 0){
      const entry = items[idx];
      // prefer URL qty if present (>0), otherwise use cart qty
      if(!urlQty || urlQty <= 0){
        qty = Number(entry.qty || qty);
      }
      // personalization from cart (preferred over url params and draft)
      if(personalMsgInput){
        if(entry.personalization && typeof entry.personalization.message !== 'undefined'){
          personalMsgInput.value = entry.personalization.message || '';
        } else {
          // fallback to draft or URL
          const draft = localStorage.getItem(PERSONAL_DRAFT_KEY);
          if(draft !== null){
            personalMsgInput.value = draft;
          } else if(urlPersonalMsg){
            personalMsgInput.value = urlPersonalMsg;
          }
        }
      }
    } else {
      // not found in cart — fill from draft or URL if provided
      if(personalMsgInput){
        const draft = localStorage.getItem(PERSONAL_DRAFT_KEY);
        if(draft !== null){
          personalMsgInput.value = draft;
        } else {
          if(urlPersonalMsg) personalMsgInput.value = urlPersonalMsg;
        }
      }
    }
  }

  qtyMinus.addEventListener('click', function(){ if(qty > 1) qty--; updateUi(); });
  qtyPlus.addEventListener('click', function(){ if(qty < maxQty) qty++; updateUi(); });

  // clicking the whole panel toggles checkbox
  addonPanels.forEach(panel=>{
    panel.addEventListener('click', function(e){
      // ignore clicks directly on interactive elements to avoid double toggle
      if(e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') )) {
        return;
      }
      const cb = panel.querySelector('.addon-checkbox');
      if(cb){
        cb.checked = !cb.checked;
        cb.dispatchEvent(new Event('change', {bubbles:true}));
      }
    });

    // keyboard accessibility (Enter/Space)
    panel.addEventListener('keydown', function(e){
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        const cb = panel.querySelector('.addon-checkbox');
        if(cb){
          cb.checked = !cb.checked;
          cb.dispatchEvent(new Event('change', {bubbles:true}));
        }
      }
    });
  });

  // when selecting/deselecting addons update total live
  addonCheckboxes.forEach(cb=>{ cb.addEventListener('change', updateUi); });
  optionInputs.forEach(input=>input.addEventListener('change',updateUi));

  // helper: build addonsKey from selected addons array
  function addonsKey(arr){
    return arr.map(a=>a.id).sort((a,b)=>a-b).join(',');
  }

  // save personalization draft while user types (only if textarea exists)
  if(personalMsgInput){
    personalMsgInput.addEventListener('input', function(){
      try {
        localStorage.setItem(PERSONAL_DRAFT_KEY, String(personalMsgInput.value || ''));
      } catch(e){}
    });
  }

  // --- Add / Update handler: if urlReturn is present attempt to update the matching cart line ---
  function addToCartHandler(){
    if(maxQty <= 0) return;
    if(qty <= 0) return;
    if(!requiredOptionsValid()){ alert('Please choose all required cake options.'); return; }

    const items = readCart();

    // compute selected addons and addonsSum
    const selectedAddons = getSelectedAddons();
    const addonsSum = sumAddons(selectedAddons);
    const selectedOptions = getSelectedOptions();
    const optionsSum = selectedOptions.reduce((sum,x)=>sum+Number(x.price||0),0);

    // build item unit price (cake + addons)
    const unitPrice = Number(cake.price || 0) + addonsSum + optionsSum;
    const itemTotal = unitPrice * qty;

    const selectedKey = addonsKey(selectedAddons);

    // collect personalization values (single message) — handle absent textarea gracefully
    const personalization = {
      message: (personalMsgInput && personalMsgInput.value) ? String(personalMsgInput.value).trim() : ''
    };

    // If we came from cart with a return param, try to find the original cart entry to update
    if(urlReturn){
      const idxToUpdate = items.findIndex(i => Number(i.id) === Number(cake.id) && String(i.addonsKey || '') === String(originalAddonsKey || ''));
      if(idxToUpdate >= 0){
        // Update that cart entry
        items[idxToUpdate].qty = qty;
        items[idxToUpdate].unitPrice = unitPrice;
        items[idxToUpdate].total = Number(unitPrice * qty);
        items[idxToUpdate].availableQty = cake.availableQty;
        items[idxToUpdate].addons = selectedAddons;
        items[idxToUpdate].options = selectedOptions;
        items[idxToUpdate].addonsKey = selectedKey;
        items[idxToUpdate].personalization = personalization;
        // persist
        writeCart(items);

        // also clear saved draft for this cake (since personalization now in cart)
        try { localStorage.removeItem(PERSONAL_DRAFT_KEY); } catch(e){}

        // feedback then return
        if(addToCartBtn){ addToCartBtn.innerHTML = '<i class="bi bi-check-lg"></i> Updated'; addToCartBtn.disabled = true; }
        if(addToCartBtnMobile){ addToCartBtnMobile.innerHTML = '<i class="bi bi-check-lg"></i> Updated'; addToCartBtnMobile.disabled = true; }

        setTimeout(()=>{ window.location.href = urlReturn; }, 350);
        return;
      }
      // if not found, fallthrough to merging logic below (adds as new or merges)
    }

    // find existing item that matches cake id + addonsKey
    const idx = items.findIndex(i => Number(i.id) === Number(cake.id) && (i.addonsKey || '') === selectedKey);
    if(idx >= 0){
      // merge quantity, but cap to maxQty
      const existing = items[idx];
      const desired = Math.min(maxQty, (Number(existing.qty) || 0) + qty);
      existing.qty = desired;
      existing.unitPrice = unitPrice;
      existing.total = existing.qty * existing.unitPrice;
      existing.availableQty = cake.availableQty;
      existing.addons = selectedAddons;
      existing.options = selectedOptions;
      existing.addonsKey = selectedKey;
      // merge/overwrite personalization (we'll overwrite with latest inputs)
      existing.personalization = personalization;
    } else {
      // push new item
      items.push({
        id: cake.id,
        cake_id: cake.cake_id,
        name: cake.name,
        price: cake.price,            // base cake price
        picture: cake.picture,
        qty: qty,
        availableQty: cake.availableQty,
        unitPrice: unitPrice,         // cake + addons per 1
        total: itemTotal,
        addons: selectedAddons,       // array of {id,name,price}
        options: selectedOptions,
        addonsKey: selectedKey,
        personalization: personalization
      });
    }

    // persist
    writeCart(items);

    // clear saved draft for this cake (persisted personalization now in cart)
    try { localStorage.removeItem(PERSONAL_DRAFT_KEY); } catch(e){}

    // feedback
    if(addToCartBtn){ addToCartBtn.innerHTML = '<i class="bi bi-check-lg"></i> Added'; addToCartBtn.disabled = true; }
    if(addToCartBtnMobile){ addToCartBtnMobile.innerHTML = '<i class="bi bi-check-lg"></i> Added'; addToCartBtnMobile.disabled = true; }

    // short delay then go back
    setTimeout(()=>{ window.location.href = 'view-all.php'; }, 600);
  }

  if(addToCartBtn) addToCartBtn.addEventListener('click', addToCartHandler);
  if(addToCartBtnMobile) addToCartBtnMobile.addEventListener('click', addToCartHandler);

  // initial preselect and UI
  preselectAddonsFromUrl();
  prefillFromCartIfEdit();
  updateUi();

  // Zoom image
  const zoomImage = document.getElementById('zoomImage');
  if(zoomImage){
    zoomImage.addEventListener('click', function(){
      var myModal = new bootstrap.Modal(document.getElementById('zoomModal'));
      myModal.show();
    });
  }

  // keep UI synced if localStorage from other tab changes
  window.addEventListener('storage', function(e){
    // if cart changed elsewhere and we're in edit flow, try to keep personalization in sync
    if(e.key === cartStorageKey()){
      // attempt to refill personalization if edit
      prefillFromCartIfEdit();
      updateUi();
    } else if (e.key === PERSONAL_DRAFT_KEY) {
      // draft changed elsewhere
      const draft = localStorage.getItem(PERSONAL_DRAFT_KEY);
      if(draft !== null && personalMsgInput){
        personalMsgInput.value = draft;
      }
      updateUi();
    } else {
      updateUi();
    }
  });

  // expose debug helpers if needed
  window.__CAKE_ADD_DEBUG = { cake, readCart, cartStorageKey };

})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
