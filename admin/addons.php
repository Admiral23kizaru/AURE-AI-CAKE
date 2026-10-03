<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/upload.php';
require_admin_role();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') require_post_csrf();

/* ================= SETTINGS ================= */
$uploadDir = __DIR__ . '/../uploads/addons/';
$maxFileSize = 2 * 1024 * 1024;
$allowedTypes = ['image/jpeg','image/png','image/gif'];

/* ================= HELPERS ================= */
function esc($s){
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function detect_upload_web_path($dir){
    $real = @realpath($dir);
    $doc  = @realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($real && $doc && strpos($real,$doc) === 0) {
        return str_replace($doc,'',$real).'/';
    }
    return '../uploads/addons/';
}

function handleUpload($file,$dir,$types,$max){
    if(empty($file) || ($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    return store_uploaded_image($file,$dir);
}

/* ================= PATH ================= */
$uploadWebPath = detect_upload_web_path($uploadDir);

/* ================= POST ================= */
if($_SERVER['REQUEST_METHOD']==='POST'){

    /* ADD ADDON */
    if(isset($_POST['add'])){
        $name = mb_substr(trim((string)($_POST['name']??'')),0,150);
        $is_free = isset($_POST['is_free'])?1:0;
        $price = $is_free?0:(float)($_POST['price']??0);
        if(mb_strlen($name)<2)throw new RuntimeException('Add-on name must contain at least two characters.');
        if($price<0)throw new RuntimeException('Add-on price cannot be negative.');
        if($name!==''){
            $pic = handleUpload($_FILES['picture']??null,$uploadDir,$allowedTypes,$maxFileSize);
            $pdo->prepare(
                "INSERT INTO addons(name,is_free,price,picture) VALUES(?,?,?,?)"
            )->execute([$name,$is_free,$price,$pic]);
        }
        header('Location:addons.php'); exit;
    }

    /* UPDATE ADDON */
    if(isset($_POST['update'])){
        $id=(int)$_POST['id'];
        $name=mb_substr(trim((string)($_POST['name']??'')),0,150);
        $is_free=isset($_POST['is_free'])?1:0;
        $price=$is_free?0:(float)($_POST['price']??0);
        if($id<=0||mb_strlen($name)<2)throw new RuntimeException('Enter a valid add-on name.');
        if($price<0)throw new RuntimeException('Add-on price cannot be negative.');

        $oldQuery=$pdo->prepare('SELECT picture FROM addons WHERE id=?');
        $oldQuery->execute([$id]);
        $old=$oldQuery->fetchColumn();
        $new=handleUpload($_FILES['picture']??null,$uploadDir,$allowedTypes,$maxFileSize);

        if($new){
            $pdo->prepare(
                "UPDATE addons SET name=?,is_free=?,price=?,picture=? WHERE id=?"
            )->execute([$name,$is_free,$price,$new,$id]);
            remove_managed_image($uploadDir,(string)$old);
        }else{
            $pdo->prepare(
                "UPDATE addons SET name=?,is_free=?,price=? WHERE id=?"
            )->execute([$name,$is_free,$price,$id]);
        }
        header('Location:addons.php'); exit;
    }

    /* DELETE ADDON */
    if(isset($_POST['delete'])){
        $id=(int)$_POST['id'];
        if($id<=0)throw new RuntimeException('Add-on not found.');
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT picture FROM addons WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $pic=$q->fetchColumn();
            if($pic===false)throw new RuntimeException('Add-on not found.');

            $used=false;
            $q=$pdo->prepare("SELECT 1 FROM order_reservations WHERE resource_type='addon' AND resource_id=? LIMIT 1");
            $q->execute([$id]);
            $used=(bool)$q->fetchColumn();
            if(!$used){
                $q=$pdo->prepare('SELECT 1 FROM addon_inventory_adjustments WHERE addon_id=? LIMIT 1');
                $q->execute([$id]);
                $used=(bool)$q->fetchColumn();
            }

            if($used){
                // Preserve every stock adjustment and order reference. Historical add-ons are archived.
                $pdo->prepare('UPDATE addons SET is_active=0 WHERE id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM cakes_addons WHERE addon_id=?')->execute([$id]);
            }else{
                // A never-used zero-history add-on may be permanently removed.
                $pdo->prepare('DELETE FROM cakes_addons WHERE addon_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM inventory_addons WHERE addon_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM addons WHERE id=?')->execute([$id]);
            }
            $pdo->commit();
            if(!$used)remove_managed_image($uploadDir,(string)$pic);
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        header('Location:addons.php'); exit;
    }

    /* ADD INVENTORY */
    if(isset($_POST['add_inventory'])){
        $addon_id = (int)$_POST['addon_id'];
        $qty = (int)$_POST['quantity'];$reason=trim((string)($_POST['reason']??''));
        if(!$addon_id||$qty===0||mb_strlen($reason)<3)throw new RuntimeException('Choose an add-on, enter a non-zero correction, and provide a reason.');
        $pdo->beginTransaction();$q=$pdo->prepare('SELECT quantity FROM addons WHERE id=? FOR UPDATE');$q->execute([$addon_id]);$old=$q->fetchColumn();if($old===false)throw new RuntimeException('Add-on not found.');$new=(int)$old+$qty;if($new<0)throw new RuntimeException('Correction cannot reduce stock below zero.');$pdo->prepare('UPDATE addons SET quantity=? WHERE id=?')->execute([$new,$addon_id]);$pdo->prepare('INSERT INTO addon_inventory_adjustments(addon_id,quantity_change,resulting_stock,reason,actor_user_id) VALUES(?,?,?,?,?)')->execute([$addon_id,$qty,$new,$reason,user_id()]);$pdo->commit();
        header('Location:addons.php'); exit;
    }

}

/* ================= DATA ================= */
$addons = $pdo->query("SELECT * FROM addons ORDER BY name")->fetchAll();

$inventory = $pdo->query("
    SELECT ia.*, a.name,u.name actor_name
    FROM addon_inventory_adjustments ia
    JOIN addons a ON a.id = ia.addon_id
    LEFT JOIN users u ON u.id=ia.actor_user_id
    ORDER BY ia.created_at DESC
")->fetchAll();

$editId=(int)($_GET['edit']??0);
$editRow=null;
if($editId>0){$editQuery=$pdo->prepare('SELECT * FROM addons WHERE id=?');$editQuery->execute([$editId]);$editRow=$editQuery->fetch();}

include __DIR__.'/../inc/header.php';
?>

<style>
.thumb{width:48px;height:48px;object-fit:cover;border-radius:6px;}
</style>

<div class="container my-4">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Add Ons</h4>
        <div class="text-muted small">Add On Add & Add Stock Overview</div>
    </div>
    <a href="dashboard.php" class="btn btn-outline-primary">
        ← Back
    </a>
</div>

<!-- ================= ADDON FORM (UNCHANGED) ================= -->
<div class="card shadow-sm mb-4">
<div class="card-body">
<h5 class="mb-3"><?= $editRow?'Edit Addon':'Add New Addon' ?></h5>

<form method="post" enctype="multipart/form-data" class="row g-2"><?= csrf_field() ?>
<?php if($editRow): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>

<div class="col-12">
<input name="name" class="form-control" placeholder="Addon name"
value="<?= esc($editRow['name']??'') ?>" required>
</div>

<div class="col-md-6">
<input name="price" type="number" step="0.01" class="form-control"
value="<?= number_format($editRow['price']??0,2,'.','') ?>"
<?= ($editRow['is_free']??false)?'disabled':'' ?>>
</div>

<div class="col-md-6 d-flex align-items-center">
<div class="form-check">
<input class="form-check-input" type="checkbox" name="is_free"
<?= ($editRow['is_free']??false)?'checked':'' ?>
onchange="this.form.price.disabled=this.checked">
<label class="form-check-label">Free</label>
</div>
</div>

<div class="col-12 col-md-6">
<input type="file" name="picture" class="form-control">
</div>

<div class="col-12 col-md-6 d-grid">
<button class="btn btn-primary" name="<?= $editRow?'update':'add' ?>">
<?= $editRow?'Save Changes':'Add Addon' ?>
</button>
</div>
</form>
</div>
</div>

<!-- ================= ADDONS TABLE (UNCHANGED) ================= -->
<div class="card shadow-sm">
<div class="card-body">
<h5 class="mb-3">All Addons</h5>

<div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
<thead class="table-light">
<tr>
<th>Image</th>
<th>Name</th>
<th>Price</th>
<th>Stock</th>
<th>ID</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($addons as $a): ?>
<tr>
<td><?= $a['picture']?'<img src="'.esc($uploadWebPath.$a['picture']).'" class="thumb">':'No Image' ?></td>
<td><?= esc($a['name']) ?></td>
<td><?= $a['is_free']?'Free':'₱'.number_format($a['price'],2) ?></td>
<td><?= (int)$a['quantity'] ?></td>
<td><?= $a['id'] ?></td>
<td class="text-nowrap">
<a href="?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
<form method="post" class="d-inline" onsubmit="return confirm('Delete this addon?');"><?= csrf_field() ?>
<input type="hidden" name="id" value="<?= $a['id'] ?>">
<button name="delete" class="btn btn-sm btn-outline-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</div>
</div>

<!-- ================= INVENTORY PANEL (NEW) ================= -->
<div class="card shadow-sm mb-4">
<div class="card-body">
<h5>Add Addon Quantity</h5>

<form method="post" class="row g-2"><?= csrf_field() ?>
<div class="col-md-6">
<select name="addon_id" class="form-select" required>
<option value="">Select Addon</option>
<?php foreach($addons as $a): ?>
<option value="<?= $a['id'] ?>">
<?= esc($a['name']) ?> (Stock: <?= (int)$a['quantity'] ?>)
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<input type="number" name="quantity" class="form-control" placeholder="Quantity" required>
</div>

<div class="col-md-10"><input name="reason" maxlength="255" class="form-control" placeholder="Reason for this positive or negative correction" required></div>

<div class="col-md-2 d-grid">
<button class="btn btn-success" name="add_inventory">Add</button>
</div>
</form>
</div>
</div>

<!-- ================= INVENTORY TABLE (NEW) ================= -->
<div class="card shadow-sm mb-4">
<div class="card-body">
<h5>Addon Quantity History</h5>

<div class="table-responsive">
<table class="table table-bordered table-hover">
<thead class="table-light">
<tr>
<th>Addon</th>
<th>Change</th><th>Resulting stock</th><th>Reason / actor</th>
<th>Date</th>
</tr>
</thead>
<tbody>
<?php foreach($inventory as $i): ?>
<tr>
<td><?= esc($i['name']) ?></td>
<td><?= (int)$i['quantity_change']>0?'+':'' ?><?= (int)$i['quantity_change'] ?></td><td><?= (int)$i['resulting_stock'] ?></td><td><?=esc($i['reason'])?><?=!empty($i['actor_name'])?' - '.esc($i['actor_name']):''?></td>
<td><?= date('Y-m-d H:i', strtotime($i['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</div>
</div>



</div>

<?php include __DIR__.'/../inc/footer.php'; ?>
