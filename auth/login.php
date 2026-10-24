<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Authentication: login.php (Authentication Gateway & Session Lifecycle Controller)
 *
 * Implements PDO parameterized credential verification, BCRYPT password checks,
 * session regeneration to counter session fixation, and role-based redirect dispatching.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Redirect already authenticated users directly to their appropriate workspace
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'farmer';
    header("Location: ../" . ($role === 'farmer' ? 'farmer' : 'buyer') . "/dashboard.php");
    exit;
}

$error = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($emailInput) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        try {
            // Prepared statement defense against SQL injection
            $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $emailInput]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Prevent Session Fixation: Generate a fresh cryptographic session ID
                session_regenerate_id(true);

                // Populate authenticated session state
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];

                setFlashMessage('success', "Welcome back, {$user['name']}! You have signed in as " . ucfirst($user['role']) . '.');

                // Role-based redirect routing
                if ($user['role'] === 'farmer') {
                    header("Location: ../farmer/dashboard.php");
                } else {
                    // Buyers & Admins route to Corporate Agribusiness Tender Workspace
                    header("Location: ../buyer/dashboard.php");
                }
                exit;
            } else {
                $error = 'Invalid email address or password. Please verify your credentials.';
            }
        } catch (PDOException $e) {
            $error = 'Authentication service encountered a database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="container" style="max-width: 520px;">
        <div class="text-center" style="margin-bottom: 2rem;">
            <div class="brand-icon" style="margin: 0 auto 1rem auto; width: 50px; height: 50px; font-size: 1.6rem;">🌱</div>
            <h1 style="font-size: 1.85rem; margin-bottom: 0.5rem;">Sign In to AgriConnect</h1>
            <p class="text-muted">Access your personalized Contract Farming & Procurement Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        <?php endif; ?>

        <!-- Quick Demo Credentials Examiner Card -->
        <div class="card" style="margin-bottom: 1.5rem; background: var(--bg-alt); padding: 1.25rem; border-left: 4px solid var(--accent);">
            <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--accent-hover); margin-bottom: 0.5rem;">
                🎓 Academic Evaluation: Quick Demo Logins
            </div>
            <p style="font-size: 0.825rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                Click any button to auto-fill pre-seeded credentials with active database contracts:
            </p>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary btn-sm" id="btnDemoFarmer" style="font-size: 0.775rem;">
                    👨‍🌾 Farmer (Ramesh)
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="btnDemoBuyer" style="font-size: 0.775rem;">
                    🏢 Buyer (ITC Agro)
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="btnDemoAdmin" style="font-size: 0.775rem;">
                    🛡️ Admin
                </button>
            </div>
        </div>

        <div class="card" style="padding: 2.25rem;">
            <form action="login.php" method="POST" id="loginForm" data-validate="true">
                <div class="form-group">
                    <label class="form-label" for="login_email">Registered Email Address <span class="required">*</span></label>
                    <input type="email" id="login_email" name="email" class="form-control" value="<?= htmlspecialchars($emailInput, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. ramesh.farmer@agriconnect.org" required autofocus>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label class="form-label" for="login_password">Password <span class="required">*</span></label>
                    </div>
                    <input type="password" id="login_password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="btn-login-submit">Sign In to Dashboard</button>
                </div>
            </form>

            <div style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border); font-size: 0.9rem;">
                Don't have an account yet? <a href="register.php" style="font-weight: 700;">Register as Farmer / Buyer &rarr;</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const emailField = document.getElementById('login_email');
    const pwdField = document.getElementById('login_password');

    document.getElementById('btnDemoFarmer')?.addEventListener('click', () => {
        emailField.value = 'ramesh.farmer@agriconnect.org';
        pwdField.value = 'password123';
    });

    document.getElementById('btnDemoBuyer')?.addEventListener('click', () => {
        emailField.value = 'vikram.buyer@itcagro.com';
        pwdField.value = 'password123';
    });

    document.getElementById('btnDemoAdmin')?.addEventListener('click', () => {
        emailField.value = 'admin@agriconnect.org';
        pwdField.value = 'password123';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
