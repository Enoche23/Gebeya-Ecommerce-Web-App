<?php
// includes/config.php

// Secure Session Cookie Settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
// ini_set('session.cookie_secure', 1); // Uncomment if using HTTPS

// Global Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Use RELATIVE base URL so it works on any localhost port
define('BASE_URL', '/gebeya');
