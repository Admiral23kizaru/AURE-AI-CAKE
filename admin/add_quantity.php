<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/inventory_service.php';
require_admin_role();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') require_post_csrf();

/* ======================================================
   AJAX: INVENTORY + SALES (DATE RANGE)
====================================================== */
if (isset($_GET['ajax'])) {

    $cakeId = intval($_GET['cake_id'] ?? 0);
    $from   = $_GET['from'] ?? '';
    $to     = $_GET['to'] ?? '';

    if (!$cakeId || !$from || !$to) {
        echo json_encode(['success' => false]);
        exit;
    }

    // Cake info
    $cakeStmt = $pdo->prepare("
        SELECT cake_id, name, quantity
        FROM cakes
        WHERE id = ?
    ");
    $cakeStmt->execute([$cakeId]);
    $cake = $cakeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$cake) {
        echo json_encode(['success' => false]);
        exit;
    }

    // Inventory added in range
    $invStmt = $pdo->prepare("
        SELECT COALESCE(SUM(qty_change),0)
        FROM inventory_adjustments
        WHERE cake_id = ?
          AND DATE(created_at) BETWEEN ? AND ?
    ");
    $invStmt->execute([$cakeId, $from, $to]);
    $inventoryAdded = (int)$invStmt->fetchColumn();

    // Sold in range
    $soldStmt = $pdo->prepare("
        SELECT COALESCE(SUM(oi.qty),0)
        FROM order_items oi
        INNER JOIN order_headers o ON o.normal_order_id = oi.order_id
        WHERE oi.cake_id = ?
          AND o.status = 'Completed'
          AND o.payment_status = 'paid'
          AND DATE(o.completed_at) BETWEEN ? AND ?
    ");
    $soldStmt->execute([$cakeId, $from, $to]);
    $sold = (int)$soldStmt->fetchColumn();

    // Nothing found
    if ($inventoryAdded === 0 && $sold === 0) {
        echo json_encode([
            'success' => true,
            'empty' => true
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'empty' => false,
        'data' => [
            'cake_id' => $cake['cake_id'],
            'name' => $cake['name'],
            'inventory_added' => $inventoryAdded,
            'sold' => $sold,
            'stock' => (int)$cake['quantity'],
            'from' => $from,
            'to' => $to
        ]
    ]);
    exit;
}

/* ======================================================
   NORMAL POST: ADD / DELETE INVENTORY
====================================================== */
$inventoryError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust'])) {
    try {
        adjust_cake_inventory(
            $pdo,
            (int) ($_POST['cake_id'] ?? 0),
            (int) ($_POST['quantity_change'] ?? 0),
            (string) ($_POST['reason'] ?? ''),
            user_id(),
            (string) ($_POST['adjustment_type'] ?? 'correction')
        );
        header('Location: add_quantity.php?updated=1');
        exit;
    } catch (Throwable $error) {
        $inventoryError = $error->getMessage();
    }
}

/* ======================================================
   PAGE DATA
====================================================== */
$cakes = $pdo->query("
    SELECT id, cake_id, name
    FROM cakes
    ORDER BY name
")->fetchAll();

$adjustments = $pdo->query("
    SELECT ia.*, c.name, u.name AS actor_name
    FROM inventory_adjustments ia
    JOIN cakes c ON c.id = ia.cake_id
    LEFT JOIN users u ON u.id = ia.actor_user_id
    ORDER BY ia.created_at DESC
")->fetchAll();

$today = date('Y-m-d');
?>

<?php include __DIR__ . '/../inc/header.php'; ?>

<div class="container py-4">

<!--<h4 class="mb-3">Inventory Monitoring</h4>-->

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Inventory Monitoring</h4>
        <div class="text-muted small">Cake Sold & Cake Stock Overview</div>
    </div>
    <a href="dashboard.php" class="btn btn-outline-primary">
        ← Back
    </a>
</div>



<?php if ($inventoryError): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($inventoryError) ?></div><?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="alert alert-success" role="status">Inventory adjustment recorded.</div><?php endif; ?>

<!-- ADJUST INVENTORY -->
<div class="card p-3 mb-4">
<h6>Record Inventory Adjustment</h6>
<form method="post" class="row g-2"><?= csrf_field() ?>
  <div class="col-md-3">
    <label class="form-label">Cake</label>
    <select name="cake_id" class="form-select" required>
      <option value="">Select Cake</option>
      <?php foreach ($cakes as $c): ?>
        <option value="<?= $c['id'] ?>">
          <?= htmlspecialchars($c['cake_id'].' - '.$c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-2">
    <label class="form-label">Stock change</label>
    <input type="number" name="quantity_change" class="form-control" placeholder="10 or -2" required>
  </div>

  <div class="col-md-2">
    <label class="form-label">Type</label>
    <select name="adjustment_type" class="form-select"><option value="purchase">Purchase</option><option value="correction">Correction</option></select>
  </div>

  <div class="col-md-3">
    <label class="form-label">Reason</label>
    <input type="text" name="reason" minlength="3" maxlength="255" class="form-control" required>
  </div>

  <div class="col-md-2 d-flex align-items-end">
    <button name="adjust" class="btn btn-success w-100">Record</button>
  </div>
</form>
</div>

<!-- INVENTORY ADJUSTMENTS -->
<div class="card p-3 mb-4">
<h6>Inventory Adjustments (Monitoring)</h6>
<table class="table table-sm table-striped">
<thead>
<tr>
  <th>Date</th>
  <th>Cake</th>
  <th>Type</th>
  <th>Change</th>
  <th>Result</th>
  <th>Reason</th>
  <th>Actor</th>
</tr>
</thead>
<tbody>
<?php if (!$adjustments): ?>
<tr><td colspan="7" class="text-muted text-center">No records</td></tr>
<?php else: foreach ($adjustments as $a): ?>
<tr>
<td><?= date('Y-m-d', strtotime($a['created_at'])) ?></td>
<td><?= htmlspecialchars($a['name']) ?></td>
<td><?= htmlspecialchars(ucfirst((string) $a['adjustment_type'])) ?></td>
<td><?= (int) $a['qty_change'] > 0 ? '+' : '' ?><?= (int) $a['qty_change'] ?></td>
<td><?= $a['resulting_stock'] === null ? 'Legacy' : (int) $a['resulting_stock'] ?></td>
<td><?= htmlspecialchars($a['note']) ?></td>
<td><?= htmlspecialchars($a['actor_name'] ?? 'System/legacy') ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<!-- INVENTORY SALES & STOCK -->
<div class="card p-3">
<h6>Inventory Sales & Stock Monitoring</h6>

<div class="row g-2 mb-3">
  <div class="col-md-3">
    <label class="form-label">Cake</label>
    <select id="cakeSelect" class="form-select">
      <option value="">Select Cake</option>
      <?php foreach ($cakes as $c): ?>
        <option value="<?= $c['id'] ?>">
          <?= htmlspecialchars($c['cake_id'].' - '.$c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-3">
    <label class="form-label">From Date</label>
    <input type="date" id="fromDate" class="form-control" value="<?= $today ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label">Until Date</label>
    <input type="date" id="toDate" class="form-control" value="<?= $today ?>">
  </div>

  <div class="col-md-3 d-flex align-items-end">
    <button id="applyBtn" class="btn btn-primary w-100">
      Apply Filters
    </button>
  </div>
</div>

<table class="table table-bordered text-center">
<thead class="table-light">
<tr>
  <th>Cake Code</th>
  <th>Cake Name</th>
  <th>Inventory Added</th>
  <th>Total Sold</th>
  <th>Date Range</th>
</tr>
</thead>
<tbody id="salesTableBody">
<tr><td colspan="5" class="text-muted">No data</td></tr>
</tbody>
</table>
</div>

</div>

<script>
const cake = document.getElementById('cakeSelect');
const from = document.getElementById('fromDate');
const to   = document.getElementById('toDate');
const btn  = document.getElementById('applyBtn');
const body = document.getElementById('salesTableBody');

btn.addEventListener('click', () => {

    if (!cake.value || !from.value || !to.value) {
        alert('Please select cake and date range');
        return;
    }

    body.innerHTML = `<tr><td colspan="5">Loading...</td></tr>`;

    fetch(`add_quantity.php?ajax=1&cake_id=${cake.value}&from=${from.value}&to=${to.value}`)
        .then(res => res.json())
        .then(res => {

            if (res.empty) {
                body.innerHTML = `
                  <tr>
                    <td colspan="5" class="text-muted">
                      No inventory or sales found in this date range
                    </td>
                  </tr>`;
                return;
            }

            const d = res.data;
            body.innerHTML = `
              <tr>
                <td>${d.cake_id}</td>
                <td>${d.name}</td>
                <td class="fw-bold text-primary">${d.inventory_added}</td>
                <td class="fw-bold text-success">${d.sold}</td>
                <td>${d.from} → ${d.to}</td>
              </tr>`;
        })
        .catch(() => {
            body.innerHTML = `<tr><td colspan="5" class="text-danger">Error loading data</td></tr>`;
        });
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
