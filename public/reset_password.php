<?php
// public/reset_password.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    redirect('/public/index.php');
}

$errors = [];
$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

if (!$email || !$token) {
    die("Invalid reset link.");
}

// Check if valid
$stmt = mysqli_prepare($conn, "SELECT user_id, reset_token_hash, reset_token_expires_at FROM users WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $user_id, $token_hash, $expires_at);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$found || empty($token_hash)) {
    die("Invalid or expired reset link.");
}

if (strtotime($expires_at) < time()) {
    die("This reset link has expired. Please request a new one.");
}

if (!password_verify($token, $token_hash)) {
    die("Invalid reset token.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password needs an uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password needs a lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password needs a number.";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password needs a special character.";
    } elseif ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    } else {
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $upd = mysqli_prepare($conn, "UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE user_id = ?");
        mysqli_stmt_bind_param($upd, "si", $new_hash, $user_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        flash_set('ok', 'Your password has been reset successfully. Please log in.');
        redirect('/public/login.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad" style="max-width:500px; margin:0 auto;">
      <h1 style="margin-bottom:5px;">Set New Password</h1>
      <div class="small" style="margin-bottom:14px;">Enter a strong new password for your account.</div>

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
        
        <label class="small"><strong>New Password</strong></label>
        <input class="input" type="password" name="password" required autocomplete="new-password">
        <div class="small" style="margin:4px 0 10px 0; color:var(--muted);">
          Min. 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char.
        </div>

        <label class="small"><strong>Confirm Password</strong></label>
        <input class="input" type="password" name="confirm_password" required autocomplete="new-password">

        <div style="height:16px;"></div>

        <button class="btn primary" type="submit" style="width:100%;">Save Password</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
