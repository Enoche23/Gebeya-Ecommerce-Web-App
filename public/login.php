<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* If already logged in, route to correct dashboard */
if (!empty($_SESSION['user_id'])) {
  if (has_role($conn, "admin")) {
    redirect('/admin/admin_dashboard.php');
  } elseif (has_role($conn, "seller")) {
    redirect('/seller/seller_dashboard.php');
  } else {
    redirect('/public/index.php');
  }
}

$errors = [];
$email = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  check_csrf();
  $email = trim($_POST["email"] ?? "");
  $password = $_POST["password"] ?? "";

  // Basic Rate Limiting
  $max_attempts = 5;
  $lockout_time = 300; // 5 minutes

  if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= $max_attempts) {
    if (time() - $_SESSION['last_login_attempt'] < $lockout_time) {
      $errors[] = "Too many failed attempts. Please wait 5 minutes before trying again.";
    } else {
      $_SESSION['login_attempts'] = 0; // reset after lockout
    }
  }

  if (!$errors) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = "Enter a valid email.";
    } elseif ($password === "") {
      $errors[] = "Password is required.";
    } else {
    $stmt = mysqli_prepare($conn, "SELECT user_id, password_hash, status FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $user_id, $hash, $status);

    if (mysqli_stmt_fetch($stmt)) {
      mysqli_stmt_close($stmt);

      if ($status !== "active") {
        $errors[] = "Account is not active.";
      } elseif (!password_verify($password, $hash)) {
        $errors[] = "Invalid email or password.";
      } else {
        // MFA OTP GENERATION
        $otp = (string)random_int(100000, 999999);
        $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + 300); // 5 minutes expiry
        
        $upd = mysqli_prepare($conn, "UPDATE users SET otp_hash = ?, otp_expires_at = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($upd, "ssi", $otp_hash, $expires, $user_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        // Send Email
        send_otp_email($email, $otp);

        // Set pending MFA session
        $_SESSION["pending_mfa_user_id"] = (int)$user_id;
        session_regenerate_id(true);

        // Reset login attempts on successful password verification
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['last_login_attempt']);

        redirect('/public/verify_otp.php');
      }
    } else {
      mysqli_stmt_close($stmt);
      $errors[] = "Invalid email or password.";
    }
  }
}

  if ($errors && !isset($_SESSION['login_attempts']) || $_SESSION['login_attempts'] < $max_attempts) {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['last_login_attempt'] = time();
  }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad" style="max-width:640px; margin:0 auto;">
      <h1 style="margin-bottom:5px;">Login</h1>
      <div class="small" style="margin-bottom:14px;">Access your Gebeya account.</div>

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

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <label class="small"><strong>Email</strong></label>
        <input class="input" type="email" name="email" required value="<?= e($email) ?>" autocomplete="off">

        <div style="height:10px;"></div>

        <label class="small"><strong>Password</strong></label>
        <input class="input" type="password" name="password" required autocomplete="new-password">
        
        <div style="text-align:right; margin-top:4px;">
          <a href="<?= BASE_URL ?>/public/forgot_password.php" class="small" style="color:var(--brand); text-decoration:none;">Forgot Password?</a>
        </div>

        <div style="height:16px;"></div>

        <button class="btn primary" type="submit" style="width:100%;">Login</button>

        <div style="height:10px;"></div>

        <div class="small" style="text-align:center;">
          Don’t have an account?
          <a href="<?= BASE_URL ?>/public/register.php">Register</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
