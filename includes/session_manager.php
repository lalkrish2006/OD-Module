<?php
if (session_status() === PHP_SESSION_NONE) {
    // Secure Session Configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'); // Only if HTTPS is enabled
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 1800); // 30 minutes

    session_start();
}

// Session Timeout Management (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: loginin.php?msg=timeout");
    exit;
}
$_SESSION['last_activity'] = time();

// Regenerate Session ID periodically to prevent fixation (every 5 minutes)
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 300) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
?>