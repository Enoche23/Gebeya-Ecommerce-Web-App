<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
if (session_status() === PHP_SESSION_NONE) session_start();

$uid = (int)$_SESSION['user_id'];
$errors = [];

$edit = ((int)($_GET['edit'] ?? 0) === 1);        // edit profile section
$addr_edit = ((int)($_GET['addr'] ?? 0) === 1);  // manage addresses section

/* ============================
   UPDATE PROFILE (NAME + PHONE)
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
  check_csrf();
  $full_name = trim($_POST['full_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');

  if ($full_name === '') $errors[] = "Full name is required.";
  if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
    $errors[] = "Invalid phone number format.";
  }

  if (!$errors) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET full_name=?, phone=? WHERE user_id=?");
    mysqli_stmt_bind_param($stmt, "ssi", $full_name, $phone, $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    flash_set('ok', 'Profile updated.');
    redirect('/public/profile.php');
  } else {
    $edit = true; // stay in edit mode if errors
  }
}

/* ============================
   ADD ADDRESS
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_address') {
  check_csrf();
  $label = trim($_POST['label'] ?? 'Home');
  $city = trim($_POST['city'] ?? '');
  $street = trim($_POST['street'] ?? '');
  $house_no = trim($_POST['house_no'] ?? '');
  $postal_code = trim($_POST['postal_code'] ?? '');

  if ($label === '') $label = 'Home';
  if ($city === '') $errors[] = "City is required.";
  if ($street === '') $errors[] = "Street is required.";

  if (!$errors) {
    $stmt = mysqli_prepare($conn, "
      INSERT INTO addresses (user_id, label, city, street, house_no, postal_code)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, "isssss", $uid, $label, $city, $street, $house_no, $postal_code);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    flash_set('ok', 'Address added.');
    redirect('/public/profile.php');
  } else {
    $addr_edit = true;
  }
}

/* ============================
   DELETE ADDRESS
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_address') {
  check_csrf();
  $address_id = (int)($_POST['address_id'] ?? 0);
  if ($address_id > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM addresses WHERE address_id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, "ii", $address_id, $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    flash_set('ok', 'Address deleted.');
    redirect('/public/profile.php');
  }
}

/* ============================
   LOAD USER
============================ */
$u_stmt = mysqli_prepare($conn, "
  SELECT full_name, email, phone, status, created_at
  FROM users
  WHERE user_id=? LIMIT 1
");
mysqli_stmt_bind_param($u_stmt, "i", $uid);
mysqli_stmt_execute($u_stmt);
$u_res = mysqli_stmt_get_result($u_stmt);
$user = mysqli_fetch_assoc($u_res);
mysqli_stmt_close($u_stmt);

/* ============================
   LOAD ADDRESSES
============================ */
$a_stmt = mysqli_prepare($conn, "
  SELECT address_id, label, city, street, house_no, postal_code, created_at
  FROM addresses
  WHERE user_id=?
  ORDER BY created_at DESC
");
mysqli_stmt_bind_param($a_stmt, "i", $uid);
mysqli_stmt_execute($a_stmt);
$a_res = mysqli_stmt_get_result($a_stmt);

$addresses = [];
while ($row = mysqli_fetch_assoc($a_res)) $addresses[] = $row;
mysqli_stmt_close($a_stmt);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad" style="max-width:1000px; margin:0 auto;">

      <div class="form-row" style="align-items:center;">
        <div>
          <h1 style="margin-bottom:4px;">My Profile</h1>
          <div class="small">Account info and delivery addresses.</div>
        </div>

        <div style="text-align:right; display:flex; gap:10px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
          <?php if (!$edit): ?>
            <a class="btn small" href="<?= BASE_URL ?>/public/profile.php?edit=1">Edit Profile</a>
          <?php else: ?>
            <a class="btn small" href="<?= BASE_URL ?>/public/profile.php">Done</a>
          <?php endif; ?>

          <?php if (!$addr_edit): ?>
            <a class="btn small" href="<?= BASE_URL ?>/public/profile.php?addr=1">Manage Addresses</a>
          <?php else: ?>
            <a class="btn small" href="<?= BASE_URL ?>/public/profile.php">Done</a>
          <?php endif; ?>

          <a class="btn danger small" href="<?= BASE_URL ?>/public/logout.php">Logout</a>
        </div>
      </div>

      <?php if ($errors): ?>
        <div class="hr"></div>
        <div class="flash danger">
          <?php foreach ($errors as $e): ?>
            <div><?= e($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="hr"></div>

      <!-- =========================
           PROFILE SECTION
      ========================== -->
      <h2 style="margin:0 0 10px;">Account</h2>

      <?php if (!$edit): ?>
        <div class="grid">
          <div class="col-6">
            <div class="small"><strong>Name</strong></div>
            <div style="margin-top:4px; font-weight:900; font-size:18px;">
              <?= e($user['full_name'] ?? '') ?>
            </div>
          </div>

          <div class="col-6">
            <div class="small"><strong>Email</strong></div>
            <div style="margin-top:4px;"><?= e($user['email'] ?? '') ?></div>
          </div>

          <div class="col-6">
            <div class="small"><strong>Phone</strong></div>
            <div style="margin-top:4px;"><?= e($user['phone'] ?? '-') ?></div>
          </div>

          <div class="col-6">
            <div class="small"><strong>Status</strong></div>
            <div style="margin-top:4px;">
              <span class="badge <?= (strtolower($user['status'] ?? '') === 'active') ? 'ok' : 'warn' ?>">
                <?= e($user['status'] ?? '-') ?>
              </span>
            </div>
          </div>

          <div class="col-12">
            <div class="small" style="margin-top:8px;">
              Member since <?= e($user['created_at'] ?? '') ?>
            </div>
          </div>
        </div>

      <?php else: ?>
        <form method="post" style="max-width:640px;">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="action" value="update_profile">

          <label class="small"><strong>Full Name</strong></label>
          <input class="input" name="full_name" required value="<?= e($_POST['full_name'] ?? ($user['full_name'] ?? '')) ?>">

          <div style="height:10px;"></div>

          <label class="small"><strong>Email</strong></label>
          <input class="input" value="<?= e($user['email'] ?? '') ?>" disabled>

          <div style="height:10px;"></div>

          <label class="small"><strong>Phone</strong></label>
          <input class="input" name="phone" placeholder="+1234567890"
                 value="<?= e($_POST['phone'] ?? ($user['phone'] ?? '')) ?>">

          <div style="height:14px;"></div>

          <div class="form-row">
            <button class="btn primary" type="submit">Save</button>
            <a class="btn" href="<?= BASE_URL ?>/public/profile.php">Cancel</a>
          </div>
        </form>
      <?php endif; ?>

      <div class="hr"></div>

      <!-- =========================
           ADDRESSES SECTION
      ========================== -->
      <div class="form-row" style="align-items:center;">
        <h2 style="margin:0;">Addresses</h2>
        <!-- <?php if (!$addr_edit): ?>
          <a class="btn small" href="<?= BASE_URL ?>/public/profile.php?addr=1">Manage</a>
        <?php endif; ?> -->
      </div>

      <?php if (!$addresses): ?>
        <div class="flash" style="margin-top:10px;">No addresses saved yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table" style="margin-top:12px;">
          <tr>
            <th>Label</th><th>City</th><th>Street</th><th>House</th><th>Postal</th>
            <?php if ($addr_edit): ?><th style="width:110px;"></th><?php endif; ?>
          </tr>

          <?php foreach ($addresses as $a): ?>
            <tr>
              <td><?= e($a['label']) ?></td>
              <td><?= e($a['city']) ?></td>
              <td><?= e($a['street']) ?></td>
              <td><?= e($a['house_no'] ?? '') ?></td>
              <td><?= e($a['postal_code'] ?? '') ?></td>

              <?php if ($addr_edit): ?>
                <td>
                  <form method="post" style="margin:0;" onsubmit="return confirm('Delete this address?');">
                    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_address">
                    <input type="hidden" name="address_id" value="<?= (int)$a['address_id'] ?>">
                    <button class="btn danger small" type="submit">Delete</button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </table>
        </div>
      <?php endif; ?>

      <?php if ($addr_edit): ?>
        <div class="hr"></div>

        <h3 style="margin-bottom:10px;">Add Address</h3>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="action" value="add_address">

          <div class="grid">
            <div class="col-4">
              <label class="small">Label</label>
              <input class="input" name="label" value="<?= e($_POST['label'] ?? 'Home') ?>">
            </div>

            <div class="col-4">
              <label class="small">City *</label>
              <input class="input" name="city" required value="<?= e($_POST['city'] ?? '') ?>">
            </div>

            <div class="col-4">
              <label class="small">Street *</label>
              <input class="input" name="street" required value="<?= e($_POST['street'] ?? '') ?>">
            </div>

            <div class="col-4">
              <label class="small">House No</label>
              <input class="input" name="house_no" value="<?= e($_POST['house_no'] ?? '') ?>">
            </div>

            <div class="col-4">
              <label class="small">Postal Code</label>
              <input class="input" name="postal_code" value="<?= e($_POST['postal_code'] ?? '') ?>">
            </div>
          </div>

          <div style="margin-top:14px;">
            <button class="btn primary" type="submit">Add Address</button>
            <a class="btn" href="<?= BASE_URL ?>/public/profile.php">Done</a>
          </div>
        </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
