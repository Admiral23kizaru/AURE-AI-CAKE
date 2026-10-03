<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/page.php';
require __DIR__ . '/../inc/phone.php';
require_admin_role();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    try {
        if (isset($_POST['create'])) {
            $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = normalize_ph_phone((string) ($_POST['phone'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? '');
            if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone || strlen($password) < 8 || !in_array($role, ['customer','staff', 'admin'], true)) throw new RuntimeException('Enter a valid name, unique email and mobile number, password of at least 8 characters, and role.');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            $active=$role==='customer'?0:1;$verified=$role==='customer'?null:date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO users(name,email,phone,password_hash,role,is_active,phone_verified_at) VALUES(?,?,?,?,?,?,?)')->execute([$name,$email,$phone,$hash,$role,$active,$verified]);
            $userId = (int) $pdo->lastInsertId();
            if($role!=='customer')$pdo->prepare('INSERT INTO admins(username,password,role,user_id) VALUES(?,?,?,?)')->execute([$name, $hash, $role, $userId]);
            $pdo->commit();
        } elseif (isset($_POST['edit'])) {
            $id = (int) ($_POST['id'] ?? 0);
            $query = $pdo->prepare('SELECT * FROM users WHERE id=?');
            $query->execute([$id]);
            $account = $query->fetch();
            if (!$account) throw new RuntimeException('Account not found.');
            $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = normalize_ph_phone((string) ($_POST['phone'] ?? ''));
            $role = $account['role'] === 'customer' ? 'customer' : (string) ($_POST['role'] ?? $account['role']);
            $password = (string) ($_POST['new_password'] ?? '');
            if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone || !in_array($role, ['customer', 'staff', 'admin'], true)) throw new RuntimeException('Enter valid account details, including a Philippine mobile number.');
            if ($password !== '' && strlen($password) < 8) throw new RuntimeException('A replacement password must be at least 8 characters.');
            $duplicate=$pdo->prepare('SELECT 1 FROM users WHERE (email=? OR phone=?) AND id<>? LIMIT 1');$duplicate->execute([$email,$phone,$id]);if($duplicate->fetchColumn())throw new RuntimeException('That email address or mobile number is already registered.');
            $phoneChanged=$phone!==(string)$account['phone'];
            $verifiedAt=$account['role']==='customer'&&$phoneChanged?null:$account['phone_verified_at'];
            $pdo->beginTransaction();
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET name=?,email=?,phone=?,phone_verified_at=?,role=?,password_hash=? WHERE id=?')->execute([$name, $email, $phone, $verifiedAt, $role, $hash, $id]);
                $pdo->prepare('UPDATE admins SET username=?,role=?,password=? WHERE user_id=?')->execute([$name, $role, $hash, $id]);
            } else {
                $pdo->prepare('UPDATE users SET name=?,email=?,phone=?,phone_verified_at=?,role=? WHERE id=?')->execute([$name, $email, $phone, $verifiedAt, $role, $id]);
                $pdo->prepare('UPDATE admins SET username=?,role=? WHERE user_id=?')->execute([$name, $role, $id]);
            }
            $pdo->commit();
        } elseif (isset($_POST['link_legacy'])) {
            $adminId = (int) ($_POST['admin_id'] ?? 0);
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email.');
            $query = $pdo->prepare('SELECT * FROM admins WHERE id=? AND user_id IS NULL');
            $query->execute([$adminId]);
            $legacy = $query->fetch();
            if (!$legacy) throw new RuntimeException('Legacy account not found.');
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO users(name,email,password_hash,role,is_active,phone_verified_at) VALUES(?,?,?,?,1,NOW())')->execute([$legacy['username'], $email, $legacy['password'], $legacy['role']]);
            $pdo->prepare('UPDATE admins SET user_id=? WHERE id=?')->execute([(int) $pdo->lastInsertId(), $adminId]);
            $pdo->commit();
        } elseif (isset($_POST['toggle'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === user_id()) throw new RuntimeException('You cannot deactivate your current administrator account.');
            $pdo->prepare('UPDATE users SET is_active=1-is_active WHERE id=?')->execute([$id]);
        }
        redirect('accounts.php');
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $error->getCode() === '23000' ? 'That email address or mobile number is already registered.' : 'Unable to update the account.';
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $error instanceof RuntimeException ? $error->getMessage() : 'Unable to update the account.';
    }
}

$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
$roleFilter = in_array((string) ($_GET['role'] ?? ''), ['customer', 'staff', 'admin'], true) ? (string) $_GET['role'] : '';
$where = [];
$args = [];
if ($search !== '') { $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)'; $term = '%' . $search . '%'; array_push($args, $term, $term, $term); }
if ($roleFilter !== '') { $where[] = 'role=?'; $args[] = $roleFilter; }
$query = $pdo->prepare('SELECT id,name,email,phone,role,is_active,phone_verified_at,created_at FROM users' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY role,name');
$query->execute($args);
$users = $query->fetchAll();
$legacyAccounts = $pdo->query('SELECT id,username,role FROM admins WHERE user_id IS NULL ORDER BY role,username')->fetchAll();
page_start('Accounts');
?>
<main class="container-fluid px-3 px-lg-5 py-4">
  <div><div class="eyebrow">Administrator</div><h1 class="h3">Customer and staff accounts</h1><p class="help-text">Accounts with order history are deactivated instead of deleted.</p></div>
  <?php if ($message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
  <div class="row g-4">
    <div class="col-xl-4"><form method="post" class="app-card p-4"><?= csrf_field() ?><h2 class="h5">Create account</h2><p class="small text-muted">Customer accounts remain pending until phone verification.</p><label class="form-label" for="create_name">Name</label><input id="create_name" name="name" maxlength="150" class="form-control" required><label class="form-label mt-2" for="create_email">Unique email</label><input id="create_email" type="email" name="email" maxlength="190" class="form-control" required><label class="form-label mt-2" for="create_phone">Unique mobile number</label><input id="create_phone" name="phone" placeholder="09XXXXXXXXX" class="form-control" required><label class="form-label mt-2" for="create_password">Temporary password</label><input id="create_password" type="password" minlength="8" name="password" class="form-control" required><label class="form-label mt-2" for="create_role">Role</label><select id="create_role" name="role" class="form-select"><option value="customer">Customer (pending verification)</option><option value="staff">Staff</option><option value="admin">Administrator</option></select><button name="create" class="btn btn-purple mt-3">Create account</button></form></div>
    <div class="col-xl-8">
      <form class="app-card p-3 mb-3 row g-2"><div class="col"><label class="visually-hidden" for="search">Search accounts</label><input id="search" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Search name, email, or phone"></div><div class="col-md-3"><select name="role" class="form-select" aria-label="Role filter"><option value="">All roles</option><?php foreach (['customer','staff','admin'] as $role): ?><option value="<?= $role ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option><?php endforeach; ?></select></div><div class="col-auto"><button class="btn btn-outline-primary">Search</button></div></form>
      <?php foreach ($users as $account): ?><form method="post" class="app-card p-3 mb-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $account['id'] ?>"><div class="account-editor"><div><label class="form-label">Name</label><input name="name" value="<?= e($account['name']) ?>" maxlength="150" class="form-control" required></div><div><label class="form-label">Email</label><input type="email" name="email" value="<?= e($account['email']) ?>" maxlength="190" class="form-control" required><?php if ($account['email'] === 'takamurashin3@gmail.com'): ?><small class="text-danger">Temporary local administrator - remove before deployment.</small><?php endif; ?></div><div><label class="form-label">Mobile</label><input name="phone" value="<?= e($account['phone']) ?>" inputmode="tel" placeholder="09XXXXXXXXX" class="form-control" required></div><div><label class="form-label">Role</label><?php if ($account['role'] === 'customer'): ?><input value="Customer" class="form-control" disabled><?php else: ?><select name="role" class="form-select"><option value="staff" <?= $account['role'] === 'staff' ? 'selected' : '' ?>>Staff</option><option value="admin" <?= $account['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select><?php endif; ?></div><div><label class="form-label">New password</label><input type="password" name="new_password" minlength="8" class="form-control" placeholder="Leave unchanged"></div><div class="account-editor__actions"><button name="edit" class="btn btn-outline-primary">Save</button><?php if ((int) $account['id'] !== user_id()): ?><button name="toggle" class="btn btn-outline-secondary"><?= $account['is_active'] ? 'Deactivate' : 'Activate' ?></button><?php endif; ?></div></div><div class="small mt-2">Phone verification: <?= $account['phone_verified_at'] ? 'Verified' : 'Required' ?> &middot; Status: <?= $account['is_active'] ? 'Active' : 'Inactive' ?></div></form><?php endforeach; ?>
    </div>
  </div>
  <?php if ($legacyAccounts): ?><section class="app-card p-4 mt-4"><h2 class="h5">Link legacy staff accounts</h2><p class="help-text">Assign a unique email to activate an existing username/password account in unified login.</p><?php foreach ($legacyAccounts as $legacy): ?><form method="post" class="row g-2 border-top py-3"><?= csrf_field() ?><input type="hidden" name="admin_id" value="<?= (int) $legacy['id'] ?>"><div class="col-md-3 d-flex align-items-center"><?= e($legacy['username']) ?> (<?= e($legacy['role']) ?>)</div><div class="col"><input type="email" name="email" class="form-control" placeholder="Unique email address" required></div><div class="col-auto"><button name="link_legacy" class="btn btn-outline-primary">Assign and activate</button></div></form><?php endforeach; ?></section><?php endif; ?>
</main>
<?php page_end();
