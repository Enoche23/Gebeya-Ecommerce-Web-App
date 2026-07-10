<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php'; // redirect(), flash_set(), has_role()
require_once __DIR__ . '/db.php';        // $conn when needed (safe include)

function is_logged_in(): bool {
  return !empty($_SESSION['user_id']);
}

function require_login(): void {
  if (!is_logged_in()) {
    flash_set('danger', 'You must be logged in to perform this action.');
    redirect('/public/login.php');
  }
}

/*
 * In a single-role world, this returns what we treat as the user's main role.
 * Priority: admin > seller > buyer
 */
function get_primary_role(mysqli $conn): string {
  if (!is_logged_in()) return 'guest';

  if (has_role($conn, 'admin')) return 'admin';
  if (has_role($conn, 'seller')) return 'seller';
  return 'buyer';
}

/*
 * Require a specific role.
 * If not allowed: redirect to a sensible place with flash message.
 */
function require_role(mysqli $conn, string $role_name): void {
  require_login();

  if (!has_role($conn, $role_name)) {
    flash_set('danger', 'You do not have permission to access that page.');

    $r = get_primary_role($conn);
    if ($r === 'admin') redirect('/admin/admin_dashboard.php');
    if ($r === 'seller') redirect('/seller/seller_dashboard.php');
    redirect('/public/index.php');
  }
}

/*
 * For pages that should NOT be accessible by a given role (e.g., buyer-only pages).
 */
function require_not_role(mysqli $conn, string $role_name): void {
  require_login();

  if (has_role($conn, $role_name)) {
    flash_set('danger', 'That page is not available for your account type.');

    $r = get_primary_role($conn);
    if ($r === 'admin') redirect('/admin/admin_dashboard.php');
    if ($r === 'seller') redirect('/seller/seller_dashboard.php');
    redirect('/public/index.php');
  }
}

/*
 * Buyer-only pages: blocks sellers/admins.
 */
function require_buyer_only(mysqli $conn): void {
  require_login();

  if (has_role($conn, 'admin') || has_role($conn, 'seller')) {
    flash_set('danger', 'This page is for buyers only.');
    $r = get_primary_role($conn);
    if ($r === 'admin') redirect('/admin/admin_dashboard.php');
    if ($r === 'seller') redirect('/seller/seller_dashboard.php');
    redirect('/public/index.php');
  }
}

/* CSRF PROTECTION */

function generate_csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
  if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    return false;
  }
  return true;
}

function check_csrf(): void {
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf_token($token)) {
    flash_set('danger', 'Invalid security token. Please refresh the page and try again.');
    $fallback = '/public/index.php';
    if (!empty($_SERVER['HTTP_REFERER'])) {
      $parsed = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
      if ($parsed) {
        $fallback = str_replace(BASE_URL, '', $parsed);
      }
    }
    redirect($fallback);
  }
}
