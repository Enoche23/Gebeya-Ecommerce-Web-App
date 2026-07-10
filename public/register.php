<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* If already logged in, send them away */
if (!empty($_SESSION['user_id'])) {
  redirect('/public/index.php');
}

$errors = [];

$full_name = '';
$email = '';
$account_type = 'buyer';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  check_csrf();

  $full_name = trim($_POST["full_name"] ?? "");
  $full_name = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $full_name);
  $full_name = strip_tags($full_name);
  $email = trim($_POST["email"] ?? "");
  $password = $_POST["password"] ?? "";
  $account_type = $_POST["account_type"] ?? "buyer"; // buyer or seller only

  if ($full_name === "") $errors[] = "Full name is required.";
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
  
  // Password complexity check
  if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters.";
  } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
    $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
  }
  
  if (!in_array($account_type, ["buyer", "seller"], true)) $errors[] = "Invalid account type.";

  if (!$errors) {
    // Check if email already exists
    $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($check, "s", $email);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if (mysqli_stmt_num_rows($check) > 0) {
      mysqli_stmt_close($check);
      $errors[] = "That email is already registered. Please login instead.";
    } else {
      mysqli_stmt_close($check);

      mysqli_begin_transaction($conn);

      try {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = mysqli_prepare($conn, "
          INSERT INTO users (full_name, email, password_hash, status)
          VALUES (?, ?, ?, 'active')
        ");
        mysqli_stmt_bind_param($stmt, "sss", $full_name, $email, $hash);
        mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // Fetch role_id (buyer or seller)
        $role_stmt = mysqli_prepare($conn, "SELECT role_id FROM roles WHERE role_name = ? LIMIT 1");
        mysqli_stmt_bind_param($role_stmt, "s", $account_type);
        mysqli_stmt_execute($role_stmt);
        mysqli_stmt_bind_result($role_stmt, $role_id);
        mysqli_stmt_fetch($role_stmt);
        mysqli_stmt_close($role_stmt);

        if (empty($role_id)) {
          throw new Exception("Role not found.");
        }

        // Enforce single role: delete any existing row just in case (defensive)
        $del_stmt = mysqli_prepare($conn, "DELETE FROM user_roles WHERE user_id = ?");
        mysqli_stmt_bind_param($del_stmt, "i", $user_id);
        mysqli_stmt_execute($del_stmt);
        mysqli_stmt_close($del_stmt);

        // Insert exactly one role
        $ur_stmt = mysqli_prepare($conn, "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($ur_stmt, "ii", $user_id, $role_id);
        mysqli_stmt_execute($ur_stmt);
        mysqli_stmt_close($ur_stmt);

        mysqli_commit($conn);

        flash_set('ok', 'Account created successfully. You can now login.');
        redirect('/public/login.php');

      } catch (Throwable $e) {
        mysqli_rollback($conn);
        $errors[] = "Registration failed. Please try again.";
      }
    }
  }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad" style="max-width:720px; margin:0 auto;">
      <h1 style="margin-bottom:5px;">Create your Gebeya account</h1>
      <div class="small" style="margin-bottom:14px;">
        Choose whether you want to register as a buyer or a seller.
      </div>

      <?php if ($errors): ?>
        <div class="flash danger">
          <strong>Please fix the following:</strong>
          <ul style="margin:8px 0 0 18px;">
            <?php foreach ($errors as $e): ?>
              <li><?= e($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div style="height:12px;"></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <label class="small"><strong>Full Name</strong></label>
        <input class="input" name="full_name" required value="<?= e($full_name) ?>" autocomplete="off">

        <div style="height:10px;"></div>

        <label class="small"><strong>Email</strong></label>
        <input class="input" name="email" type="email" required value="<?= e($email) ?>" autocomplete="off">

        <div style="height:10px;"></div>

        <label class="small"><strong>Password</strong></label>
        <input class="input" name="password" type="password" required autocomplete="new-password">
        <div class="small">Minimum 8 characters, including upper, lower, number, and special character.</div>

        <div style="height:12px;"></div>

        <label class="small"><strong>Account Type</strong></label>
        <select class="input" name="account_type">
          <option value="buyer"  <?= $account_type === 'buyer' ? 'selected' : '' ?>>Buyer</option>
          <option value="seller" <?= $account_type === 'seller' ? 'selected' : '' ?>>Seller</option>
        </select>

        <div style="height:16px;"></div>

        <button class="btn primary" type="submit" style="width:100%;">Create Account</button>

        <div style="height:10px;"></div>

        <div class="small" style="text-align:center;">
          Already have an account?
          <a href="<?= BASE_URL ?>/public/login.php">Login</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
