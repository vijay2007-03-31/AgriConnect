<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Buyer Portal: dashboard.php (Agribusiness Enterprise Command Center)
 *
 * Provides real-time metrics on published procurement tenders, aggregate
 * commodity volume targets, received farmer bids, and fast-track approvals.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('buyer');

$buyerId = (int)$_SESSION['user_id'];

// 1. Ingest Corporate Buyer Summary KPIs
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT cd.id) AS total_tenders,
        SUM(CASE WHEN cd.status = 'open' THEN 1 ELSE 0 END) AS active_tenders,
        COUNT(c.id) AS total_bids_received,
        SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) AS pending_bids,
        SUM(CASE WHEN c.status = 'approved' THEN c.proposed_quantity ELSE 0 END) AS approved_volume_qtl,
        SUM(CASE WHEN c.status = 'approved' THEN (c.proposed_quantity * cd.offered_price_per_quintal) ELSE 0 END) AS committed_capital
    FROM crop_demands cd
    LEFT JOIN contracts c ON cd.id = c.demand_id
    WHERE cd.buyer_id = :bid
");
$stmtStats->execute([':bid' => $buyerId]);
$stats = $stmtStats->fetch() ?: [
    'total_tenders'       => 0,
    'active_tenders'      => 0,
    'total_bids_received' => 0,
    'pending_bids'        => 0,
    'approved_volume_qtl' => 0,
    'committed_capital'   => 0
];

// 2. Fetch Active Published Demands
$stmtDemands = $pdo->prepare("
    SELECT 
        cd.id,
        cd.crop_name,
        cd.variety,
        cd.required_quintals,
        cd.offered_price_per_quintal,
        cd.delivery_location,
        cd.deadline,
        cd.status,
        cd.created_at,
        COUNT(c.id) AS bid_count,
        SUM(CASE WHEN c.status = 'approved' THEN c.proposed_quantity ELSE 0 END) AS fulfilled_quantity
    FROM crop_demands cd
    LEFT JOIN contracts c ON cd.id = c.demand_id
    WHERE cd.buyer_id = :bid
    GROUP BY cd.id
    ORDER BY cd.created_at DESC
    LIMIT 5
");
$stmtDemands->execute([':bid' => $buyerId]);
$myDemands = $stmtDemands->fetchAll();

// 3. Fetch Recent Farmer Applications Awaiting Review
$stmtPendingBids = $pdo->prepare("
    SELECT 
        c.id AS bid_id,
        c.proposed_quantity,
        c.bid_notes,
        c.status AS bid_status,
        c.applied_at,
        cd.crop_name,
        cd.variety,
        cd.offered_price_per_quintal,
        u_farmer.name AS farmer_name,
        u_farmer.phone AS farmer_phone,
        fp.land_acres,
        fp.district,
        fp.state
    FROM contracts c
    JOIN crop_demands cd ON c.demand_id = cd.id
    JOIN users u_farmer ON c.farmer_id = u_farmer.id
    LEFT JOIN farmer_profiles fp ON u_farmer.id = fp.user_id
    WHERE cd.buyer_id = :bid AND c.status = 'pending'
    ORDER BY c.applied_at DESC
    LIMIT 5
");
$stmtPendingBids->execute([':bid' => $buyerId]);
$pendingBids = $stmtPendingBids->fetchAll();

$pageTitle = 'Buyer Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="container">
        <!-- Top Workspace Bar -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <span class="badge badge-role-buyer">Corporate Agribusiness Portal</span>
                    <span class="text-muted" style="font-size: 0.85rem;">Authenticated Enterprise Desk</span>
                </div>
                <h1><?= htmlspecialchars($_SESSION['name'] ?? 'Procurement Desk', ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="text-muted">
                    Manage direct farmer contract procurement, publish crop tonnage demands, and approve verified harvest applications.
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="post_contract.php" class="btn btn-primary">+ Post New Crop Demand</a>
                <a href="manage_bids.php" class="btn btn-secondary">Manage All Bids</a>
            </div>
        </div>

        <!-- 4 Summary KPI Cards -->
        <div class="grid grid-4" style="margin-bottom: 2.5rem;">
            <div class="stat-card stat-success">
                <div>
                    <div class="stat-label">Committed Procurement</div>
                    <div class="stat-value" style="font-size: 1.55rem; color: var(--primary);">
                        <?= formatINR($stats['committed_capital'] ?? 0); ?>
                    </div>
                    <div class="stat-subtext"><?= number_format((float)($stats['approved_volume_qtl'] ?? 0)); ?> Qtl secured</div>
                </div>
                <div class="stat-icon">💰</div>
            </div>

            <div class="stat-card">
                <div>
                    <div class="stat-label">Published Tenders</div>
                    <div class="stat-value"><?= (int)($stats['total_tenders'] ?? 0); ?></div>
                    <div class="stat-subtext"><?= (int)($stats['active_tenders'] ?? 0); ?> Open for bidding</div>
                </div>
                <div class="stat-icon">📋</div>
            </div>

            <div class="stat-card stat-accent">
                <div>
                    <div class="stat-label">Applications Received</div>
                    <div class="stat-value"><?= (int)($stats['total_bids_received'] ?? 0); ?></div>
                    <div class="stat-subtext"><?= (int)($stats['pending_bids'] ?? 0); ?> Pending decision</div>
                </div>
                <div class="stat-icon">📥</div>
            </div>

            <div class="stat-card stat-warning">
                <div>
                    <div class="stat-label">Pending Action</div>
                    <div class="stat-value"><?= (int)($stats['pending_bids'] ?? 0); ?></div>
                    <div class="stat-subtext">Awaiting your approval</div>
                </div>
                <div class="stat-icon">⚡</div>
            </div>
        </div>

        <!-- Section 1: Pending Applications Action Desk -->
        <?php if (!empty($pendingBids)): ?>
            <div class="card" style="margin-bottom: 2.5rem; border-left: 4px solid var(--accent);">
                <div class="card-header">
                    <div>
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <span>⏳</span>
                            <span>Pending Farmer Applications Awaiting Review</span>
                        </h3>
                        <span class="text-muted" style="font-size: 0.85rem;">Action required: Review farmer profile and approve or reject</span>
                    </div>
                    <a href="manage_bids.php?status=pending" class="btn btn-outline btn-sm">Open Review Desk &rarr;</a>
                </div>

                <div class="table-responsive" style="border: none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Farmer Name</th>
                                <th>Land Size</th>
                                <th>Target Tender</th>
                                <th>Committed Qtl</th>
                                <th>Offered Price</th>
                                <th>Contract Total</th>
                                <th>Applied Date</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingBids as $pb): 
                                $total = $pb['proposed_quantity'] * $pb['offered_price_per_quintal'];
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($pb['farmer_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);">
                                            📞 <?= htmlspecialchars($pb['farmer_phone'], ENT_QUOTES, 'UTF-8'); ?> | <?= htmlspecialchars(($pb['district'] ?? '') . ', ' . ($pb['state'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
                                    <td><strong><?= htmlspecialchars($pb['land_acres'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></strong> Ac</td>
                                    <td>
                                        <strong><?= htmlspecialchars($pb['crop_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($pb['variety'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td><strong><?= (int)$pb['proposed_quantity']; ?></strong> Qtl</td>
                                    <td><?= formatINR($pb['offered_price_per_quintal']); ?></td>
                                    <td style="font-weight: 700; color: var(--primary);"><?= formatINR($total); ?></td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= date('d M, h:i A', strtotime($pb['applied_at'])); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                            <form action="manage_bids.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="bid_id" value="<?= (int)$pb['bid_id']; ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                            </form>
                                            <form action="manage_bids.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="bid_id" value="<?= (int)$pb['bid_id']; ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reject this bid?');">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section 2: Published Tenders Summary -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 style="margin: 0;">My Published Procurement Tenders</h3>
                    <span class="text-muted" style="font-size: 0.85rem;">Active market demands open to regional farmers</span>
                </div>
                <a href="post_contract.php" class="btn btn-primary btn-sm">+ Post New Demand</a>
            </div>

            <?php if (empty($myDemands)): ?>
                <div style="text-align: center; padding: 3.5rem 1rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">📋</div>
                    <h4>No Procurement Tenders Published</h4>
                    <p class="text-muted" style="max-width: 450px; margin: 0 auto 1.5rem auto;">
                        Publish your crop procurement specifications (volume, target variety, price, and delivery terminal) to receive direct farmer bids.
                    </p>
                    <a href="post_contract.php" class="btn btn-primary">Post First Tender Now</a>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="border: none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tender ID</th>
                                <th>Crop & Variety</th>
                                <th>Target Volume</th>
                                <th>Offered Price</th>
                                <th>Delivery Location</th>
                                <th>Applications</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myDemands as $d): ?>
                                <tr>
                                    <td><strong class="font-mono">#TND-<?= str_pad($d['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($d['crop_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <div style="font-size: 0.775rem; color: var(--text-muted);"><?= htmlspecialchars($d['variety'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td>
                                        <strong><?= (int)$d['required_quintals']; ?></strong> Qtl
                                        <div style="font-size: 0.75rem; color: var(--success); font-weight: 600;">
                                            <?= (int)$d['fulfilled_quantity']; ?> Qtl Approved
                                        </div>
                                    </td>
                                    <td><strong><?= formatINR($d['offered_price_per_quintal']); ?></strong> / Qtl</td>
                                    <td style="font-size: 0.825rem; color: var(--text-muted);"><?= htmlspecialchars($d['delivery_location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge" style="background: var(--bg-alt); color: var(--text);">
                                            <?= (int)$d['bid_count']; ?> Bids
                                        </span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?= date('d M Y', strtotime($d['deadline'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($d['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= ucfirst(htmlspecialchars($d['status'], ENT_QUOTES, 'UTF-8')); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="manage_bids.php?demand_id=<?= (int)$d['id']; ?>" class="btn btn-outline btn-sm">
                                            View Bids (<?= (int)$d['bid_count']; ?>)
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
