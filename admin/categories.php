<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/upload.php';
require_admin_role();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') require_post_csrf();

// ---------- SETTINGS ----------
$uploadDir = __DIR__ . '/../uploads/categories/'; // server filesystem path (kept as before)
$maxFileSize = 2 * 1024 * 1024; // 2 MB
$allowedTypes = ['image/jpeg','image/png','image/gif'];

// ---------- HELPERS ----------
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function normalize_path($p){
    return str_replace('\\','/',$p);
}

function detect_upload_web_path($uploadDir){
    // Try to convert server path to a web accessible path by comparing to DOCUMENT_ROOT
    $uploadReal = @realpath($uploadDir);
    $docRoot   = @realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

    if ($uploadReal && $docRoot && strpos(normalize_path($uploadReal), normalize_path($docRoot)) === 0) {
        // remove docroot from upload path, ensure leading slash
        $web = substr(normalize_path($uploadReal), strlen(normalize_path($docRoot)));
        if ($web === '') $web = '/';
        if ($web[0] !== '/') $web = '/' . $web;
        // ensure trailing slash
        if (substr($web, -1) !== '/') $web .= '/';
        return $web;
    }

    // fallback: attempt relative path from script location (use parent folder)
    $fallback = '../uploads/categories/';
    return $fallback;
}

function handleUpload($file, $uploadDir, $allowedTypes, $maxFileSize){
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    return store_uploaded_image($file, $uploadDir);
}

// ---------- DETECT WEB PATH ----------
$uploadWebPath = detect_upload_web_path($uploadDir); // e.g. "/uploads/categories/" or "../uploads/categories/"

// ---------- POST HANDLING ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD
    if (isset($_POST['add'])) {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name !== '') {
            $picture = handleUpload($_FILES['picture'] ?? null, $uploadDir, $allowedTypes, $maxFileSize);
            $stmt = $pdo->prepare('INSERT INTO categories (name, picture) VALUES (?, ?)');
            $stmt->execute([$name, $picture]);
        }
        header('Location: categories.php'); exit;
    }

    // UPDATE
    if (isset($_POST['update'])) {
        $id = intval($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        if ($id && $name !== '') {
            // fetch existing picture
            $row = $pdo->prepare('SELECT picture FROM categories WHERE id = ?');
            $row->execute([$id]);
            $existing = $row->fetch();
            $existingPic = $existing['picture'] ?? null;

            $newPic = handleUpload($_FILES['picture'] ?? null, $uploadDir, $allowedTypes, $maxFileSize);

            if ($newPic !== null) {
                // update with new picture and delete old
                $stmt = $pdo->prepare('UPDATE categories SET name = ?, picture = ? WHERE id = ?');
                $stmt->execute([$name, $newPic, $id]);
                if ($existingPic) {
                    remove_managed_image($uploadDir, (string) $existingPic);
                }
            } else {
                // update name only
                $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
                $stmt->execute([$name, $id]);
            }
        }
        header('Location: categories.php'); exit;
    }

    // DELETE
    if (isset($_POST['delete'])) {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $usedQuery = $pdo->prepare('SELECT COUNT(*) FROM cakes WHERE category_id=?');
            $usedQuery->execute([$id]);
            if ((int) $usedQuery->fetchColumn() > 0) {
                $_SESSION['category_error'] = 'This category is assigned to one or more cakes and cannot be deleted.';
                header('Location: categories.php'); exit;
            }
            // get picture name
            $row = $pdo->prepare('SELECT picture FROM categories WHERE id = ?');
            $row->execute([$id]);
            $r = $row->fetch();
            $pic = $r['picture'] ?? null;

            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);

            if ($pic) remove_managed_image($uploadDir, (string) $pic);
        }
        header('Location: categories.php'); exit;
    }
}

// ---------- READ DATA ----------
$cats = $pdo->query('SELECT id, name, picture FROM categories ORDER BY name')->fetchAll();

// edit mode (GET ?edit=ID)
$editId = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editRow = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT id, name, picture FROM categories WHERE id = ?');
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch();
}

include __DIR__ . '/../inc/header.php';
$categoryError = (string) ($_SESSION['category_error'] ?? '');
unset($_SESSION['category_error']);
?>

<style>
/* Layout tweaks: make content wider on desktop, responsive on mobile */
.page-container {
  margin: 1.25rem auto;
  max-width: 1200px; /* wide on desktop */
  padding-left: 1rem;
  padding-right: 1rem;
}

/* make list scrollable on larger screens to keep form visible */
.list-scroll {
  max-height: 60vh;
  overflow-y: auto;
  padding-right: .25rem;
}

/* category thumbnail */
.cat-thumb {
  width:56px;
  height:56px;
  object-fit:cover;
  border-radius:8px;
  display:inline-block;
  flex: 0 0 56px;
}

/* fallback "No" box */
.cat-no {
  width:56px;
  height:56px;
  border-radius:8px;
  background:#f4f4f4;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#8a8a8a;
  font-size:.85rem;
}

/* compact file input on small screens */
.form-file-compact input[type="file"] {
  padding: .2rem;
}

/* responsive tweaks */
@media (max-width: 991.98px) {
  .page-container { max-width: 100%; }
  .list-scroll { max-height: none; }
  .cat-thumb, .cat-no { width:48px; height:48px; }
}
</style>

<div class="page-container">
  <?php if ($categoryError): ?><div class="alert alert-warning" role="alert"><?= esc($categoryError) ?></div><?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0">Categories</h4>
      <div class="text-muted small">Manage your cake categories</div>
    </div>
    <div>
      <a class="btn btn-outline-primary me-2" href="dashboard.php">Back</a>
    </div>
  </div>

  <div class="row g-3">
    <!-- LEFT: Form -->
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <?php if ($editRow): ?>
            <h5 class="card-title">Edit Category</h5>
            <form method="post" enctype="multipart/form-data" class="mb-2"><?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
              <div class="mb-3">
                <label class="form-label">Name</label>
                <input name="name" class="form-control" value="<?= esc($editRow['name']) ?>" required>
              </div>
              <div class="mb-3 form-file-compact">
                <label class="form-label">Picture <small class="text-muted">(leave empty to keep current)</small></label>
                <input type="file" name="picture" class="form-control">
                <div class="small text-muted mt-1">Optional, max 2MB (jpg/png/gif)</div>
              </div>

              <?php if ($editRow['picture']): ?>
                <div class="mb-3">
                  <div class="small text-muted mb-1">Current picture</div>
                  <img src="<?= esc($uploadWebPath . $editRow['picture']) ?>" alt="" style="max-width:220px; height:auto; display:block; border-radius:8px;">
                </div>
              <?php endif; ?>

              <div class="d-flex gap-2">
                <button name="update" class="btn btn-primary">Save</button>
                <a class="btn btn-secondary" href="categories.php">Cancel</a>
              </div>
            </form>
          <?php else: ?>
            <h5 class="card-title">Add New Category</h5>
            <form method="post" enctype="multipart/form-data" class="row gx-2 gy-2 align-items-end"><?= csrf_field() ?>
              <div class="col-12">
                <label class="form-label visually-hidden">Name</label>
                <input name="name" class="form-control" placeholder="New category" required>
              </div>

              <div class="col-12 col-sm-6 form-file-compact">
                <label class="form-label visually-hidden">Picture</label>
                <input type="file" name="picture" class="form-control">
                <div class="small text-muted mt-1">Optional, max 2MB (jpg/png/gif)</div>
              </div>

              <div class="col-12 col-sm-6 d-grid">
                <button name="add" class="btn btn-primary">Add</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- RIGHT: Categories list -->
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title mb-3">All Categories</h5>

          <?php if (empty($cats)): ?>
            <div class="text-muted">No categories yet.</div>
          <?php else: ?>
            <div class="list-scroll">
              <div class="list-group">
                <?php foreach ($cats as $c): ?>
                  <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center" style="gap:12px;">
                      <?php if ($c['picture']): ?>
                        <img src="<?= esc($uploadWebPath . $c['picture']) ?>" alt="" class="cat-thumb">
                      <?php else: ?>
                        <div class="cat-no">No</div>
                      <?php endif; ?>
                      <div>
                        <div class="fw-semibold"><?= esc($c['name']) ?></div>
                        <div class="small text-muted">ID: <?= (int)$c['id'] ?></div>
                      </div>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                      <a class="btn btn-sm btn-outline-secondary" href="categories.php?edit=<?= (int)$c['id'] ?>">Edit</a>

                      <form method="post" style="margin:0" onsubmit="return confirm('Delete this category? This will remove the image file too.');"><?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <button name="delete" class="btn btn-sm btn-outline-danger">Delete</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
