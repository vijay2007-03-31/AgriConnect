<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Middleware: auth_check.php (Role-Based Access Control & Session Guard)
 *
 * Enforces route authorization, protects sensitive endpoints from unauthenticated
 * tampering, and prevents horizontal privilege escalation between Farmers and Buyers.
 */

require_once __DIR__ . '/../config/db.php';

// Safe Session Initialization Guard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks whether an active authenticated session exists.
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Returns current authenticated user metadata or null.
 *
 * @return array|null
 */
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['name'] ?? 'User',
        'email' => $_SESSION['email'] ?? '',
        'role'  => $_SESSION['role'] ?? 'guest'
    ];
}

/**
 * Verifies if current user matches a specified role.
 *
 * @param string $role ('farmer', 'buyer', 'admin')
 * @return bool
 */
function hasRole(string $role): bool {
    return isLoggedIn() && (isset($_SESSION['role']) && $_SESSION['role'] === $role);
}

/**
 * Route protection middleware requiring active login.
 * Redirects unauthenticated visitors to login.php.
 *
 * @return void
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlashMessage('warning', 'Please sign in to your AgriConnect account to access this page.');
        // Calculate appropriate redirect path based on invocation depth
        $isSubdir = strpos($_SERVER['SCRIPT_NAME'], '/farmer/') !== false || 
                    strpos($_SERVER['SCRIPT_NAME'], '/buyer/') !== false ||
                    strpos($_SERVER['SCRIPT_NAME'], '/auth/') !== false;
        $loginUrl = $isSubdir ? '../auth/login.php' : 'auth/login.php';
        header("Location: {$loginUrl}");
        exit;
    }
}

/**
 * Strict Role Guard. Validates role and halts unauthorized access attempts.
 *
 * @param string $allowedRole ('farmer', 'buyer', 'admin')
 * @return void
 */
function requireRole(string $allowedRole): void {
    requireLogin();

    $currentRole = $_SESSION['role'] ?? '';
    
    // Admins have supervisory access across portals
    if ($currentRole === 'admin') {
        return;
    }

    if ($currentRole !== $allowedRole) {
        setFlashMessage('danger', 'Access Denied: You do not possess authorization credentials for this portal.');
        $isSubdir = strpos($_SERVER['SCRIPT_NAME'], '/farmer/') !== false || 
                    strpos($_SERVER['SCRIPT_NAME'], '/buyer/') !== false;
        
        $redirectUrl = ($currentRole === 'farmer')
            ? ($isSubdir ? '../farmer/dashboard.php' : 'farmer/dashboard.php')
            : ($isSubdir ? '../buyer/dashboard.php' : 'buyer/dashboard.php');

        header("Location: {$redirectUrl}");
        exit;
    }
}
