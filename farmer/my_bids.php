<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Farmer Portal: my_bids.php (Contract Applications & Status Lifecycle Tracker)
 *
 * Provides real-time visibility into submitted farmer tenders, approval badges,
 * buyer contact details, and guaranteed contract payouts.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('farmer');

$farmerId = (int)$_SESSION['user_id'];
$statusFilter = trim($_GET['status'] ?? 'all');

// Build query with optional status filtering
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
        cd.deadline,
        u_buyer.name AS buyer_name,
        u_buyer.phone AS buyer_phone,
        u_buyer.email AS buyer_email
    FROM contracts c
    JOIN crop_demands cd ON c.demand_id = cd.id
    JOIN users u_buyer ON cd.buyer_id = u_buyer.id
    WHERE c.farmer_id = :fid
";

$params = [':fid' => $farmerId];

if (in_array($statusFilter, ['pending', 'approved', 'rejected', 'fulfilled'])) {
    $sql .= " AND c.status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY c.applied_at DESC";

$stmtBids = $pdo->prepare($sql);
$stmtBids->execute($params);
$bids = $stmtBids->fetchAll();

// Aggregation summary stats
$stmtSummary = $pdo->prepare("
    SELECT 
        COUNT(c.id) AS total_count,
        SUM(CASE WHEN c.status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN c.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN c.status = 'approved' THEN (c.proposed_quantity * cd.offered_price_per_quintal) ELSE 0 END) AS approved_sum
    FROM contracts c
    JOIN crop_demands cd ON c.demand_id = cd.id
    WHERE c.farmer_id = :fid
");
$stmtSummary->execute([':fid' => $farmerId]);
$summary = $stmtSummary->fetch() ?: [
    'total_count'    => 0,
    'approved_count' => 0,
    'pending_count'  => 0,
    'rejected_count' => 0,
    'approved_sum'   => 0
];

$pageTitle = 'My Contract Bids';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="container">
        <!-- Page Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="crop-tag">Application Tracker</span>
                <h1 style="margin-top: 0.5rem; margin-bottom: 0.5rem;">My Submitted Contract Bids</h1>
                <p class="text-muted">
                    Monitor approval lifecycle, contractual commitments, and corporate buyer contact information.
                </p>
            </div>
            <a href="browse_contracts.php" class="btn btn-primary">+ Apply for New Tenders</a>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-4" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Applications</div>
                    <div class="stat-value"><?= (int)$summary['total_count']; ?></div>
                    <div class="stat-subtext">Lifetime submitted bids</div>
                </div>
                <div class="stat-icon">📄</div>
            </div>

            <div class="stat-card stat-success">
                <div>
                    <div class="stat-label">Approved Value</div>
                    <div class="stat-value" style="font-size: 1.55rem; color: var(--primary);">
                        <?= formatINR($summary['approved_sum']); ?>
                    </div>
                    <div class="stat-subtext"><?= (int)$summary['approved_count']; ?> Approved agreements</div>
                </div>
                <div class="stat-icon">💰</div>
            </div>

            <div class="stat-card stat-accent">
                <div>
                    <div class="stat-label">Under Buyer Review</div>
                    <div class="stat-value"><?= (int)$summary['pending_count']; ?></div>
                    <div class="stat-subtext">Awaiting decision</div>
                </div>
                <div class="stat-icon">⏳</div>
            </div>

            <div class="stat-card stat-warning">
                <div>
                    <div class="stat-label">Unfulfilled / Rejected</div>
                    <div class="stat-value"><?= (int)$summary['rejected_count']; ?></div>
                    <div class="stat-subtext">Over-subscribed volume</div>
                </div>
                <div class="stat-icon">ℹ️</div>
            </div>
        </div>

        <!-- Filter Tab Pills -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <a href="my_bids.php?status=all" class="btn btn-sm <?= ($statusFilter === 'all') ? 'btn-primary' : 'btn-secondary'; ?>">
                All Applications (<?= (int)$summary['total_count']; ?>)
            </a>
            <a href="my_bids.php?status=approved" class="btn btn-sm <?= ($statusFilter === 'approved') ? 'btn-primary' : 'btn-secondary'; ?>">
                Approved (<?= (int)$summary['approved_count']; ?>)
            </a>
            <a href="my_bids.php?status=pending" class="btn btn-sm <?= ($statusFilter === 'pending') ? 'btn-primary' : 'btn-secondary'; ?>">
                Pending Review (<?= (int)$summary['pending_count']; ?>)
            </a>
            <a href="my_bids.php?status=rejected" class="btn btn-sm <?= ($statusFilter === 'rejected') ? 'btn-primary' : 'btn-secondary'; ?>">
                Rejected (<?= (int)$summary['rejected_count']; ?>)
            </a>
        </div>

        <!-- Bids Table -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <?php if (empty($bids)): ?>
                <div style="text-align: center; padding: 4rem 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🌾</div>
                    <h3>No Contract Applications Found</h3>
                    <p class="text-muted" style="max-width: 450px; margin: 0 auto 1.5rem auto;">
                        <?= ($statusFilter !== 'all') ? "No bids found matching the '{$statusFilter}' status." : "You have not submitted any pre-harvest contract bids yet."; ?>
                    </p>
                    <a href="browse_contracts.php" class="btn btn-primary">Browse Active Procurement Tenders</a>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="border: none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bid ID</th>
                                <th>Crop & Variety</th>
                                <th>Agribusiness Buyer</th>
                                <th>Committed Qtl</th>
                                <th>Contract Price</th>
                                <th>Total Revenue</th>
                                <th>Delivery Hub</th>
                                <th>Status</th>
                                <th>Applied On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bids as $b): 
                                $totalPayout = $b['proposed_quantity'] * $b['offered_price_per_quintal'];
                            ?>
                                <tr>
                                    <td><strong class="font-mono">#BID-<?= str_pad($b['bid_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($b['crop_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);"><?= htmlspecialchars($b['variety'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($b['buyer_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);">📞 <?= htmlspecialchars($b['buyer_phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td><strong><?= (int)$b['proposed_quantity']; ?></strong> Qtl</td>
                                    <td><?= formatINR($b['offered_price_per_quintal']); ?></td>
                                    <td style="font-weight: 700; color: var(--primary);"><?= formatINR($totalPayout); ?></td>
                                    <td style="font-size: 0.825rem; color: var(--text-muted);"><?= htmlspecialchars($b['delivery_location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($b['bid_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= ucfirst(htmlspecialchars($b['bid_status'], ENT_QUOTES, 'UTF-8')); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= date('d M Y, h:i A', strtotime($b['applied_at'])); ?>
                                    </td>
                                </tr>
                                <?php if (!empty($b['bid_notes'])): ?>
                                    <tr style="background: #fafafa;">
                                        <td colspan="9" style="padding: 0.5rem 1.15rem; font-size: 0.8rem; color: var(--text-muted);">
                                            💬 <strong>Farmer Notes:</strong> <?= htmlspecialchars($b['bid_notes'], ENT_QUOTES, 'UTF-8'); ?>
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
