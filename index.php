<?php
/**
 * AgriConnect — Root Router & Entrypoint Bootstrap
 *
 * Automatically routes root web requests:
 * - If authenticated: routes to the user's role dashboard (farmer or buyer).
 * - If guest: routes to the Stage 1 responsive landing page (public/index.html).
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'farmer';
    if ($role === 'farmer') {
        header("Location: farmer/dashboard.php");
        exit;
    } else {
        header("Location: buyer/dashboard.php");
        exit;
    }
}

// Redirect guest visitors to the Stage 1 interactive web architecture
header("Location: public/index.html");
exit;
