<?php
// staff/dashboard.php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

// ensure only staff or admin (for supervisors) can view
if (!function_exists('require_staff_or_admin')) {
    // fallback: check session & role in admins table
    session_start();
    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        echo "Access denied. Staff login required.";
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, role, name FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$me || !in_array($me['role'], ['staff', 'admin'])) {
        http_response_code(403);
        echo "Access denied. Staff role required.";
        exit;
    }
} else {
    // use project's helper if available
    require_staff_or_admin();
}

// limited counts for staff view
$counts = $pdo->query('SELECT 
    (SELECT COUNT(*) FROM cakes) as cakes, 
    (SELECT COUNT(*) FROM order_headers WHERE order_type = "normal") as orders,
    (SELECT COUNT(*) FROM order_headers WHERE status = "Pending") as pending_orders,
    (SELECT COUNT(*) FROM order_headers WHERE order_type = "ai") as ai_orders
')->fetch(PDO::FETCH_ASSOC);

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

include __DIR__ . '/../inc/header.php';
?>

<style>
/* Borrowed admin dashboard styles — simplified for staff */
.admin-wrap {
  max-width: 1100px;
  margin: 1.5rem auto;
  padding: 0 1rem;
}
.dashboard-hero {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:1rem;
  margin-bottom:1rem;
  flex-wrap:wrap;
}
.brand-card {
  display:flex;
  gap:1rem;
  align-items:center;
}
.brand-card .logo {
  width:64px;
  height:64px;
  border-radius:12px;
  display:grid;
  place-items:center;
  background:linear-gradient(135deg,#fff 0%, #f7f7f7 100%);
  box-shadow: 0 6px 18px rgba(20,20,40,0.06);
  border:1px solid rgba(0,0,0,0.03);
}
.brand-card h2 { margin:0; font-size:1.25rem; letter-spacing:-0.2px; }
.brand-card .muted { color:#6b7280; font-size:.9rem; }

.actions {
  display:flex;
  gap:.5rem;
  flex-wrap:wrap;
}
.action-btn { min-width:140px; }

/* Stats grid */
.stats-grid {
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap:1rem;
  margin-top:1rem;
}

/* Stat cards */
.stat {
  background: linear-gradient(180deg, #ffffff 0%, #fbfbfc 100%);
  border-radius: 12px;
  padding: 1rem;
  box-shadow: 0 8px 24px rgba(18, 24, 40, 0.04);
  border: 1px solid rgba(15, 23, 42, 0.03);
  display:flex;
  align-items:center;
  gap:1rem;
}
.stat .stat-icon {
  width:56px;
  height:56px;
  border-radius:8px;
  display:grid;
  place-items:center;
  flex-shrink:0;
  font-size:1.25rem;
}
.stat .stat-body .label { font-size:.85rem; color:#6b7280; }
.stat .stat-body .value { font-size:1.75rem; font-weight:700; margin-top:.15rem; }

.stat.orders .stat-icon { background:linear-gradient(90deg,#e8fdf0,#bff3d6); color:#0b5a2b; }
.stat.aiorders .stat-icon { background:linear-gradient(90deg,#f6edff,#e7d3ff); color:#6b2ca8; }
.stat.cakes .stat-icon { background:linear-gradient(90deg,#ffecd2,#ffb88c); color:#7a2e0f; }

.btn-theme {
  background: linear-gradient(90deg,#7b2cbf,#9b59ff);
  color: #fff;
  border: none;
  box-shadow: 0 6px 18px rgba(123,44,191,0.08);
}

/* Inventory table card */
.card { border-radius: 12px; border: 1px solid rgba(0,0,0,0.04); box-shadow: 0 8px 24px rgba(18,24,40,0.03); padding: 1rem; background: #fff; }
.table-responsive { margin-top:.5rem; }
@media (max-width:575.98px){ .stats-grid { grid-template-columns: repeat(1,1fr); } .brand-card .logo{ width:56px; height:56px; } }
</style>

<div class="admin-wrap">
  <div class="dashboard-hero">
    <div class="brand-card">
      <div class="logo" aria-hidden="true">
        <!-- same logo as admin for visual consistency -->
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none">
          <path d="M3 13h18v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6z" fill="#FFD59E"/>
          <path d="M6 9a4 4 0 0 1 8 0" stroke="#FF7A18" stroke-width="1.2"/>
          <path d="M2 13c3-2 6-2 10-2s7 0 10 2" stroke="#FF7A18" stroke-width="1.2"/>
        </svg>
      </div>
      <div>
        <h2>Staff Dashboard</h2>
        <div class="muted">Quick tools for floor staff</div>
      </div>
    </div>

    <div class="actions">
      <!-- Staff-only actions (as requested) -->
      <a class="btn btn-outline-primary action-btn" href="orders.php">Orders</a>
      <a class="btn btn-outline-primary action-btn" href="reports.php">Reports</a> <a class="btn btn-outline-primary action-btn" href="assisted_order.php">Assisted Order</a> <a class="btn btn-outline-primary action-btn" href="pickup_schedule.php">Pickup Schedule</a>
      <a class="btn btn-theme action-btn" href="ai_orders.php">AI Orders</a>

      <!-- Helpful extras -->
      <a class="btn btn-outline-primary action-btn" href="add_quantity.php">Add Inventory</a>
      <a class="btn btn-outline-primary action-btn" href="item_sold.php">Item Sold</a>
      <a class="btn btn-outline-primary action-btn" href="addons.php">Addons</a>
      <form method="post" action="/AI-CAKE/logout.php"><?=csrf_field()?><button class="btn btn-danger action-btn">Logout</button></form>
    </div>
  </div>

  <!-- Stats grid -->
  <div class="stats-grid">
    <div class="stat cakes">
      <div class="stat-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M12 3v3" stroke="currentColor" stroke-width="1.2"/>
          <path d="M4 10h16v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6z" stroke="currentColor" stroke-width="1.2"/>
        </svg>
      </div>
      <div class="stat-body">
        <div class="label">Total Cakes</div>
        <div class="value"><?= esc($counts['cakes'] ?? 0) ?></div>
      </div>
    </div>

    <div class="stat orders">
      <div class="stat-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M3 3h18v4H3z" stroke="currentColor" stroke-width="1.2"/>
          <path d="M7 11h10v6H7z" stroke="currentColor" stroke-width="1.2"/>
        </svg>
      </div>
      <div class="stat-body">
        <div class="label">Orders (all)</div>
        <div class="value"><?= esc($counts['orders'] ?? 0) ?></div>
      </div>
    </div>

    <div class="stat aiorders">
      <div class="stat-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M3 12h18" stroke="currentColor" stroke-width="1.4"/>
          <path d="M12 3v6" stroke="currentColor" stroke-width="1.4"/>
          <circle cx="12" cy="16" r="3" stroke="currentColor" stroke-width="1.2"/>
        </svg>
      </div>
      <div class="stat-body">
        <div class="label">AI Orders</div>
        <div class="value"><?= esc($counts['ai_orders'] ?? 0) ?></div>
      </div>
    </div>
  </div>

  <!-- Inventory quick list (kept from your previous staff file) -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Inventory (quick)</h6>
          <small class="text-muted">Updated live</small>
        </div>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr><th>Code</th><th>Name</th><th>Qty</th><th>Available</th></tr>
            </thead>
            <tbody>
              <?php
              $cakes = $pdo->query('SELECT cake_id, name, quantity, available FROM cakes ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
              foreach ($cakes as $c):
              ?>
              <tr>
                <td><?= htmlspecialchars($c['cake_id']) ?></td>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= (int)$c['quantity'] ?></td>
                <td><?= $c['available'] ? 'Yes' : 'No' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
