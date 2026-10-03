<?php
declare(strict_types=1);

require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/page.php';
require_admin_role();

const CAKE_OPTION_TYPES = ['Flavor', 'Size', 'Design', 'Add-on'];
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    try {
        if (isset($_POST['create']) || isset($_POST['update'])) {
            $id = (int) ($_POST['id'] ?? 0);
            $type = (string) ($_POST['option_type'] ?? '');
            $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
            $price = round((float) ($_POST['price'] ?? 0), 2);
            $stockInput = trim((string) ($_POST['stock'] ?? ''));
            $stock = $stockInput === '' ? null : (int) $stockInput;
            if (!in_array($type, CAKE_OPTION_TYPES, true)) throw new InvalidArgumentException('Choose a valid option type.');
            if (mb_strlen($name) < 2) throw new InvalidArgumentException('Enter an option name.');
            if ($price < 0 || $price > 1000000) throw new InvalidArgumentException('Enter a valid non-negative price adjustment.');
            if ($stock !== null && $stock < 0) throw new InvalidArgumentException('Stock cannot be negative.');

            $duplicate = $pdo->prepare('SELECT 1 FROM cake_options WHERE option_type=? AND name=? AND id<>? LIMIT 1');
            $duplicate->execute([$type, $name, $id]);
            if ($duplicate->fetchColumn()) throw new RuntimeException('An option with that type and name already exists.');

            if (isset($_POST['create'])) {
                $pdo->prepare('INSERT INTO cake_options(option_type,name,price_adjustment,stock,is_active) VALUES(?,?,?,?,1)')
                    ->execute([$type, $name, $price, $stock]);
                $message = 'Cake option created.';
            } else {
                if ($id <= 0) throw new RuntimeException('Cake option not found.');
                $pdo->prepare('UPDATE cake_options SET option_type=?,name=?,price_adjustment=?,stock=? WHERE id=?')
                    ->execute([$type, $name, $price, $stock, $id]);
                $message = 'Cake option updated.';
            }
        } elseif (isset($_POST['assign'])) {
            $cakeId = (int) ($_POST['cake_id'] ?? 0);
            $optionId = (int) ($_POST['option_id'] ?? 0);
            $valid = $pdo->prepare('SELECT 1 FROM cakes c JOIN cake_options o ON o.id=? WHERE c.id=? AND c.available=1 AND o.is_active=1');
            $valid->execute([$optionId, $cakeId]);
            if (!$valid->fetchColumn()) throw new RuntimeException('Choose an active cake and option.');
            $pdo->prepare('INSERT INTO cake_option_compatibility(cake_id,option_id,is_required) VALUES(?,?,?) ON DUPLICATE KEY UPDATE is_required=VALUES(is_required)')
                ->execute([$cakeId, $optionId, isset($_POST['required']) ? 1 : 0]);
            $message = 'Product compatibility saved.';
        } elseif (isset($_POST['unassign'])) {
            $pdo->prepare('DELETE FROM cake_option_compatibility WHERE cake_id=? AND option_id=?')
                ->execute([(int) ($_POST['cake_id'] ?? 0), (int) ($_POST['option_id'] ?? 0)]);
            $message = 'Product compatibility removed.';
        } elseif (isset($_POST['toggle'])) {
            $pdo->prepare('UPDATE cake_options SET is_active=1-is_active WHERE id=?')->execute([(int) ($_POST['id'] ?? 0)]);
            $message = 'Option availability updated.';
        }
    } catch (Throwable $error) {
        $messageType = 'danger';
        $message = $error instanceof PDOException ? 'Unable to save the cake option.' : $error->getMessage();
    }
}

$options = $pdo->query('SELECT * FROM cake_options ORDER BY option_type,name')->fetchAll();
$cakes = $pdo->query('SELECT id,name,available FROM cakes ORDER BY name')->fetchAll();
$assignments = $pdo->query(
    'SELECT co.cake_id,co.option_id,co.is_required,c.name cake_name,o.option_type,o.name option_name
       FROM cake_option_compatibility co
       JOIN cakes c ON c.id=co.cake_id
       JOIN cake_options o ON o.id=co.option_id
      ORDER BY c.name,o.option_type,o.name'
)->fetchAll();

page_start('Cake options');
?>
<main class="container py-4">
  <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><div class="eyebrow">Administrator</div><h1 class="h3">Cake customization options</h1><p class="help-text mb-0">Manage Flavor, Size, Design, and Add-on choices and assign them explicitly to cakes.</p></div>
    <a href="dashboard.php" class="btn btn-outline-primary">Back to dashboard</a>
  </div>
  <?php if ($message): ?><div class="alert alert-<?= e($messageType) ?>" role="status"><?= e($message) ?></div><?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-4">
      <form method="post" class="app-card p-4">
        <?= csrf_field() ?><h2 class="h5">Add option</h2>
        <label class="form-label" for="create_type">Type</label><select id="create_type" name="option_type" class="form-select"><?php foreach (CAKE_OPTION_TYPES as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select>
        <label class="form-label mt-2" for="create_name">Name</label><input id="create_name" name="name" maxlength="120" class="form-control" required>
        <label class="form-label mt-2" for="create_price">Price adjustment</label><input id="create_price" type="number" min="0" max="1000000" step="0.01" name="price" value="0" class="form-control" required>
        <label class="form-label mt-2" for="create_stock">Stock</label><input id="create_stock" type="number" min="0" name="stock" class="form-control" aria-describedby="stock_help"><div id="stock_help" class="help-text">Leave blank when the option does not use inventory.</div>
        <button name="create" class="btn btn-purple mt-3">Create option</button>
      </form>
    </div>
    <div class="col-lg-8">
      <section class="app-card p-4">
        <h2 class="h5">Existing options</h2>
        <?php if (!$options): ?><p class="help-text">No options have been configured.</p><?php endif; ?>
        <?php foreach ($options as $option): ?>
          <form method="post" class="border-top py-3">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $option['id'] ?>">
            <div class="row g-2 align-items-end">
              <div class="col-md-2"><label class="form-label">Type</label><select name="option_type" class="form-select"><?php foreach (CAKE_OPTION_TYPES as $type): ?><option <?= $type === $option['option_type'] ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label">Name</label><input name="name" maxlength="120" value="<?= e($option['name']) ?>" class="form-control" required></div>
              <div class="col-md-2"><label class="form-label">Price</label><input type="number" min="0" max="1000000" step="0.01" name="price" value="<?= e($option['price_adjustment']) ?>" class="form-control" required></div>
              <div class="col-md-2"><label class="form-label">Stock</label><input type="number" min="0" name="stock" value="<?= $option['stock'] === null ? '' : (int) $option['stock'] ?>" class="form-control"></div>
              <div class="col-md-3 d-flex gap-2"><button name="update" class="btn btn-outline-primary">Save</button><button name="toggle" class="btn btn-outline-secondary"><?= $option['is_active'] ? 'Deactivate' : 'Activate' ?></button></div>
            </div>
          </form>
        <?php endforeach; ?>
      </section>
    </div>
  </div>

  <section class="app-card p-4 mt-4">
    <h2 class="h5">Product compatibility</h2>
    <form method="post" class="row g-2 align-items-end mb-4">
      <?= csrf_field() ?>
      <div class="col-md-5"><label class="form-label" for="assign_cake">Cake</label><select id="assign_cake" name="cake_id" class="form-select" required><option value="">Choose cake</option><?php foreach ($cakes as $cake): if (!$cake['available']) continue; ?><option value="<?= (int) $cake['id'] ?>"><?= e($cake['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label" for="assign_option">Option</label><select id="assign_option" name="option_id" class="form-select" required><option value="">Choose option</option><?php foreach ($options as $option): if (!$option['is_active']) continue; ?><option value="<?= (int) $option['id'] ?>"><?= e($option['option_type'] . ' - ' . $option['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-1 form-check mb-2"><input id="required" type="checkbox" name="required" class="form-check-input"><label for="required" class="form-check-label">Required</label></div>
      <div class="col-md-2"><button name="assign" class="btn btn-purple w-100">Assign</button></div>
    </form>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Cake</th><th>Type</th><th>Option</th><th>Rule</th><th></th></tr></thead><tbody>
      <?php foreach ($assignments as $assignment): ?><tr><td><?= e($assignment['cake_name']) ?></td><td><?= e($assignment['option_type']) ?></td><td><?= e($assignment['option_name']) ?></td><td><?= $assignment['is_required'] ? 'Required' : 'Optional' ?></td><td><form method="post" onsubmit="return confirm('Remove this option from the cake?')"><?= csrf_field() ?><input type="hidden" name="cake_id" value="<?= (int) $assignment['cake_id'] ?>"><input type="hidden" name="option_id" value="<?= (int) $assignment['option_id'] ?>"><button name="unassign" class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr><?php endforeach; ?>
      <?php if (!$assignments): ?><tr><td colspan="5" class="text-center help-text py-4">No product compatibility has been configured.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
</main>
<?php page_end();
