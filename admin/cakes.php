<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/upload.php';
require_admin_role();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') require_post_csrf();

$action = $_GET['action'] ?? '';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_cake'])){
    $id=(int)$_POST['delete_cake'];$pdo->beginTransaction();
    try{$q=$pdo->prepare('SELECT * FROM cakes WHERE id=? FOR UPDATE');$q->execute([$id]);$cake=$q->fetch();if(!$cake)throw new RuntimeException('Cake not found.');$used=false;
        foreach(['order_items','reviews','favorites','inventory_adjustments'] as $table){$q=$pdo->prepare("SELECT 1 FROM {$table} WHERE cake_id=? LIMIT 1");$q->execute([$id]);if($q->fetchColumn()){$used=true;break;}}
        if(!$used){$q=$pdo->prepare("SELECT 1 FROM order_reservations WHERE resource_type='cake' AND resource_id=? LIMIT 1");$q->execute([$id]);$used=(bool)$q->fetchColumn();}
        if($used)$pdo->prepare('UPDATE cakes SET available=0,is_new=0,best_sellers=0 WHERE id=?')->execute([$id]);
        else{$pdo->prepare('DELETE FROM cakes_addons WHERE cake_id=?')->execute([$id]);$pdo->prepare('DELETE FROM cake_option_compatibility WHERE cake_id=?')->execute([$id]);$pdo->prepare('DELETE FROM cakes WHERE id=?')->execute([$id]);}
        $pdo->commit();if(!$used)remove_managed_image(__DIR__.'/../uploads',(string)$cake['picture']);header('Location: cakes.php');exit;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

// Only active add-ons can be assigned to a product.
$allAddons = $pdo->query('SELECT id, name, is_free, price FROM addons WHERE is_active=1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Read inputs
    $name = trim($_POST['name']??'');
    $category_id = ($_POST['category_id']??null) ?: null;
    $price = floatval($_POST['price']??0);
    $available = isset($_POST['available']) ? 1 : 0;
    $quantity = intval($_POST['quantity']??0);
    $description = trim($_POST['description'] ?? '');
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    $is_best = isset($_POST['best_sellers']) ? 1 : 0;
    $id = intval($_POST['id']??0);
    $is_rec = 0;
    if(mb_strlen($name)<2 || mb_strlen($name)>150) throw new RuntimeException('Cake name must be between 2 and 150 characters.');
    if($price<=0) throw new RuntimeException('Cake price must be greater than zero.');
    if($quantity<0) throw new RuntimeException('Cake stock cannot be negative.');
    if(mb_strlen($description)>2000) throw new RuntimeException('Cake description is too long.');
    if($category_id!==null){$category_id=(int)$category_id;$categoryCheck=$pdo->prepare('SELECT 1 FROM categories WHERE id=?');$categoryCheck->execute([$category_id]);if(!$categoryCheck->fetchColumn())throw new RuntimeException('Choose a valid category.');}

    // selected addons from form: array of addon ids (strings) or may be missing
    $selectedAddons = array();
    if(!empty($_POST['addons']) && is_array($_POST['addons'])){
        // sanitize to integers and unique
        $selectedAddons = array_values(array_filter(array_unique(array_map('intval', $_POST['addons'])),static fn(int $value):bool=>$value>0));
    }
    if($selectedAddons){
        $marks=implode(',',array_fill(0,count($selectedAddons),'?'));
        $addonCheck=$pdo->prepare("SELECT COUNT(*) FROM addons WHERE is_active=1 AND id IN ({$marks})");
        $addonCheck->execute($selectedAddons);
        if((int)$addonCheck->fetchColumn()!==count($selectedAddons))throw new RuntimeException('One or more selected add-ons are unavailable.');
    }

    // Handle picture upload
    $picture = '';
    if(isset($_FILES['picture']) && ($_FILES['picture']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$picture=store_uploaded_image($_FILES['picture'],__DIR__.'/../uploads');

    if($id > 0){
        // UPDATE existing cake (do not change cake_id)
        try {
            $pdo->beginTransaction();
            $old = $pdo->prepare('SELECT * FROM cakes WHERE id=? FOR UPDATE');
            $old->execute([$id]);
            $orow = $old->fetch();
            if(!$orow) throw new RuntimeException('Cake not found.');
            $is_rec=(int)$orow['is_recommended'];

            if($picture){
                $stmt = $pdo->prepare('UPDATE cakes SET name=?, category_id=?, picture=?, price=?, available=?, quantity=?, description=?, is_new=?, is_recommended=?, best_sellers=? WHERE id=?');
                $stmt->execute([$name, $category_id, $picture, $price, $available, $quantity, $description, $is_new, $is_rec, $is_best, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE cakes SET name=?, category_id=?, price=?, available=?, quantity=?, description=?, is_new=?, is_recommended=?, best_sellers=? WHERE id=?');
                $stmt->execute([$name, $category_id, $price, $available, $quantity, $description, $is_new, $is_rec, $is_best, $id]);
            }

            $stockChange=$quantity-(int)$orow['quantity'];
            if($stockChange!==0){
                $pdo->prepare("INSERT INTO inventory_adjustments(cake_id,qty_change,adjustment_type,resulting_stock,note,actor_user_id) VALUES(?,?,'correction',?,'Stock changed in product maintenance',?)")
                    ->execute([$id,$stockChange,$quantity,user_id()]);
            }

            // update addons relations: delete existing then insert new (if any)
            $pdo->prepare('DELETE FROM cakes_addons WHERE cake_id = ?')->execute([$id]);
            if(!empty($selectedAddons)){
                $ins = $pdo->prepare('INSERT INTO cakes_addons (cake_id, addon_id) VALUES (?, ?)');
                foreach($selectedAddons as $aid){
                    $ins->execute([$id, $aid]);
                }
            }

            $pdo->commit();
            if($picture && !empty($orow['picture'])) remove_managed_image(__DIR__.'/../uploads',(string)$orow['picture']);
        } catch (Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            if($picture)remove_managed_image(__DIR__.'/../uploads',$picture);
            throw $e;
        }
    } else {
        // INSERT new cake
        try {
            $pdo->beginTransaction();
            $next = (int)$pdo->query('SELECT COALESCE(MAX(id),0) + 1 AS next FROM cakes')->fetchColumn();
            if(!$next) $next = 1;
            $cake_id = 'CK' . str_pad($next, 5, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare('INSERT INTO cakes (cake_id, name, category_id, picture, price, available, quantity, description, is_new, is_recommended, best_sellers, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([$cake_id, $name, $category_id, $picture, $price, $available, $quantity, $description, $is_new, $is_rec, $is_best]);

            $newCakeId = (int)$pdo->lastInsertId();
            if($quantity>0){
                $pdo->prepare("INSERT INTO inventory_adjustments(cake_id,qty_change,adjustment_type,resulting_stock,note,actor_user_id) VALUES(?,?,'opening',?,'Opening stock from product creation',?)")
                    ->execute([$newCakeId,$quantity,$quantity,user_id()]);
            }

            // insert selected addons
            if(!empty($selectedAddons)){
                $ins = $pdo->prepare('INSERT INTO cakes_addons (cake_id, addon_id) VALUES (?, ?)');
                foreach($selectedAddons as $aid){
                    $ins->execute([$newCakeId, $aid]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            if($picture)remove_managed_image(__DIR__.'/../uploads',$picture);
            throw $e;
        }
    }

    header('Location: cakes.php'); exit;
}

// fetch for listing/edit — include category name and joined addons list for display
$cakes = $pdo->query('
    SELECT 
      cakes.*,
      categories.name as category,
      (SELECT GROUP_CONCAT(a.name SEPARATOR ", ") FROM cakes_addons ca JOIN addons a ON a.id = ca.addon_id WHERE ca.cake_id = cakes.id) as addons_list,
      (SELECT COUNT(*) FROM cakes_addons ca WHERE ca.cake_id = cakes.id) as addons_count
    FROM cakes
    LEFT JOIN categories ON cakes.category_id=categories.id
    ORDER BY cakes.id DESC
')->fetchAll(PDO::FETCH_ASSOC);

$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$edit = null;
$existingSelectedAddons = [];
if(isset($_GET['id']) && ($action === 'edit')){
    $stmt = $pdo->prepare('SELECT * FROM cakes WHERE id=?');
    $stmt->execute([intval($_GET['id'])]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);

    if($edit){
        // fetch existing addon relations
        $sa = $pdo->prepare('SELECT addon_id FROM cakes_addons WHERE cake_id = ?');
        $sa->execute([$edit['id']]);
        $existingSelectedAddons = $sa->fetchAll(PDO::FETCH_COLUMN, 0);
        // ensure ints
        $existingSelectedAddons = array_map('intval', $existingSelectedAddons);
    }
}

include __DIR__ . '/../inc/header.php';
?>

<style>
/* Page container and layout */
.page-container {
  max-width: 1100px;
  margin: 1rem auto;
  padding: 0 0.75rem;
}

/* Form top card */
.form-card {
  border-radius: 12px;
  padding: 0.9rem;
  box-shadow: 0 8px 20px rgba(15,23,42,0.04);
  border: 1px solid rgba(0,0,0,0.04);
  background: #fff;
  margin-bottom: 1rem;
}

/* Bigger touch targets on mobile */
@media (max-width: 767.98px) {
  .form-card { padding: .8rem; }
  .form-card .form-control, .form-card .form-select, .form-card textarea {
    padding: .9rem;
    font-size: 1rem;
  }
  .form-card .form-check-input { width: 1.25rem; height: 1.25rem; }
  .btn-mobile-save { font-size: 1.05rem; padding: .85rem 1rem; }
}

/* Addons box */
.addons-box {
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid #eee;
  padding: .5rem;
  border-radius: 6px;
  background: #fafafa;
}

/* Table styling (desktop) */
.table-wrap {
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 6px 18px rgba(15,23,42,0.04);
  border: 1px solid rgba(0,0,0,0.04);
  background: #fff;
}

/* Mobile card list: show only on small screens */
.mobile-card-list { display: none; }
@media (max-width: 767.98px) {
  .desktop-table { display: none; }
  .mobile-card-list { display: block; }
  .cake-card {
    border-radius: 10px;
    box-shadow: 0 6px 16px rgba(12,16,30,0.04);
    padding: .75rem;
    margin-bottom: .75rem;
    background: #fff;
    border: 1px solid rgba(0,0,0,0.03);
  }
  .cake-card .meta { font-size: .9rem; color: #6b7280; }
  .cake-card .actions .btn { font-size: .85rem; padding: .35rem .5rem; }
}

/* Sticky mobile action bar (save / cancel) */
.mobile-action-bar {
  display:none;
}
@media (max-width: 767.98px) {
  .mobile-action-bar {
    position: fixed;
    right: 0;
    left: 0;
    bottom: 0;
    z-index: 1100;
    padding: .5rem;
    background: rgba(255,255,255,0.97);
    border-top: 1px solid rgba(0,0,0,0.06);
    display:flex;
    gap:.5rem;
    justify-content: space-between;
  }
  body { padding-bottom: 72px; } /* avoid content hidden behind bar */
}

/* small image preview */
.img-preview {
  max-height: 80px;
  border-radius: 6px;
  margin-top: .5rem;
  display:block;
}

.cakes-table td,
.cakes-table th {
  vertical-align: middle;
}

@media (max-width: 768px) {
  .cakes-table {
    font-size: 0.9rem;
  }

  .cakes-table th,
  .cakes-table td {
    white-space: nowrap;
  }

  .cakes-table td.text-wrap {
    white-space: normal;
  }
}

</style>

<div class="page-container">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">Cakes</h4>
    <div class="d-none d-sm-block">
      <a class="btn btn-outline-primary" href="dashboard.php">Dashboard</a>
      <a class="btn btn-outline-primary" href="categories.php">Categories</a>
      <a class="btn btn-outline-primary" href="addons.php">Addons</a>
    </div>
  </div>

  <!-- WIDE FORM -->
  <div class="form-card" id="cake-editor" tabindex="-1">
    <form id="cakeForm" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
      <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit['id']??''); ?>">
      <div class="row g-2">
        <?php if($edit): ?>
          <div class="col-12">
            <label class="form-label small mb-1">Cake ID</label>
            <input class="form-control" value="<?php echo htmlspecialchars($edit['cake_id'] ?? ''); ?>" readonly>
          </div>
        <?php endif; ?>

        <div class="col-12 col-md-8">
          <label class="form-label small mb-1">Name</label>
          <input name="name" class="form-control" minlength="2" maxlength="150" required value="<?php echo htmlspecialchars($edit['name']??''); ?>">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label small mb-1">Category</label>
          <select name="category_id" class="form-select">
            <option value="">-- none --</option>
            <?php foreach($cats as $c): ?>
              <option value="<?php echo $c['id']; ?>" <?php if(isset($edit['category_id']) && $edit['category_id']==$c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label small mb-1">Price</label>
          <input name="price" class="form-control" type="number" min="0.01" step="0.01" required value="<?php echo htmlspecialchars($edit['price']??''); ?>">
        </div>

        <div class="col-12">
          <label class="form-label small mb-1">Description</label>
          <textarea name="description" class="form-control" rows="3" maxlength="2000"><?php echo htmlspecialchars($edit['description'] ?? ''); ?></textarea>
        </div>

        <div class="col-6 col-md-4">
          <label class="form-label small mb-1">Picture (optional)</label>
          <input id="pictureInput" type="file" name="picture" class="form-control" accept="image/jpeg,image/png,image/webp">
          <?php if($edit && $edit['picture']): ?>
            <img id="existingPreview" src="../uploads/<?php echo htmlspecialchars($edit['picture']); ?>" class="img-preview" alt="current">
          <?php else: ?>
            <img id="existingPreview" src="" class="img-preview" style="display:none" alt="preview">
          <?php endif; ?>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label small mb-1">Quantity</label>
          <input name="quantity" class="form-control" type="number" min="0" step="1" value="<?php echo htmlspecialchars($edit['quantity']??0); ?>">
          <div class="form-text">Changes are recorded in inventory history.</div>
        </div>

        <div class="col-6 col-md-3 d-flex align-items-center">
          <div class="form-check mt-2">
            <input type="checkbox" name="available" class="form-check-input" id="available" <?php if(isset($edit['available']) && $edit['available']) echo 'checked'; ?>>
            <label for="available" class="form-check-label">Available</label>
          </div>
        </div>

        <div class="col-6 col-md-3 d-flex gap-2 flex-wrap">
          <div class="form-check mt-2">
            <input type="checkbox" name="is_new" class="form-check-input" id="is_new" <?php if(isset($edit['is_new']) && $edit['is_new']) echo 'checked'; ?>>
            <label for="is_new" class="form-check-label">New</label>
          </div>

          <div class="form-check mt-2">
            <input type="checkbox" name="best_sellers" class="form-check-input" id="is_best" <?php if(isset($edit['best_sellers']) && $edit['best_sellers']) echo 'checked'; ?>>
            <label for="is_best" class="form-check-label">Best Seller</label>
          </div>
        </div>

        <!-- Addons: accordion on mobile, visible box on desktop -->
        <div class="col-12 col-md-6">
          <label class="form-label small mb-1">Addons (optional)</label>

          <!-- Mobile: collapse -->
          <div class="d-block d-md-none mb-2">
            <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#addonsCollapse" role="button" aria-expanded="false" aria-controls="addonsCollapse">
              Select Addons
            </a>
            <div class="collapse mt-2" id="addonsCollapse">
              <div class="addons-box">
                <?php if(empty($allAddons)): ?>
                  <div class="text-muted">No addons available. Create addons first.</div>
                <?php else: ?>
                  <?php foreach($allAddons as $ad):
                    $checked = in_array((int)$ad['id'], $existingSelectedAddons, true) ? 'checked' : '';
                  ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="addons[]" id="addon_m_<?php echo (int)$ad['id']; ?>" value="<?php echo (int)$ad['id']; ?>" <?php echo $checked; ?>>
                      <label class="form-check-label" for="addon_m_<?php echo (int)$ad['id']; ?>">
                        <?php echo htmlspecialchars($ad['name']); ?> <small class="text-muted">— <?php echo $ad['is_free'] ? 'Free' : '₱'.number_format($ad['price'],2); ?></small>
                      </label>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Desktop: always visible -->
          <div class="d-none d-md-block">
            <div class="addons-box">
              <?php if(empty($allAddons)): ?>
                <div class="text-muted">No addons available. Create addons first.</div>
              <?php else: ?>
                <?php foreach($allAddons as $ad):
                  $checked = in_array((int)$ad['id'], $existingSelectedAddons, true) ? 'checked' : '';
                ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="addons[]" id="addon_<?php echo (int)$ad['id']; ?>" value="<?php echo (int)$ad['id']; ?>" <?php echo $checked; ?>>
                    <label class="form-check-label" for="addon_<?php echo (int)$ad['id']; ?>">
                      <?php echo htmlspecialchars($ad['name']); ?> <small class="text-muted">— <?php echo $ad['is_free'] ? 'Free' : '₱'.number_format($ad['price'],2); ?></small>
                    </label>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

        </div>

        <!-- buttons for md+ -->
        <div class="col-12 d-md-flex justify-content-start gap-2 mt-2">
          <button class="btn btn-primary"><?php echo $edit ? 'Update' : 'Add'; ?></button>
          <?php if($edit): ?><a class="btn btn-outline-primary" href="cakes.php">Cancel</a><?php endif; ?>
        </div>
      </div>
    </form>
  </div>



 <!-- RESPONSIVE TABLE (ALL SCREENS) -->
    <div class="table-responsive mt-2">
      <table class="table table-striped table-hover align-middle mb-0 cakes-table">
        <thead class="table-light">
          <tr>
            <th class="d-none">ID</th>
            <th>Picture</th>
            <th>Cake ID</th>
            <th>Name</th>
            <th>Category</th>
            <th>Description</th>
            <th>Price</th>
            <th>Qty</th>
            <th>Available</th>
            <th>Addons</th>
            <th>New</th>
            <th>Recommended</th>
            <th>Best Seller</th>
            <th>Actions</th>
          </tr>
        </thead>
    
        <tbody>
          <?php foreach($cakes as $c): ?>
            <tr>
              <td class="d-none"><?php echo (int)$c['id']; ?></td>
    
              <!-- Picture -->
              <td style="width:90px;">
                <?php if($c['picture'] && file_exists(__DIR__.'/../uploads/'.$c['picture'])): ?>
                  <img src="../uploads/<?php echo htmlspecialchars($c['picture']); ?>"
                       class="img-fluid rounded"
                       style="max-height:50px;max-width:70px;">
                <?php else: ?>
                  <div class="bg-light text-muted d-flex align-items-center justify-content-center rounded"
                       style="height:50px;width:50px;">-</div>
                <?php endif; ?>
              </td>
    
              <td><?php echo htmlspecialchars($c['cake_id'] ?? ''); ?></td>
              <td class="fw-semibold"><?php echo htmlspecialchars($c['name']); ?></td>
              <td><?php echo htmlspecialchars($c['category'] ?? ''); ?></td>
    
              <!-- Description -->
              <td class="text-wrap" style="min-width:200px;max-width:260px;">
                <?php echo htmlspecialchars($c['description'] ?? ''); ?>
              </td>
    
              <td>₱<?php echo number_format($c['price'],2); ?></td>
              <td><?php echo (int)$c['quantity']; ?></td>
              <td><?php echo $c['available'] ? 'Yes' : 'No'; ?></td>
    
              <!-- Addons -->
              <td class="text-wrap" style="min-width:180px;">
                <?php echo !empty($c['addons_list']) ? htmlspecialchars($c['addons_list']) : '—'; ?>
              </td>
    
              <td><?php echo $c['is_new'] ? 'Yes' : 'No'; ?></td>
              <td><?php echo $c['is_recommended'] ? 'Yes' : 'No'; ?></td>
              <td><?php echo $c['best_sellers'] ? 'Yes' : 'No'; ?></td>
    
              <!-- Actions -->
              <td class="text-nowrap">
                <a class="btn btn-sm btn-outline-primary mb-1"
                   href="cakes.php?action=edit&id=<?php echo $c['id']; ?>#cake-editor">
                  Edit
                </a>
                <form method="post" class="d-inline" onsubmit="return confirm('Archive this cake, or permanently delete it only if it has never been used?')"><?=csrf_field()?><button class="btn btn-sm btn-outline-danger mb-1" name="delete_cake" value="<?=(int)$c['id']?>">Archive / delete</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>


</div>

<script>
// Image preview for picture input
document.addEventListener('DOMContentLoaded', function(){
  const picInput = document.getElementById('pictureInput');
  const preview = document.getElementById('existingPreview');

  if(picInput){
    picInput.addEventListener('change', function(e){
      const f = this.files && this.files[0];
      if(!f){ 
        if(preview) preview.style.display = '<?php echo ($edit && $edit['picture']) ? 'block' : 'none'; ?>';
        return;
      }
      const reader = new FileReader();
      reader.onload = function(ev){
        if(preview){
          preview.src = ev.target.result;
          preview.style.display = 'block';
        }
      };
      reader.readAsDataURL(f);
    });
  }

  // mobile save button triggers form submit
  const mobileSave = document.getElementById('mobileSaveBtn');
  if(mobileSave){
    mobileSave.addEventListener('click', function(){
      document.getElementById('cakeForm').submit();
    });
  }
  const mobileReset = document.getElementById('mobileResetBtn');
  if(mobileReset){
    mobileReset.addEventListener('click', function(){
      document.getElementById('cakeForm').reset();
      if(preview) preview.style.display = 'none';
    });
  }
});
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>
