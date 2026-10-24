<?php
/**
 * AgriConnect: Smart Contract Farming & Agritech Management Portal
 * Database Configuration & Core System Bootstrap
 *
 * Full-Stack Architecture: PHP 8.x + MySQL 8.x Relational Database (3NF)
 * Provides a hardened PDO connection singleton, prepared statement enforcement,
 * session lifecycle management, CSRF defense, and XSS sanitization.
 */

// 1. Safe Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie parameters before starting
    session_set_cookie_params([
        'lifetime' => 86400, // 24 hours
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 2. MySQL Database Credentials & Connection Parameters (Default XAMPP / WAMP)
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'agriconnect_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''; // Default empty password for XAMPP
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset={$db_charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Enforce true MySQL server-side prepared statements
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "⚠️ Database Connection Error: " . $e->getMessage() . "\n");
        exit(1);
    }
    // Formatted Academic Error Screen with instructions if MySQL is offline
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Error — AgriConnect</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
            .error-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 2.5rem; max-width: 680px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
            h1 { color: #f87171; font-size: 1.5rem; margin-top: 0; display: flex; align-items: center; gap: 0.5rem; }
            code { background: #0f172a; padding: 0.2rem 0.5rem; border-radius: 4px; color: #38bdf8; font-family: monospace; font-size: 0.9rem; }
            pre { background: #0f172a; padding: 1rem; border-radius: 6px; overflow-x: auto; color: #fbbf24; font-size: 0.85rem; border: 1px solid #334155; }
            .steps { background: #0f172a; border-left: 4px solid #16a34a; padding: 1.25rem; border-radius: 0 6px 6px 0; margin-top: 1.5rem; }
            .steps ol { margin: 0.5rem 0 0 0; padding-left: 1.2rem; }
            .steps li { margin-bottom: 0.6rem; line-height: 1.5; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <h1><span>⚠️</span> AgriConnect: MySQL Connection Handshake Failed</h1>
            <p>PHP PDO was unable to connect to the MySQL database engine. Please review the diagnostic information below:</p>
            <pre><?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'); ?></pre>
            <div class="steps">
                <strong>Academic Setup Checklist (XAMPP / WAMP):</strong>
                <ol>
                    <li>Open <strong>XAMPP / WAMP Control Panel</strong> and ensure the <strong>MySQL</strong> service is started (Port 3306).</li>
                    <li>Launch <code>http://localhost/phpmyadmin</code> in your web browser.</li>
                    <li>Create a database named <code>agriconnect_db</code> (Collation: <code>utf8mb4_unicode_ci</code>).</li>
                    <li>Import the SQL file located at: <code>sql/database.sql</code>.</li>
                    <li>Verify credentials in <code>config/db.php</code> (Host: <code>localhost</code>, User: <code>root</code>, Password: empty).</li>
                </ol>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Returns the active PDO database connection singleton.
 *
 * @return PDO
 */
function getDbConnection(): PDO {
    global $pdo;
    return $pdo;
}

/**
 * Sanitizes input strings against Cross-Site Scripting (XSS).
 *
 * @param mixed $data
 * @return string
 */
function sanitize($data): string {
    if (is_null($data)) return '';
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generates or retrieves existing CSRF token for the current session.
 *
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the provided CSRF token using timing-attack safe comparison.
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sets a flash message to be displayed on the next rendered page.
 *
 * @param string $type ('success', 'danger', 'warning', 'info')
 * @param string $message
 * @return void
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieves and clears the flash message.
 *
 * @return array|null
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Formats a monetary amount into Indian Rupee representation (e.g. ₹2,45,000.00).
 *
 * @param float|int $amount
 * @return string
 */
function formatINR($amount): string {
    return '₹' . number_format((float)$amount, 2);
}
