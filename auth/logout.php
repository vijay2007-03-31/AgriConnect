<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Authentication: logout.php (Complete Session Teardown & Invalidation)
 *
 * Flushes memory state, destroys server-side session data, expires browser cookies,
 * and redirects safely to the login portal with a goodbye flash notification.
 */

require_once __DIR__ . '/../config/db.php';

// 1. Unset all session superglobals
$_SESSION = [];

// 2. Invalidate the session cookie in browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destroy session on server
session_destroy();

// 4. Start a clean transient session just to carry the logout flash notification
session_start();
$_SESSION['flash'] = [
    'type'    => 'info',
    'message' => 'You have been securely signed out of AgriConnect. See you soon!'
];

header("Location: login.php");
exit;
