<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Buyer Portal: manage_bids.php (Farmer Contract Applications Review & Approval Engine)
 *
 * Provides granular evaluation of farmer land profiles, soil data, and proposed
 * tonnage, featuring one-click status transitions (Approve / Reject) with authorization verification.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('buyer');

$buyerId = (int)$_SESSION['user_id'];
$demandFilter = (int)($_GET['demand_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? 'all');

// 1. Handle Status Transition (Approve / Reject) Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $bidId = (int)($_POST['bid_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');

    if (!in_array($newStatus, ['approved', 'rejected', 'pending'])) {
        setFlashMessage('danger', 'Invalid contract status requested.');
    } else {
        // Strict Authorization Guard: Ensure the bid belongs to a tender published by this buyer
        $stmtVerify = $pdo->prepare("
            SELECT c.id, cd.crop_name, u_farmer.name AS farmer_name
            FROM contracts c
            JOIN crop_demands cd ON c.demand_id = cd.id
            JOIN users u_farmer ON c.farmer_id = u_farmer.id
            WHERE c.id = :bid_id AND cd.buyer_id = :buyer_id
            LIMIT 1
        ");
        $stmtVerify->execute([':bid_id' => $bidId, ':buyer_id' => $buyerId]);
        $verifiedBid = $stmtVerify->fetch();

        if (!$verifiedBid) {
            setFlashMessage('danger', 'Authorization Error: You do not own the procurement tender associated with this application.');
        } else {
            // Execute state update
            $stmtUpdate = $pdo->prepare("UPDATE contracts SET status = :status WHERE id = :id");
            $stmtUpdate->execute([':status' => $newStatus, ':id' => $bidId]);

            $statusText = ucfirst($newStatus);
            setFlashMessage('success', "Contract Application #BID-" . str_pad($bidId, 4, '0', STR_PAD_LEFT) . " from {$verifiedBid['farmer_name']} has been {$statusText}!");

            // Redirect back preserving current filters
            $redirectUrl = "manage_bids.php?status=" . urlencode($statusFilter) . ($demandFilter > 0 ? "&demand_id={$demandFilter}" : '');
            header("Location: {$redirectUrl}");
            exit;
        }
    }
}

// 2. Fetch Buyer's Demands for Dropdown Filter
$stmtAllDemands = $pdo->prepare("SELECT id, crop_name, variety FROM crop_demands WHERE buyer_id = :bid ORDER BY created_at DESC");
$stmtAllDemands->execute([':bid' => $buyerId]);
$buyerDemands = $stmtAllDemands->fetchAll();

// 3. Query Bids with Joined Agronomic Profiles
$sql = "
    SELECT 
        c.id AS bid_id,
        c.proposed_quantity,
        c.bid_notes,
        c.status AS bid_status,
        c.applied_at,
        c.updated_at,
        cd.id AS demand_id,
        cd.crop_name,
        cd.variety,
        cd.offered_price_per_quintal,
        cd.delivery_location,
        u_farmer.id AS farmer_id,
        u_farmer.name AS farmer_name,
        u_farmer.email AS farmer_email,
        u_farmer.phone AS farmer_phone,
        fp.land_acres,
        fp.soil_type,
        fp.survey_number,
        fp.district,
        fp.state
    FROM contracts c
    JOIN crop_demands cd ON c.demand_id = cd.id
    JOIN users u_farmer ON c.farmer_id = u_farmer.id
    LEFT JOIN farmer_profiles fp ON u_farmer.id = fp.user_id
    WHERE cd.buyer_id = :bid
";

$params = [':bid' => $buyerId];

if ($demandFilter > 0) {
    $sql .= " AND cd.id = :demand_id";
    $params[':demand_id'] = $demandFilter;
}

if (in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $sql .= " AND c.status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY c.applied_at DESC";

$stmtBids = $pdo->prepare($sql);
$stmtBids->execute($params);
$bids = $stmtBids->fetchAll();

$pageTitle = 'Manage Farmer Contract Applications';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="container">
        <!-- Page Title -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="crop-tag">Tender Desk</span>
                <h1 style="margin-top: 0.5rem; margin-bottom: 0.5rem;">Review Farmer Applications</h1>
                <p class="text-muted">
                    Examine verified land survey numbers, soil types, and committed volumes. Execute formal approval decisions.
                </p>
            </div>
            <a href="post_contract.php" class="btn btn-primary">+ Post New Tender Demand</a>
        </div>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 2rem; padding: 1.25rem;">
            <form action="manage_bids.php" method="GET" style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem; align-items: center;">
                <div>
                    <select name="demand_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All My Published Tenders</option>
                        <?php foreach ($buyerDemands as $bd): ?>
                            <option value="<?= (int)$bd['id']; ?>" <?= ($demandFilter === (int)$bd['id']) ? 'selected' : ''; ?>>
                                #TND-<?= str_pad($bd['id'], 4, '0', STR_PAD_LEFT); ?>: <?= htmlspecialchars($bd['crop_name'] . ' (' . $bd['variety'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?= ($statusFilter === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending Decision</option>
                        <option value="approved" <?= ($statusFilter === 'approved') ? 'selected' : ''; ?>>Approved Contracts</option>
                        <option value="rejected" <?= ($statusFilter === 'rejected') ? 'selected' : ''; ?>>Rejected Applications</option>
                    </select>
                </div>

                <div>
                    <a href="manage_bids.php" class="btn btn-secondary">Reset Filters</a>
                </div>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <?php if (empty($bids)): ?>
                <div style="text-align: center; padding: 4rem 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📬</div>
                    <h3>No Farmer Applications Found</h3>
                    <p class="text-muted" style="max-width: 480px; margin: 0 auto 1.5rem auto;">
                        <?= ($statusFilter !== 'all' || $demandFilter > 0) ? 'No bids match your selected filter parameters.' : 'You have not received any farmer applications on your tenders yet.'; ?>
                    </p>
                    <a href="post_contract.php" class="btn btn-primary">Publish New Procurement Tender</a>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="border: none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bid ID</th>
                                <th>Farmer Profile & Location</th>
                                <th>Target Tender</th>
                                <th>Proposed Volume</th>
                                <th>Contract Price</th>
                                <th>Total Outlay</th>
                                <th>Status</th>
                                <th>Applied At</th>
                                <th style="text-align: right;">Decision Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bids as $b): 
                                $total = $b['proposed_quantity'] * $b['offered_price_per_quintal'];
                            ?>
                                <tr>
                                    <td><strong class="font-mono">#BID-<?= str_pad($b['bid_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($b['farmer_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);">
                                            📞 <?= htmlspecialchars($b['farmer_phone'], ENT_QUOTES, 'UTF-8'); ?><br>
                                            🌾 <?= htmlspecialchars($b['land_acres'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?> Ac (<?= htmlspecialchars($b['soil_type'] ?? 'Loam', ENT_QUOTES, 'UTF-8'); ?>)<br>
                                            📍 <?= htmlspecialchars(($b['district'] ?? '') . ', ' . ($b['state'] ?? 'India'), ENT_QUOTES, 'UTF-8'); ?>
                                            (Survey: <?= htmlspecialchars($b['survey_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>)
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($b['crop_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);"><?= htmlspecialchars($b['variety'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td><strong><?= (int)$b['proposed_quantity']; ?></strong> Quintals</td>
                                    <td><?= formatINR($b['offered_price_per_quintal']); ?></td>
                                    <td style="font-weight: 700; color: var(--primary);"><?= formatINR($total); ?></td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($b['bid_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= ucfirst(htmlspecialchars($b['bid_status'], ENT_QUOTES, 'UTF-8')); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= date('d M Y, h:i A', strtotime($b['applied_at'])); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; gap: 0.35rem; justify-content: flex-end;">
                                            <?php if ($b['bid_status'] !== 'approved'): ?>
                                                <form action="manage_bids.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="bid_id" value="<?= (int)$b['bid_id']; ?>">
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="btn btn-success btn-sm" title="Approve Contract">Approve</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($b['bid_status'] !== 'rejected'): ?>
                                                <form action="manage_bids.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="bid_id" value="<?= (int)$b['bid_id']; ?>">
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Reject Application" onclick="return confirm('Are you sure you want to reject this farmer bid?');">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php if (!empty($b['bid_notes'])): ?>
                                    <tr style="background: #fafafa;">
                                        <td colspan="9" style="padding: 0.5rem 1.15rem; font-size: 0.8rem; color: var(--text-muted);">
                                            💬 <strong>Farmer's Agronomic Dispatch Notes:</strong> <?= htmlspecialchars($b['bid_notes'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
