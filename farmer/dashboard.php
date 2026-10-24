<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Farmer Portal: dashboard.php (Cultivator Command Center)
 *
 * Displays registered land profile metrics, active procurement bids,
 * guaranteed contract revenue, and real-time open buyer tenders.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('farmer');

$userId = (int)$_SESSION['user_id'];

// 1. Fetch Farmer Profile Metadata
$stmtProfile = $pdo->prepare("
    SELECT u.name, u.email, u.phone, fp.land_acres, fp.soil_type, fp.survey_number, fp.state, fp.district
    FROM users u
    LEFT JOIN farmer_profiles fp ON u.id = fp.user_id
    WHERE u.id = :id
    LIMIT 1
");
$stmtProfile->execute([':id' => $userId]);
$profile = $stmtProfile->fetch();

// 2. Fetch Bid Statistics
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(c.id) AS total_bids,
        SUM(CASE WHEN c.status = 'approved' THEN 1 ELSE 0 END) AS approved_bids,
        SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) AS pending_bids,
        SUM(CASE WHEN c.status = 'approved' THEN (c.proposed_quantity * cd.offered_price_per_quintal) ELSE 0 END) AS guaranteed_value
    FROM contracts c
    JOIN crop_demands cd ON c.demand_id = cd.id
    WHERE c.farmer_id = :id
");
$stmtStats->execute([':id' => $userId]);
$stats = $stmtStats->fetch() ?: [
    'total_bids'       => 0,
    'approved_bids'    => 0,
    'pending_bids'     => 0,
    'guaranteed_value' => 0
];

// 3. Fetch Recent Submitted Bids (Latest 5)
$stmtRecentBids = $pdo->prepare("
    SELECT 
        c.id AS bid_id,
        c.proposed_quantity,
        c.status AS bid_status,
        c.applied_at,
        cd.crop_name,
        cd.variety,
        cd.offered_price_per_quintal,
        u_buyer.name AS buyer_name
    FROM contracts c
    JOIN crop_demands cd ON c.demand_id = cd.id
    JOIN users u_buyer ON cd.buyer_id = u_buyer.id
    WHERE c.farmer_id = :id
    ORDER BY c.applied_at DESC
    LIMIT 5
");
$stmtRecentBids->execute([':id' => $userId]);
$recentBids = $stmtRecentBids->fetchAll();

// 4. Fetch Top Open Demands
$stmtOpenDemands = $pdo->query("
    SELECT 
        cd.id,
        cd.crop_name,
        cd.variety,
        cd.required_quintals,
        cd.offered_price_per_quintal,
        cd.delivery_location,
        cd.deadline,
        u.name AS buyer_name
    FROM crop_demands cd
    JOIN users u ON cd.buyer_id = u.id
    WHERE cd.status = 'open'
    ORDER BY cd.created_at DESC
    LIMIT 3
");
$openDemands = $stmtOpenDemands->fetchAll();

$pageTitle = 'Farmer Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="container">
        <!-- Dashboard Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <span class="badge badge-role-farmer">Farmer Portal</span>
                    <span class="text-muted" style="font-size: 0.85rem;">Survey No: <?= htmlspecialchars($profile['survey_number'] ?? 'Pending', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <h1>Welcome, <?= htmlspecialchars($profile['name'] ?? 'Cultivator', ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="text-muted">
                    Farm Location: <strong><?= htmlspecialchars(($profile['district'] ?? '') . ', ' . ($profile['state'] ?? 'India'), ENT_QUOTES, 'UTF-8'); ?></strong> | 
                    Registered Area: <strong><?= htmlspecialchars($profile['land_acres'] ?? '0', ENT_QUOTES, 'UTF-8'); ?> Acres</strong> (<?= htmlspecialchars($profile['soil_type'] ?? 'Loam', ENT_QUOTES, 'UTF-8'); ?>)
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="browse_contracts.php" class="btn btn-primary">Browse Open Tenders</a>
                <a href="../public/estimator.html" class="btn btn-secondary">Yield Calculator</a>
            </div>
        </div>

        <!-- 4 Core Metric KPI Cards -->
        <div class="grid grid-4" style="margin-bottom: 2.5rem;">
            <div class="stat-card stat-success">
                <div>
                    <div class="stat-label">Guaranteed Revenue</div>
                    <div class="stat-value" style="font-size: 1.6rem; color: var(--primary);">
                        <?= formatINR($stats['guaranteed_value'] ?? 0); ?>
                    </div>
                    <div class="stat-subtext">Approved legal contracts</div>
                </div>
                <div class="stat-icon">💰</div>
            </div>

            <div class="stat-card">
                <div>
                    <div class="stat-label">Registered Land</div>
                    <div class="stat-value"><?= htmlspecialchars($profile['land_acres'] ?? '0', ENT_QUOTES, 'UTF-8'); ?> <span style="font-size: 1rem; font-weight: 500;">Acres</span></div>
                    <div class="stat-subtext"><?= htmlspecialchars($profile['soil_type'] ?? 'Soil Record', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="stat-icon">🌾</div>
            </div>

            <div class="stat-card stat-accent">
                <div>
                    <div class="stat-label">Pending Reviews</div>
                    <div class="stat-value"><?= (int)($stats['pending_bids'] ?? 0); ?></div>
                    <div class="stat-subtext">Awaiting buyer signoff</div>
                </div>
                <div class="stat-icon">⏳</div>
            </div>

            <div class="stat-card stat-warning">
                <div>
                    <div class="stat-label">Approved Contracts</div>
                    <div class="stat-value"><?= (int)($stats['approved_bids'] ?? 0); ?> / <?= (int)($stats['total_bids'] ?? 0); ?></div>
                    <div class="stat-subtext">Active harvest commitments</div>
                </div>
                <div class="stat-icon">📜</div>
            </div>
        </div>

        <div class="grid grid-3" style="gap: 2rem; align-items: start;">
            <!-- Left 2 Cols: Recent Applications Table -->
            <div style="grid-column: span 2;">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 style="margin: 0;">My Recent Contract Applications</h3>
                            <span class="text-muted" style="font-size: 0.85rem;">Status of tenders you have applied for</span>
                        </div>
                        <a href="my_bids.php" class="btn btn-outline btn-sm">View Full History &rarr;</a>
                    </div>

                    <?php if (empty($recentBids)): ?>
                        <div style="text-align: center; padding: 2.5rem 1rem;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📄</div>
                            <h4>No Contract Bids Submitted Yet</h4>
                            <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 1.25rem;">Explore active corporate procurement tenders and submit your first pre-harvest bid.</p>
                            <a href="browse_contracts.php" class="btn btn-primary btn-sm">Browse Tenders Now</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Crop & Variety</th>
                                        <th>Corporate Buyer</th>
                                        <th>Committed Qtl</th>
                                        <th>Offered Price</th>
                                        <th>Contract Value</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBids as $bid): 
                                        $val = $bid['proposed_quantity'] * $bid['offered_price_per_quintal'];
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($bid['crop_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <div style="font-size: 0.775rem; color: var(--text-muted);"><?= htmlspecialchars($bid['variety'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($bid['buyer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><strong><?= (int)$bid['proposed_quantity']; ?></strong> Qtl</td>
                                            <td><?= formatINR($bid['offered_price_per_quintal']); ?></td>
                                            <td style="font-weight: 700; color: var(--primary);"><?= formatINR($val); ?></td>
                                            <td>
                                                <span class="badge badge-<?= htmlspecialchars($bid['bid_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?= ucfirst(htmlspecialchars($bid['bid_status'], ENT_QUOTES, 'UTF-8')); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right 1 Col: Open Demands Spotlight -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 style="margin: 0;">Open Tenders Spotlight</h3>
                        <a href="browse_contracts.php" class="text-muted" style="font-size: 0.825rem;">All &rarr;</a>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($openDemands as $d): ?>
                            <div style="padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--bg-alt);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                                    <strong><?= htmlspecialchars($d['crop_name'] . ' (' . $d['variety'] . ')', ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="badge badge-open">Open</span>
                                </div>
                                <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin: 0.25rem 0;">
                                    <?= formatINR($d['offered_price_per_quintal']); ?> <span style="font-size: 0.75rem; font-weight: 400; color: var(--text-muted);">/ Qtl</span>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                                    Buyer: <?= htmlspecialchars($d['buyer_name'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    Delivery: <?= htmlspecialchars($d['delivery_location'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <a href="browse_contracts.php" class="btn btn-outline btn-sm btn-block">View Details & Apply</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
