<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Authentication: register.php (Secure Backend User & Profile Registration)
 *
 * Implements server-side validation, duplicate email guards, BCRYPT hashing,
 * and atomic database transactions (ACID) spanning users & farmer_profiles tables.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// If already logged in, redirect to respective role dashboard
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'farmer';
    header("Location: ../" . ($role === 'farmer' ? 'farmer' : 'buyer') . "/dashboard.php");
    exit;
}

$errors = [];
$formData = [
    'name'           => '',
    'email'          => '',
    'phone'          => '',
    'role'           => 'farmer',
    'land_acres'     => '',
    'soil_type'      => 'Alluvial Black Soil',
    'survey_number'  => '',
    'state'          => '',
    'district'       => '',
    'company_name'   => '',
    'procurement_hub'=> ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Ingest and sanitize inputs
    $formData['name']           = trim($_POST['name'] ?? '');
    $formData['email']          = strtolower(trim($_POST['email'] ?? ''));
    $formData['phone']          = trim($_POST['phone'] ?? '');
    $formData['role']           = trim($_POST['role'] ?? 'farmer');
    $password                   = $_POST['password'] ?? '';
    $confirm_password           = $_POST['confirm_password'] ?? '';

    // Farmer-specific fields
    $formData['land_acres']     = trim($_POST['land_acres'] ?? '');
    $formData['soil_type']      = trim($_POST['soil_type'] ?? 'Alluvial Black Soil');
    $formData['survey_number']  = trim($_POST['survey_number'] ?? '');
    $formData['state']          = trim($_POST['state'] ?? '');
    $formData['district']       = trim($_POST['district'] ?? '');

    // Buyer-specific fields
    $formData['company_name']   = trim($_POST['company_name'] ?? '');
    $formData['procurement_hub']= trim($_POST['procurement_hub'] ?? '');

    // 2. Server-Side Constraint Validations
    if (strlen($formData['name']) < 3) {
        $errors[] = 'Full Name must contain at least 3 alphabetic characters.';
    }

    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (!preg_match('/^[6-9]\d{9}$/', $formData['phone'])) {
        $errors[] = 'Phone number must be a valid 10-digit Indian mobile number.';
    }

    if (strlen($password) < 8 || !preg_match('/\d/', $password) || !preg_match('/[!@#$%^&*(),.?":{}|<>_\-]/', $password)) {
        $errors[] = 'Password must be at least 8 characters long and include at least 1 digit and 1 special symbol.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!in_array($formData['role'], ['farmer', 'buyer'])) {
        $formData['role'] = 'farmer';
    }

    if ($formData['role'] === 'farmer') {
        if (empty($formData['land_acres']) || !is_numeric($formData['land_acres']) || floatval($formData['land_acres']) <= 0) {
            $errors[] = 'Please specify a valid total farm acreage greater than 0.';
        }
        if (empty($formData['survey_number'])) {
            $errors[] = 'Land Survey / Khasra number is required for agricultural verification.';
        }
        if (empty($formData['state']) || empty($formData['district'])) {
            $errors[] = 'State and District are required.';
        }
    }

    // 3. Duplicate Email Check via Prepared Statement
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $formData['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email address already exists. Please sign in instead.';
        }
    }

    // 4. Atomic Database Transaction (ACID Compliance)
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Hash password with standard BCRYPT
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // If buyer provided company name, customize display name
            $finalName = ($formData['role'] === 'buyer' && !empty($formData['company_name'])) 
                ? "{$formData['name']} ({$formData['company_name']})" 
                : $formData['name'];

            // Insert into `users` table
            $stmtUser = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, phone, role)
                VALUES (:name, :email, :password_hash, :phone, :role)
            ");
            $stmtUser->execute([
                ':name'          => $finalName,
                ':email'         => $formData['email'],
                ':password_hash' => $password_hash,
                ':phone'         => $formData['phone'],
                ':role'          => $formData['role']
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            // If role is farmer, insert corresponding normalized profile record
            if ($formData['role'] === 'farmer') {
                $stmtProfile = $pdo->prepare("
                    INSERT INTO farmer_profiles (user_id, land_acres, soil_type, survey_number, state, district)
                    VALUES (:user_id, :land_acres, :soil_type, :survey_number, :state, :district)
                ");
                $stmtProfile->execute([
                    ':user_id'       => $newUserId,
                    ':land_acres'    => floatval($formData['land_acres']),
                    ':soil_type'     => $formData['soil_type'],
                    ':survey_number' => $formData['survey_number'],
                    ':state'         => $formData['state'],
                    ':district'      => $formData['district']
                ]);
            }

            // Commit atomic transaction
            $pdo->commit();

            setFlashMessage('success', 'Your account has been created successfully! Please sign in with your credentials.');
            header("Location: login.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'System error occurred while creating your account: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="container container-narrow">
        <div class="text-center" style="margin-bottom: 2rem;">
            <span class="crop-tag">Stage 2 Backend</span>
            <h1 style="margin-top: 0.5rem; margin-bottom: 0.5rem;">Join AgriConnect Network</h1>
            <p class="text-muted">Direct digital contract farming connecting cultivators with corporate procurement houses.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <div>
                    <strong>Registration Incomplete:</strong>
                    <ul style="margin: 0.5rem 0 0 1.25rem; font-size: 0.875rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 2.25rem;">
            <form action="register.php" method="POST" id="registrationForm" data-validate="true">
                <!-- Role Selector -->
                <div style="display: flex; gap: 1rem; margin-bottom: 1.75rem;">
                    <label style="flex: 1; cursor: pointer;">
                        <input type="radio" name="role" value="farmer" style="display: none;" id="roleRadioFarmer" <?= ($formData['role'] === 'farmer') ? 'checked' : ''; ?>>
                        <div class="role-box <?= ($formData['role'] === 'farmer') ? 'active-role' : ''; ?>" id="boxFarmer">
                            <div style="font-size: 1.5rem;">👨‍🌾</div>
                            <strong>Farmer / Cultivator</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Sell harvests & bid on tenders</div>
                        </div>
                    </label>

                    <label style="flex: 1; cursor: pointer;">
                        <input type="radio" name="role" value="buyer" style="display: none;" id="roleRadioBuyer" <?= ($formData['role'] === 'buyer') ? 'checked' : ''; ?>>
                        <div class="role-box <?= ($formData['role'] === 'buyer') ? 'active-role' : ''; ?>" id="boxBuyer">
                            <div style="font-size: 1.5rem;">🏢</div>
                            <strong>Corporate Agribusiness</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Post procurement crop demand</div>
                        </div>
                    </label>
                </div>

                <!-- Account Credentials -->
                <div class="form-group">
                    <label class="form-label" for="name">Legal Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Ramesh Patel" required>
                </div>

                <div class="grid grid-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="name@domain.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Mobile Phone (10-Digit) <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($formData['phone'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="9876543210" required>
                    </div>
                </div>

                <div class="grid grid-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="password">Create Secure Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Min 8 chars with 1 number & 1 symbol" required>
                        <div class="password-meter"><div class="password-meter-fill" id="password-meter-fill"></div></div>
                        <span class="input-hint" id="password-meter-text">Entropy: ≥ 8 chars, 1 digit, 1 special symbol.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                    </div>
                </div>

                <!-- Farmer Attributes -->
                <div id="farmerSection" style="<?= ($formData['role'] === 'buyer') ? 'display: none;' : ''; ?> margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                    <h4 style="margin-bottom: 1rem; color: var(--primary);">🌾 Agricultural Land Details (3NF Profile)</h4>
                    
                    <div class="grid grid-2" style="gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" for="land_acres">Total Land Area (Acres) <span class="required">*</span></label>
                            <input type="number" id="land_acres" name="land_acres" class="form-control" value="<?= htmlspecialchars($formData['land_acres'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 8.5" step="0.1" min="0.1">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="soil_type">Predominant Soil Type <span class="required">*</span></label>
                            <select id="soil_type" name="soil_type" class="form-control">
                                <option value="Alluvial Black Soil" <?= ($formData['soil_type'] === 'Alluvial Black Soil') ? 'selected' : ''; ?>>Alluvial Black Soil</option>
                                <option value="Red Sandy Loam" <?= ($formData['soil_type'] === 'Red Sandy Loam') ? 'selected' : ''; ?>>Red Sandy Loam</option>
                                <option value="Clayey Loam" <?= ($formData['soil_type'] === 'Clayey Loam') ? 'selected' : ''; ?>>Clayey Loam</option>
                                <option value="Silty Alluvium" <?= ($formData['soil_type'] === 'Silty Alluvium') ? 'selected' : ''; ?>>Silty Alluvium</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="survey_number">Revenue Land Survey / Khasra Number <span class="required">*</span></label>
                        <input type="text" id="survey_number" name="survey_number" class="form-control" value="<?= htmlspecialchars($formData['survey_number'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. SY-4021/B">
                    </div>

                    <div class="grid grid-2" style="gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" for="state">State <span class="required">*</span></label>
                            <input type="text" id="state" name="state" class="form-control" value="<?= htmlspecialchars($formData['state'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Punjab">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="district">District <span class="required">*</span></label>
                            <input type="text" id="district" name="district" class="form-control" value="<?= htmlspecialchars($formData['district'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Ludhiana">
                        </div>
                    </div>
                </div>

                <!-- Buyer Attributes -->
                <div id="buyerSection" style="<?= ($formData['role'] === 'farmer') ? 'display: none;' : ''; ?> margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                    <h4 style="margin-bottom: 1rem; color: var(--primary);">🏢 Corporate Procurement Details</h4>
                    <div class="form-group">
                        <label class="form-label" for="company_name">Company / Mill Entity Name</label>
                        <input type="text" id="company_name" name="company_name" class="form-control" value="<?= htmlspecialchars($formData['company_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. ITC Agri-Business Foods Pvt Ltd">
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Create Verified Account</button>
                </div>
            </form>

            <div style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border); font-size: 0.9rem;">
                Already have an AgriConnect account? <a href="login.php" style="font-weight: 700;">Sign in to your portal &rarr;</a>
            </div>
        </div>
    </div>
</div>

<style>
.role-box {
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    padding: 1.25rem;
    text-align: center;
    background: #ffffff;
    transition: all var(--transition-fast);
}
.role-box.active-role {
    border-color: var(--primary);
    background: var(--primary-subtle);
    color: var(--primary);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rFarmer = document.getElementById('roleRadioFarmer');
    const rBuyer = document.getElementById('roleRadioBuyer');
    const boxFarmer = document.getElementById('boxFarmer');
    const boxBuyer = document.getElementById('boxBuyer');
    const fSec = document.getElementById('farmerSection');
    const bSec = document.getElementById('buyerSection');

    rFarmer.addEventListener('change', () => {
        if (rFarmer.checked) {
            boxFarmer.classList.add('active-role');
            boxBuyer.classList.remove('active-role');
            fSec.style.display = 'block';
            bSec.style.display = 'none';
        }
    });

    rBuyer.addEventListener('change', () => {
        if (rBuyer.checked) {
            boxBuyer.classList.add('active-role');
            boxFarmer.classList.remove('active-role');
            fSec.style.display = 'none';
            bSec.style.display = 'block';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
