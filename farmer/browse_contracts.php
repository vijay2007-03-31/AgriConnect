<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Farmer Portal: browse_contracts.php (Real-Time Procurement Tenders & Bidding)
 *
 * Pulls active crop demands from database, shows farmer's existing application status,
 * and handles modal-based pre-harvest contract bid submissions.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('farmer');

$farmerId = (int)$_SESSION['user_id'];
$errors = [];

// Handle Bid Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_bid') {
    $demandId = (int)($_POST['demand_id'] ?? 0);
    $proposedQuantity = (int)($_POST['proposed_quantity'] ?? 0);
    $bidNotes = trim($_POST['bid_notes'] ?? '');

    if ($demandId <= 0) {
        $errors[] = 'Invalid procurement tender selected.';
    }
    if ($proposedQuantity <= 0) {
        $errors[] = 'Proposed quantity must be a positive number of quintals.';
    }

    // Verify demand exists and is open
    if (empty($errors)) {
        $stmtCheckDemand = $pdo->prepare("SELECT id, crop_name, variety, buyer_id FROM crop_demands WHERE id = :id AND status = 'open' LIMIT 1");
        $stmtCheckDemand->execute([':id' => $demandId]);
        $demand = $stmtCheckDemand->fetch();

        if (!$demand) {
            $errors[] = 'This procurement tender is no longer accepting applications.';
        } else {
            // Check if this farmer already has an active bid on this demand
            $stmtCheckBid = $pdo->prepare("SELECT id FROM contracts WHERE demand_id = :did AND farmer_id = :fid LIMIT 1");
            $stmtCheckBid->execute([':did' => $demandId, ':fid' => $farmerId]);
            if ($stmtCheckBid->fetch()) {
                $errors[] = 'You have already submitted a bid for this contract tender. Check "My Bids" to track status.';
            } else {
                // Insert contract bid
                $stmtInsert = $pdo->prepare("
                    INSERT INTO contracts (demand_id, farmer_id, proposed_quantity, bid_notes, status)
                    VALUES (:did, :fid, :qty, :notes, 'pending')
                ");
                $stmtInsert->execute([
                    ':did'   => $demandId,
                    ':fid'   => $farmerId,
                    ':qty'   => $proposedQuantity,
                    ':notes' => $bidNotes
                ]);

                setFlashMessage('success', "Your bid for {$proposedQuantity} Quintals of {$demand['crop_name']} ({$demand['variety']}) has been successfully submitted to the buyer!");
                header("Location: my_bids.php");
                exit;
            }
        }
    }
}

// Fetch all open demands and left-join with existing bids from this farmer
$stmtTenders = $pdo->prepare("
    SELECT 
        cd.id,
        cd.crop_name,
        cd.variety,
        cd.required_quintals,
        cd.offered_price_per_quintal,
        cd.delivery_location,
        cd.deadline,
        cd.created_at,
        u.name AS buyer_name,
        u.email AS buyer_email,
        u.phone AS buyer_phone,
        c.id AS existing_bid_id,
        c.status AS existing_bid_status,
        c.proposed_quantity AS existing_bid_quantity
    FROM crop_demands cd
    JOIN users u ON cd.buyer_id = u.id
    LEFT JOIN contracts c ON cd.id = c.demand_id AND c.farmer_id = :fid
    WHERE cd.status = 'open'
    ORDER BY cd.deadline ASC
");
$stmtTenders->execute([':fid' => $farmerId]);
$tenders = $stmtTenders->fetchAll();

$pageTitle = 'Browse Procurement Contracts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="container">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="crop-tag">Live Procurement Tenders</span>
                <h1 style="margin-top: 0.5rem; margin-bottom: 0.5rem;">Corporate Contract Demands</h1>
                <p class="text-muted">
                    Review specifications published by verified agribusiness buyers. Apply for partial or full tonnage commitments.
                </p>
            </div>
            <a href="my_bids.php" class="btn btn-secondary">Track My Submitted Applications &rarr;</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <div>
                    <strong>Unable to Submit Bid:</strong>
                    <ul style="margin: 0.25rem 0 0 1.25rem;">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Bar -->
        <div class="card" style="margin-bottom: 2rem; padding: 1.25rem;">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: center;">
                <input type="text" id="contract-search" class="form-control" placeholder="🔍 Search crop name, variety, buyer company, or state...">
                <select id="contract-category" class="form-control">
                    <option value="all">All Crops</option>
                    <option value="wheat">Wheat</option>
                    <option value="rice">Rice / Basmati</option>
                    <option value="cotton">Cotton</option>
                    <option value="soybean">Soybean</option>
                    <option value="coconut">Coconut</option>
                    <option value="onion">Onion</option>
                </select>
                <select id="contract-sort" class="form-control">
                    <option value="default">Sort by: Deadline</option>
                    <option value="price-desc">Price: High to Low</option>
                    <option value="price-asc">Price: Low to High</option>
                    <option value="qty-desc">Required Volume: High to Low</option>
                </select>
                <button type="button" class="btn btn-secondary" id="filter-reset-btn">Reset</button>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border); font-size: 0.85rem;">
                <span class="badge badge-open" id="contract-count-badge">Showing <?= count($tenders); ?> Active Contracts</span>
                <span class="text-muted">Direct database synchronisation</span>
            </div>
        </div>

        <!-- Tenders Grid -->
        <div class="grid grid-3" id="contracts-grid">
            <?php foreach ($tenders as $tender): ?>
                <div class="contract-card"
                     data-crop="<?= strtolower(htmlspecialchars($tender['crop_name'], ENT_QUOTES, 'UTF-8')); ?>"
                     data-location="<?= strtolower(htmlspecialchars($tender['delivery_location'], ENT_QUOTES, 'UTF-8')); ?>"
                     data-buyer="<?= strtolower(htmlspecialchars($tender['buyer_name'], ENT_QUOTES, 'UTF-8')); ?>"
                     data-price="<?= (float)$tender['offered_price_per_quintal']; ?>"
                     data-quantity="<?= (int)$tender['required_quintals']; ?>">
                    <div>
                        <div class="contract-header">
                            <div>
                                <span class="crop-tag"><?= htmlspecialchars($tender['crop_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <h3 class="crop-title" style="margin-top: 0.35rem;"><?= htmlspecialchars($tender['crop_name'] . ' (' . $tender['variety'] . ')', ENT_QUOTES, 'UTF-8'); ?></h3>
                            </div>
                            <?php if ($tender['existing_bid_id']): ?>
                                <span class="badge badge-<?= htmlspecialchars($tender['existing_bid_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                    Applied: <?= ucfirst(htmlspecialchars($tender['existing_bid_status'], ENT_QUOTES, 'UTF-8')); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-open">Open</span>
                            <?php endif; ?>
                        </div>

                        <div class="contract-price">
                            <?= formatINR($tender['offered_price_per_quintal']); ?>
                            <span>/ Quintal</span>
                        </div>

                        <div class="contract-meta">
                            <div class="contract-meta-item">
                                <span class="meta-label">Total Demand</span>
                                <span class="meta-value"><?= (int)$tender['required_quintals']; ?> Quintals</span>
                            </div>
                            <div class="contract-meta-item">
                                <span class="meta-label">Corporate Buyer</span>
                                <span class="meta-value meta-buyer"><?= htmlspecialchars($tender['buyer_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="contract-meta-item">
                                <span class="meta-label">Delivery Hub</span>
                                <span class="meta-value meta-location"><?= htmlspecialchars($tender['delivery_location'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="contract-meta-item">
                                <span class="meta-label">Tender Deadline</span>
                                <span class="meta-value"><?= date('d M Y', strtotime($tender['deadline'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($tender['existing_bid_id']): ?>
                        <div style="margin-top: 1rem;">
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                                You committed: <strong><?= (int)$tender['existing_bid_quantity']; ?> Quintals</strong>
                            </div>
                            <a href="my_bids.php" class="btn btn-secondary btn-block btn-sm">View Status in My Bids</a>
                        </div>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary btn-block btn-open-bid-modal"
                                data-id="<?= (int)$tender['id']; ?>"
                                data-crop="<?= htmlspecialchars($tender['crop_name'] . ' (' . $tender['variety'] . ')', ENT_QUOTES, 'UTF-8'); ?>"
                                data-buyer="<?= htmlspecialchars($tender['buyer_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-price="<?= htmlspecialchars(formatINR($tender['offered_price_per_quintal']), ENT_QUOTES, 'UTF-8'); ?>"
                                data-max="<?= (int)$tender['required_quintals']; ?>">
                            Submit Pre-Harvest Bid
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="contracts-empty-state" style="display: none; text-align: center; padding: 4rem 1rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🌾</div>
            <h3>No Active Contracts Match Filter</h3>
            <p class="text-muted">Try resetting your keywords or category filter.</p>
        </div>
    </div>
</div>

<!-- Modal Dialog: Submit Contract Bid -->
<div class="modal-backdrop" id="farmerBidModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3 id="modalCropName" style="margin: 0; font-size: 1.25rem;">Submit Tender Bid</h3>
                <span id="modalBuyerName" style="font-size: 0.825rem; color: var(--text-muted);">Buyer Company</span>
            </div>
            <button type="button" class="modal-close" id="modalClose">&times;</button>
        </div>
        <form action="browse_contracts.php" method="POST" id="bidForm" data-validate="true">
            <input type="hidden" name="action" value="submit_bid">
            <input type="hidden" name="demand_id" id="modalDemandId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Offered Contract Price</label>
                    <input type="text" id="modalOfferedPrice" class="form-control" readonly style="background: var(--bg-alt); font-weight: 700; color: var(--primary);">
                </div>

                <div class="form-group">
                    <label class="form-label" for="proposed_quantity">Proposed Commitment Quantity (Quintals) <span class="required">*</span></label>
                    <input type="number" id="proposed_quantity" name="proposed_quantity" class="form-control" placeholder="e.g. 200" min="5" required>
                    <span class="input-hint" id="modalMaxHint">Maximum tender volume: 500 Quintals</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bid_notes">Quality & Harvest Dispatch Notes</label>
                    <textarea id="bid_notes" name="bid_notes" class="form-control" rows="3" placeholder="State your expected harvesting week, moisture control measures, or certification details..."></textarea>
                </div>

                <div class="alert alert-info" style="font-size: 0.8rem; margin-bottom: 0;">
                    <span>📜</span>
                    <div>By submitting this bid, you express interest in locking harvest procurement under standardized legal quality grading terms.</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modalCancel">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm & Submit Bid</button>
            </div>
        </form>
    </div>
</div>

<!-- Filter & Modal Scripts -->
<script src="../js/filter.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('farmerBidModal');
    const closeBtn = document.getElementById('modalClose');
    const cancelBtn = document.getElementById('modalCancel');
    const modalCrop = document.getElementById('modalCropName');
    const modalBuyer = document.getElementById('modalBuyerName');
    const modalPrice = document.getElementById('modalOfferedPrice');
    const modalDemandId = document.getElementById('modalDemandId');
    const modalMaxHint = document.getElementById('modalMaxHint');
    const qtyInput = document.getElementById('proposed_quantity');

    document.querySelectorAll('.btn-open-bid-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            modalCrop.textContent = `Apply for ${btn.dataset.crop}`;
            modalBuyer.textContent = `Corporate Buyer: ${btn.dataset.buyer}`;
            modalPrice.value = `${btn.dataset.price} / Quintal`;
            modalDemandId.value = btn.dataset.id;
            modalMaxHint.textContent = `Target Tender Demand: ${btn.dataset.max} Quintals`;
            qtyInput.max = btn.dataset.max;
            qtyInput.value = '';
            modal.classList.add('is-open');
        });
    });

    const hideModal = () => modal.classList.remove('is-open');
    if (closeBtn) closeBtn.addEventListener('click', hideModal);
    if (cancelBtn) cancelBtn.addEventListener('click', hideModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) hideModal();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
