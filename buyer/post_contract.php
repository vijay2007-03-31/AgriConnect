<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Buyer Portal: post_contract.php (Publish New Procurement Tender Demand)
 *
 * Enables corporate agribusinesses, grain millers, and FMCG buyers to publish
 * explicit crop tonnage, price ceilings, delivery locations, and quality criteria.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('buyer');

$buyerId = (int)$_SESSION['user_id'];
$errors = [];
$formData = [
    'crop_name'                  => 'Wheat',
    'variety'                    => '',
    'required_quintals'          => '',
    'offered_price_per_quintal'  => '',
    'delivery_location'          => '',
    'deadline'                   => date('Y-m-d', strtotime('+30 days'))
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['crop_name']                 = trim($_POST['crop_name'] ?? '');
    $formData['variety']                   = trim($_POST['variety'] ?? '');
    $formData['required_quintals']         = (int)($_POST['required_quintals'] ?? 0);
    $formData['offered_price_per_quintal'] = (float)($_POST['offered_price_per_quintal'] ?? 0);
    $formData['delivery_location']         = trim($_POST['delivery_location'] ?? '');
    $formData['deadline']                  = trim($_POST['deadline'] ?? '');

    // Server-Side Constraint Validation
    if (empty($formData['crop_name'])) {
        $errors[] = 'Please select or enter the crop name.';
    }
    if (empty($formData['variety']) || strlen($formData['variety']) < 2) {
        $errors[] = 'Specific crop variety or grading standard is required (e.g., Sharbati A-Grade).';
    }
    if ($formData['required_quintals'] < 10) {
        $errors[] = 'Required procurement volume must be at least 10 Quintals.';
    }
    if ($formData['offered_price_per_quintal'] < 500) {
        $errors[] = 'Offered price per quintal must be at least ₹500.00.';
    }
    if (empty($formData['delivery_location']) || strlen($formData['delivery_location']) < 5) {
        $errors[] = 'Full warehouse, mandi, or depot delivery address is required.';
    }
    if (empty($formData['deadline']) || strtotime($formData['deadline']) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Tender closing deadline must be a future date.';
    }

    // Insert into Database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO crop_demands (buyer_id, crop_name, variety, required_quintals, offered_price_per_quintal, delivery_location, deadline, status)
                VALUES (:bid, :crop, :variety, :qty, :price, :loc, :deadline, 'open')
            ");
            $stmt->execute([
                ':bid'      => $buyerId,
                ':crop'     => $formData['crop_name'],
                ':variety'  => $formData['variety'],
                ':qty'      => $formData['required_quintals'],
                ':price'    => $formData['offered_price_per_quintal'],
                ':loc'      => $formData['delivery_location'],
                ':deadline' => $formData['deadline']
            ]);

            setFlashMessage('success', "Procurement tender for {$formData['required_quintals']} Quintals of {$formData['crop_name']} ({$formData['variety']}) has been published successfully!");
            header("Location: dashboard.php");
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Database error while publishing tender: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Post Procurement Tender';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="container container-narrow">
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span class="badge badge-role-buyer">Buyer Tender Desk</span>
            </div>
            <h1 style="margin-top: 0.5rem; margin-bottom: 0.5rem;">Publish Crop Procurement Demand</h1>
            <p class="text-muted">
                Create a legally binding pre-harvest procurement listing for regional farmers to discover and apply.
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <div>
                    <strong>Unable to Publish Tender:</strong>
                    <ul style="margin: 0.25rem 0 0 1.25rem; font-size: 0.875rem;">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 2.25rem;">
            <form action="post_contract.php" method="POST" id="tenderForm" data-validate="true">
                <div class="grid grid-2" style="gap: 1rem;">
                    <!-- Crop Selection -->
                    <div class="form-group">
                        <label class="form-label" for="crop_name">Target Crop Commodity <span class="required">*</span></label>
                        <select id="crop_name" name="crop_name" class="form-control" required>
                            <option value="Wheat" <?= ($formData['crop_name'] === 'Wheat') ? 'selected' : ''; ?>>Wheat (Gehun)</option>
                            <option value="Rice" <?= ($formData['crop_name'] === 'Rice') ? 'selected' : ''; ?>>Rice / Basmati (Dhan)</option>
                            <option value="Coconut" <?= ($formData['crop_name'] === 'Coconut') ? 'selected' : ''; ?>>Coconut / Thengai (Pollachi)</option>
                            <option value="Onion" <?= ($formData['crop_name'] === 'Onion') ? 'selected' : ''; ?>>Onion / Pyaz (Nashik Red)</option>
                            <option value="Cotton" <?= ($formData['crop_name'] === 'Cotton') ? 'selected' : ''; ?>>Cotton (Kapas)</option>
                            <option value="Soybean" <?= ($formData['crop_name'] === 'Soybean') ? 'selected' : ''; ?>>Soybean</option>
                            <option value="Maize" <?= ($formData['crop_name'] === 'Maize') ? 'selected' : ''; ?>>Maize / Corn</option>
                            <option value="Mustard" <?= ($formData['crop_name'] === 'Mustard') ? 'selected' : ''; ?>>Mustard Seed (Sarson)</option>
                            <option value="Pulses" <?= ($formData['crop_name'] === 'Pulses') ? 'selected' : ''; ?>>Pulses / Lentils (Dal)</option>
                        </select>
                    </div>

                    <!-- Variety / Specification -->
                    <div class="form-group">
                        <label class="form-label" for="variety">Grade / Variety Specification <span class="required">*</span></label>
                        <input type="text" id="variety" name="variety" class="form-control" value="<?= htmlspecialchars($formData['variety'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Sharbati A-Grade / Pusa 1121" required>
                    </div>
                </div>

                <div class="grid grid-2" style="gap: 1rem;">
                    <!-- Required Volume -->
                    <div class="form-group">
                        <label class="form-label" for="required_quintals">Required Total Volume (Quintals) <span class="required">*</span></label>
                        <input type="number" id="required_quintals" name="required_quintals" class="form-control" value="<?= htmlspecialchars((string)$formData['required_quintals'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 500" min="10" step="5" required>
                        <span class="input-hint">Farmers may apply for full or partial allocations of this demand.</span>
                    </div>

                    <!-- Offered Price -->
                    <div class="form-group">
                        <label class="form-label" for="offered_price_per_quintal">Offered Price per Quintal (₹) <span class="required">*</span></label>
                        <input type="number" id="offered_price_per_quintal" name="offered_price_per_quintal" class="form-control" value="<?= htmlspecialchars((string)$formData['offered_price_per_quintal'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 2450.00" min="100" step="10" required>
                        <span class="input-hint">Must meet or exceed prevailing government MSP standards.</span>
                    </div>
                </div>

                <!-- Delivery Hub Location -->
                <div class="form-group">
                    <label class="form-label" for="delivery_location">Designated Mandi / Warehouse Delivery Hub <span class="required">*</span></label>
                    <input type="text" id="delivery_location" name="delivery_location" class="form-control" value="<?= htmlspecialchars($formData['delivery_location'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Khanna Grain Terminal, Ludhiana District, Punjab" required>
                    <span class="input-hint">Specify exact depot, state, and unloading terminal.</span>
                </div>

                <!-- Submission Deadline -->
                <div class="form-group">
                    <label class="form-label" for="deadline">Tender Application Deadline Date <span class="required">*</span></label>
                    <input type="date" id="deadline" name="deadline" class="form-control" value="<?= htmlspecialchars($formData['deadline'], ENT_QUOTES, 'UTF-8'); ?>" min="<?= date('Y-m-d'); ?>" required>
                    <span class="input-hint">The date when bidding closes for this procurement batch.</span>
                </div>

                <!-- Budget Preview Dynamic Calculation -->
                <div style="background: var(--bg-alt); border-radius: var(--radius-md); padding: 1.25rem; margin: 1.5rem 0; border: 1px solid var(--border);">
                    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.25rem;">
                        Projected Total Procurement Capital Commitment
                    </div>
                    <div id="projectedBudget" style="font-size: 1.75rem; font-weight: 800; color: var(--primary); font-family: var(--font-mono);">
                        ₹ 0.00
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                        Calculated as (Required Volume &times; Offered Price / Qtl).
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem;">
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg" id="btnPublishTender">Publish Procurement Tender</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const qty = document.getElementById('required_quintals');
    const price = document.getElementById('offered_price_per_quintal');
    const budgetEl = document.getElementById('projectedBudget');

    function updateBudget() {
        const q = parseFloat(qty.value) || 0;
        const p = parseFloat(price.value) || 0;
        const total = q * p;
        budgetEl.textContent = new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 2
        }).format(total);
    }

    if (qty && price && budgetEl) {
        qty.addEventListener('input', updateBudget);
        price.addEventListener('input', updateBudget);
        updateBudget();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
