<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin_role();

// fetch counts (cakes, categories, orders, addons, stores, ai_orders, staffs)
$counts = $pdo->query('SELECT 
    (SELECT COUNT(*) FROM cakes) as cakes, 
    (SELECT COUNT(*) FROM categories) as cats, 
    (SELECT COUNT(*) FROM order_headers WHERE order_type = "normal") as orders,
    (SELECT COUNT(*) FROM addons) as addons,
    (SELECT COUNT(*) FROM stores WHERE is_active = 1) as stores,
    (SELECT COUNT(*) FROM order_headers WHERE order_type = "ai") as ai_orders,
    (SELECT COUNT(*) FROM users WHERE role = "staff" AND is_active = 1) as staffs
')->fetch();


// fetch quick inventory list (for inventory card)
$cakes = $pdo->query('SELECT cake_id, name, quantity, available FROM cakes ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

include __DIR__ . '/../inc/header.php';
?>

<head>
<link rel="icon" type="image/png" href="/uploads/logo/logotab.png">
<link rel="shortcut icon" type="image/png" href="/uploads/logo/logotab.png">


</head>

<style>
/* Dashboard modern styling */
.admin-wrap {
  max-width: 1200px;
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
  width:72px;
  height:72px;
  border-radius:12px;
  display:grid;
  place-items:center;
  background:linear-gradient(135deg,#fff 0%, #f7f7f7 100%);
  box-shadow: 0 6px 18px rgba(20,20,40,0.06);
  border:1px solid rgba(0,0,0,0.03);
}

.brand-card h2 {
  margin:0;
  font-size:1.25rem;
  letter-spacing: -0.2px;
}

.brand-card .muted {
  color:#6b7280;
  font-size:.9rem;
}

.actions {
  display:flex;
  gap:.5rem;
  flex-wrap:wrap;
}

.action-btn {
  min-width:140px;
}

/* Stats grid — responsive to accommodate an extra card */
.stats-grid {
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap:1rem;
  margin-top:1rem;
}

@media (max-width: 991.98px){
  .brand-card .logo { width:56px; height:56px; }
}
@media (max-width: 575.98px){
  .stats-grid { grid-template-columns: repeat(1, 1fr); }
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
.stat .stat-body .value { font-size:1.75rem; font-weight:700; margin-top:0.15rem; }

.stat.cakes  .stat-icon { background:linear-gradient(90deg,#ffecd2,#ffb88c); color:#7a2e0f; }
.stat.cats   .stat-icon { background:linear-gradient(90deg,#e6f7ff,#c3e6ff); color:#0b3a56; }
.stat.orders .stat-icon { background:linear-gradient(90deg,#e8fdf0,#bff3d6); color:#0b5a2b; }
.stat.addons .stat-icon { background:linear-gradient(90deg,#f3e6ff,#e6c3ff); color:#3a0756; }
.stat.stores .stat-icon { background:linear-gradient(90deg,#f0f7ff,#d1e9ff); color:#0b4a76; }

/* AI Orders stat (purple) */
.stat.aiorders .stat-icon { background:linear-gradient(90deg,#f6edff,#e7d3ff); color:#6b2ca8; }

/* Staffs stat (teal) */
.stat.staffs .stat-icon { background:linear-gradient(90deg,#e8f9f6,#c9f0ea); color:#066a5e; }

/* Button theme for actions */
.btn-theme {
  background: linear-gradient(90deg,#7b2cbf,#9b59ff);
  color: #fff;
  border: none;
  box-shadow: 0 6px 18px rgba(123,44,191,0.08);
}

/* Inventory card styling (keeps consistent look) */
.inventory-card {
  margin-top: 1.25rem;
  border-radius: 12px;
  padding: 1rem;
  background: #fff;
  border: 1px solid rgba(15,23,42,0.03);
  box-shadow: 0 8px 24px rgba(18,24,40,0.03);
}
.table-sm th, .table-sm td { vertical-align: middle; }
@media (max-width:575.98px){ .stats-grid { grid-template-columns: repeat(1,1fr); } .brand-card .logo{ width:56px; height:56px; } }
</style>

<div class="admin-wrap">

  <div class="dashboard-hero">
    <div class="brand-card">
      <div class="logo">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none">
          <path d="M3 13h18v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6z" fill="#FFD59E"/>
          <path d="M6 9a4 4 0 0 1 8 0" stroke="#FF7A18" stroke-width="1.2"/>
          <path d="M2 13c3-2 6-2 10-2s7 0 10 2" stroke="#FF7A18" stroke-width="1.2"/>
        </svg>
      </div>
      <div>
        <h2>Admin Dashboard</h2>
        <div class="muted">Overview of your cake business</div>
      </div>
    </div>

    <div class="actions">
      <a class="btn btn-outline-primary action-btn" href="cakes.php">Cakes</a>
      <a class="btn btn-outline-primary action-btn" href="categories.php">Categories</a>
      <a class="btn btn-outline-primary action-btn" href="addons.php">Addons</a>
      <a class="btn btn-outline-primary action-btn" href="orders.php">Orders</a>
      <a class="btn btn-outline-primary action-btn" href="reports.php">Reports</a> <a class="btn btn-outline-primary action-btn" href="capacity.php">Capacity</a> <a class="btn btn-outline-primary action-btn" href="options.php">Cake Options</a> <a class="btn btn-outline-primary action-btn" href="accounts.php">Accounts</a> <a class="btn btn-outline-primary action-btn" href="readiness.php">Defense Readiness</a> <a class="btn btn-outline-primary action-btn" href="../staff/assisted_order.php">Assisted Order</a> <a class="btn btn-outline-primary action-btn" href="../staff/pickup_schedule.php">Pickup Schedule</a>

      <!-- ADDED: Stores button -->
      <a class="btn btn-outline-primary action-btn" href="stores.php">Stores</a>

      <!-- ADDED: AI Orders button (links to admin chat/conversations) -->
      <a class="btn action-btn btn-theme" href="ai_orders.php">AI Orders</a>

      <!-- ADDED: Staffs button -->
      <a class="btn btn-outline-primary action-btn" href="staffs.php">Staffs</a>

      <!-- ADDED: Add Inventory button -->
      <a class="btn btn-outline-primary action-btn" href="add_quantity.php">Add Inventory</a>
      
      <!-- ADDED: Order History button -->
      <a class="btn btn-outline-primary action-btn" href="order_history.php">Order History</a>
      
      <!-- ADDED: Item Sold button -->
      <a class="btn btn-outline-primary action-btn" href="item_sold.php">Item Sold</a>

      <form method="post" action="/AI-CAKE/logout.php"><?=csrf_field()?><button class="btn btn-danger action-btn">Logout</button></form>
    </div>
  </div>

  <!-- Stats Only -->
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
        <div class="value"><?= esc($counts['cakes']) ?></div>
      </div>
    </div>

    <div class="stat cats">
      <div class="stat-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M3 7h4l2 2h10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" stroke="currentColor" stroke-width="1.2"/>
        </svg>
      </div>
      <div class="stat-body">
        <div class="label">Categories</div>
        <div class="value"><?= esc($counts['cats']) ?></div>
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
        <div class="label">Orders</div>
        <div class="value"><?= esc($counts['orders']) ?></div>
      </div>
    </div>

    <div class="stat addons">
      <div class="stat-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.6"/>
        </svg>
      </div>
      <div class="stat-body">
        <div class="label">Addons</div>
        <div class="value"><?= esc($counts['addons']) ?></div>
      </div>
    </div>

    <!-- ADDED: AI Orders stat card -->
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
        <div class="value"><?= esc($counts['ai_orders']) ?></div>
      </div>
    </div>

    <!-- ADDED: Stores stat card -->
    <div class="stat stores">
      <div class="stat-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M3 10h18v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7z" stroke="currentColor" stroke-width="1.2"/>
          <path d="M7 10V6a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.2"/>
        </svg>
      </div>
      <div class="stat-body">
        <div class="label">Pickup Shop</div>
        <div class="value"><?= esc($counts['stores']) ?></div>
      </div>
    </div>

    <!-- ADDED: STAFFS stat card -->
    <div class="stat staffs">
      <div class="stat-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M16 11c1.657 0 3-1.567 3-3.5S17.657 4 16 4s-3 1.567-3 3.5S14.343 11 16 11zM8 11c1.657 0 3-1.567 3-3.5S9.657 4 8 4 5 5.567 5 7.5 6.343 11 8 11z" stroke="currentColor" stroke-width="1.1"/>
          <path d="M2 20c0-2.5 3.5-4 6-4s6 1.5 6 4" stroke="currentColor" stroke-width="1.1"/>
        </svg>
      </div>
      <div class="stat-body">
        <div class="label">Staffs</div>
        <div class="value"><?= esc($counts['staffs']) ?></div>
      </div>
    </div>

  </div>

  <!-- Inventory quick list (same as staff) -->
  <div class="inventory-card">
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
          <?php foreach ($cakes as $c): ?>
            <tr>
              <td><?= esc($c['cake_id']) ?></td>
              <td><?= esc($c['name']) ?></td>
              <td><?= (int)$c['quantity'] ?></td>
              <td><?= !empty($c['available']) ? 'Yes' : 'No' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($cakes)): ?>
            <tr><td colspan="4" class="text-muted text-center">No cakes found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
