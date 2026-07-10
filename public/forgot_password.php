<?php
// public/forgot_password.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    redirect('/public/index.php');
}

$errors = [];
$success_msg = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $email = trim($_POST['email'] ?? '');

    // Basic Rate Limiting
    if (!isset($_SESSION['reset_attempts'])) {
        $_SESSION['reset_attempts'] = 0;
        $_SESSION['first_reset_attempt'] = time();
    }
    
    if (time() - $_SESSION['first_reset_attempt'] > 900) { // 15 mins
        $_SESSION['reset_attempts'] = 0;
        $_SESSION['first_reset_attempt'] = time();
    }

    if ($_SESSION['reset_attempts'] >= 5) {
        $errors[] = "Too many requests. Please wait 15 minutes.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
        $_SESSION['reset_attempts']++;
    } else {
        $_SESSION['reset_attempts']++;

        $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $user_id);
        
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);

            // Generate cryptographic token
            $raw_token = bin2hex(random_bytes(32));
            $token_hash = password_hash($raw_token, PASSWORD_DEFAULT);
            $expires = date('Y-m-d H:i:s', time() + 900); // 15 minutes
            
            $upd = mysqli_prepare($conn, "UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($upd, "ssi", $token_hash, $expires, $user_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            // Send Email
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $reset_link = $protocol . "://" . $host . BASE_URL . "/public/reset_password.php?token=" . urlencode($raw_token) . "&email=" . urlencode($email);
            send_reset_email($email, $reset_link);
        } else {
            mysqli_stmt_close($stmt);
        }

        // Generic success message to prevent email enumeration attacks
        $success_msg = "Check your inbox! We’ve sent a password reset link to your email.";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid">
  <div class="col-12">
    <div class="card card-pad" style="max-width:500px; margin:0 auto;">
      <h1 style="margin-bottom:5px;">Forgot Password</h1>
      <div class="small" style="margin-bottom:14px;">Enter your email to receive a password reset link.</div>

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

      <?php if ($success_msg): ?>
        <div class="flash ok">
          <?= e($success_msg) ?>
        </div>
        <div style="height:12px;"></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <label class="small"><strong>Email Address</strong></label>
        <input class="input" type="email" name="email" required placeholder="you@example.com">

        <div style="height:16px;"></div>

        <button class="btn primary" type="submit" style="width:100%;">Send Reset Link</button>
      </form>

      <div style="height:20px;"></div>
      <div class="small" style="text-align:center;">
        Remember your password? <a href="<?= BASE_URL ?>/public/login.php">Back to Login</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
