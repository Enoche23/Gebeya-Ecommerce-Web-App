<?php
// includes/functions.php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Persistent Cart Logic
if (isset($_SESSION['cart'])) {
  // Sync current session cart to cookie (valid for 30 days)
  setcookie('gebeya_cart', json_encode($_SESSION['cart']), time() + 86400 * 30, '/');
} elseif (isset($_COOKIE['gebeya_cart'])) {
  // Restore cart from cookie if session cart is not set
  $decoded = json_decode($_COOKIE['gebeya_cart'], true);
  if (is_array($decoded)) {
    $_SESSION['cart'] = $decoded;
  } else {
    $_SESSION['cart'] = [];
  }
}

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/*
 * Redirect helper:
 * - redirect('/public/login.php')  -> Location: /gebeya/public/login.php
 * - redirect('public/login.php')   -> Location: /gebeya/public/login.php
 * - redirect('https://example.com') -> Location: https://example.com
 */
function redirect(string $path): void {
  if (preg_match('#^https?://#i', $path)) {
    header("Location: " . $path);
    exit;
  }

  if ($path === "" || $path[0] !== "/") {
    $path = "/" . $path;
  }

  header("Location: " . BASE_URL . $path);
  exit;
}

/* FLASH MESSAGE SYSTEM */

function flash_set(string $type, string $msg): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  $_SESSION['flash'][$type][] = $msg;
}

function flash_get_all(): array {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  $flashes = $_SESSION['flash'] ?? [];
  unset($_SESSION['flash']);
  return $flashes;
}

/* DATA HELPERS */

function get_categories(mysqli $conn): array {
  $cats = [];
  $res = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name");
  while ($row = mysqli_fetch_assoc($res)) {
    $cats[] = $row;
  }
  return $cats;
}

function get_user_roles(mysqli $conn, int $user_id): array {
  $roles = [];

  $sql = "SELECT r.role_name
          FROM user_roles ur
          JOIN roles r ON r.role_id = ur.role_id
          WHERE ur.user_id = ?";

  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $role_name);

  while (mysqli_stmt_fetch($stmt)) {
    $roles[] = $role_name;
  }

  mysqli_stmt_close($stmt);
  return $roles;
}

function has_role(mysqli $conn, string $role): bool {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  if (empty($_SESSION['user_id'])) {
    return false;
  }

  $uid = (int)$_SESSION['user_id'];

  $sql = "SELECT 1
          FROM user_roles ur
          JOIN roles r ON r.role_id = ur.role_id
          WHERE ur.user_id = ? AND r.role_name = ?
          LIMIT 1";

  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "is", $uid, $role);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_store_result($stmt);

  $ok = (mysqli_stmt_num_rows($stmt) > 0);
  mysqli_stmt_close($stmt);

  return $ok;
}
