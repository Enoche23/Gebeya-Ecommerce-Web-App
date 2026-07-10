<?php
// public/verify_otp.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// If no pending MFA, send back to login
if (empty($_SESSION['pending_mfa_user_id'])) {
    redirect('/public/login.php');
}

$user_id = (int)$_SESSION['pending_mfa_user_id'];
$errors = [];

// Handle OTP resend
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    require_once __DIR__ . '/../includes/mailer.php';
    
    // Check if we can resend (very basic rate limit)
    if (isset($_SESSION['last_otp_sent']) && (time() - $_SESSION['last_otp_sent']) < 60) {
        $errors[] = "Please wait at least 60 seconds before requesting a new code.";
    } else {
        $otp = (string)random_int(100000, 999999);
        $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + 300);
        
        $upd = mysqli_prepare($conn, "UPDATE users SET otp_hash = ?, otp_expires_at = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($upd, "ssi", $otp_hash, $expires, $user_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        // Fetch user email
        $get = mysqli_prepare($conn, "SELECT email FROM users WHERE user_id = ?");
        mysqli_stmt_bind_param($get, "i", $user_id);
        mysqli_stmt_execute($get);
        mysqli_stmt_bind_result($get, $email);
        mysqli_stmt_fetch($get);
        mysqli_stmt_close($get);

        send_otp_email($email, $otp);
        $_SESSION['last_otp_sent'] = time();
        flash_set('ok', 'A new code has been sent to your email.');
        redirect('/public/verify_otp.php');
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $otp_input = trim($_POST['otp'] ?? '');

    // Rate limit OTP attempts: max 3 per 5 minutes
    if (!isset($_SESSION['otp_attempts'])) {
        $_SESSION['otp_attempts'] = 0;
        $_SESSION['first_otp_attempt'] = time();
    }
    
    if (time() - $_SESSION['first_otp_attempt'] > 300) {
        // Reset after 5 mins
        $_SESSION['otp_attempts'] = 0;
        $_SESSION['first_otp_attempt'] = time();
    }

    if ($_SESSION['otp_attempts'] >= 3) {
        $errors[] = "Too many failed attempts. Please request a new code.";
    } elseif (empty($otp_input) || strlen($otp_input) !== 6) {
        $errors[] = "Please enter the 6-digit code.";
        $_SESSION['otp_attempts']++;
    } else {
        $stmt = mysqli_prepare($conn, "SELECT otp_hash, otp_expires_at FROM users WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $otp_hash, $otp_expires_at);
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if (!$found || empty($otp_hash)) {
            $errors[] = "No pending verification found.";
        } elseif (strtotime($otp_expires_at) < time()) {
            $errors[] = "This code has expired. Please request a new one.";
        } elseif (!password_verify($otp_input, $otp_hash)) {
            $errors[] = "Invalid code.";
            $_SESSION['otp_attempts']++;
        } else {
            // SUCCESS!
            // Clear OTP
            $clear = mysqli_prepare($conn, "UPDATE users SET otp_hash = NULL, otp_expires_at = NULL WHERE user_id = ?");
            mysqli_stmt_bind_param($clear, "i", $user_id);
            mysqli_stmt_execute($clear);
            mysqli_stmt_close($clear);

            // Log user in
            $_SESSION['user_id'] = $user_id;
            unset($_SESSION['pending_mfa_user_id']);
            unset($_SESSION['otp_attempts']);
            unset($_SESSION['first_otp_attempt']);
            session_regenerate_id(true);

            // Routing
            if (has_role($conn, "admin")) {
                redirect('/admin/admin_dashboard.php');
            } elseif (has_role($conn, "seller")) {
                redirect('/seller/seller_dashboard.php');
            } else {
                redirect('/public/index.php');
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad" style="max-width:500px; margin:0 auto;">
      <h1 style="margin-bottom:5px; text-align:center;">Enter Verification Code</h1>
      <div class="small" style="text-align:center; margin-bottom:14px;">We've sent a 6-digit code to your email.</div>

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
        <label class="small"><strong>6-Digit Code</strong></label>
        <input class="input" type="text" name="otp" required maxlength="6" pattern="\d{6}" placeholder="123456" style="text-align:center; font-size: 24px; letter-spacing: 10px;">

        <div style="height:16px;"></div>

        <button class="btn primary" type="submit" style="width:100%;">Verify & Login</button>
      </form>

      <div style="height:20px;"></div>
      <div class="small" style="text-align:center;">
        Didn't receive it? <a href="<?= BASE_URL ?>/public/verify_otp.php?action=resend">Resend Code</a><br><br>
        <a href="<?= BASE_URL ?>/public/logout.php" class="danger">Cancel Login</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
