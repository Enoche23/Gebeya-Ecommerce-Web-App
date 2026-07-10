<?php
// public/logout.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear session data
$_SESSION = [];

// Delete session cookie (important for proper logout)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?: '/',
        $params['domain'] ?: '',
        (bool)$params['secure'],
        (bool)$params['httponly']
    );
}

// Destroy session
session_destroy();

// Start fresh session to avoid browser edge cases
session_start();
session_regenerate_id(true);

// Redirect safely using BASE_URL
redirect('/public/login.php');
