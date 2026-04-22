<?php
// logout.php — Destroy session and redirect
require_once 'config.php';

if (isLoggedIn()) {
    // Clear all session data
    $_SESSION = [];

    // Destroy the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

// Flash message won't work here since session is destroyed,
// so we pass a query param to the login page instead
header('Location: login.php?logged_out=1');
exit;