<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Shared Global Navigation Header & Authenticated Layout
 */

if (!defined('AGRICONNECT_APP')) {
    define('AGRICONNECT_APP', true);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

// Dynamically determine relative root prefix based on script directory depth
$inSubdir = (strpos($_SERVER['SCRIPT_NAME'], '/farmer/') !== false || 
             strpos($_SERVER['SCRIPT_NAME'], '/buyer/') !== false || 
             strpos($_SERVER['SCRIPT_NAME'], '/auth/') !== false);
$base = $inSubdir ? '../' : './';

$user = currentUser();
$flash = getFlashMessage();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AgriConnect — Enterprise Agritech Portal connecting Indian cultivators with corporate agribusinesses through assured contract farming and fair price discovery.">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' — AgriConnect' : 'AgriConnect — Smart Contract Farming & Agritech Management' ?></title>
    
    <!-- Modern Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Unified CSS Architecture -->
    <link rel="stylesheet" href="<?= $base ?>css/styles.css">
</head>
<body>

    <!-- 1. Government MSP Live Ticker Preview -->
    <div class="msp-ticker-wrapper">
        <div class="container-wide msp-ticker-container">
            <span class="msp-badge">Official MSP 2026</span>
            <div class="msp-ticker-track">
                <div class="msp-item"><span class="crop-title">🌾 Wheat (Grade A):</span> <span class="price">₹2,450 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🍚 Basmati Paddy:</span> <span class="price">₹4,150 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🥥 Pollachi Coconut:</span> <span class="price">₹3,850 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🧅 Nashik Red Onion:</span> <span class="price">₹2,600 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🌱 Shankar-6 Cotton:</span> <span class="price">₹7,200 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🫘 Yellow Soybean:</span> <span class="price">₹4,800 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🌽 Hybrid Maize:</span> <span class="price">₹2,090 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🌼 Mustard Seed:</span> <span class="price">₹5,650 / Qtl</span></div>
                <!-- Duplicate for seamless infinite marquee loop -->
                <div class="msp-item"><span class="crop-title">🌾 Wheat (Grade A):</span> <span class="price">₹2,450 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🥥 Pollachi Coconut:</span> <span class="price">₹3,850 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🧅 Nashik Red Onion:</span> <span class="price">₹2,600 / Qtl</span></div>
            </div>
        </div>
    </div>

    <!-- 2. Sticky Glassmorphic Navbar -->
    <header class="site-header">
        <div class="container navbar">
            <a href="<?= $base ?>public/index.html" class="brand-logo" title="AgriConnect Platform">
                <div class="brand-icon">🌱</div>
                <span>AgriConnect</span>
                <span class="brand-tag">v2.0 PHP</span>
            </a>

            <!-- Desktop & Mobile Navigation Menu -->
            <ul class="nav-menu" id="navMenu">
                <?php if (!$user): ?>
                    <li><a href="<?= $base ?>public/index.html" class="nav-link <?= ($currentScript === 'index.html') ? 'active' : '' ?>">Home</a></li>
                    <li><a href="<?= $base ?>public/contracts.html" class="nav-link <?= ($currentScript === 'contracts.html') ? 'active' : '' ?>">Tenders</a></li>
                    <li><a href="<?= $base ?>public/estimator.html" class="nav-link <?= ($currentScript === 'estimator.html') ? 'active' : '' ?>">Yield & Profit Calculator</a></li>
                <?php elseif ($user['role'] === 'farmer'): ?>
                    <li><a href="<?= $base ?>farmer/dashboard.php" class="nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">Farmer Dashboard</a></li>
                    <li><a href="<?= $base ?>farmer/browse_contracts.php" class="nav-link <?= ($currentScript === 'browse_contracts.php') ? 'active' : '' ?>">Browse Tenders</a></li>
                    <li><a href="<?= $base ?>farmer/my_bids.php" class="nav-link <?= ($currentScript === 'my_bids.php') ? 'active' : '' ?>">My Contract Bids</a></li>
                    <li><a href="<?= $base ?>public/estimator.html" class="nav-link">Yield Calculator</a></li>
                <?php elseif ($user['role'] === 'buyer' || $user['role'] === 'admin'): ?>
                    <li><a href="<?= $base ?>buyer/dashboard.php" class="nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">Buyer Dashboard</a></li>
                    <li><a href="<?= $base ?>buyer/post_contract.php" class="nav-link <?= ($currentScript === 'post_contract.php') ? 'active' : '' ?>">Post New Tender</a></li>
                    <li><a href="<?= $base ?>buyer/manage_bids.php" class="nav-link <?= ($currentScript === 'manage_bids.php') ? 'active' : '' ?>">Manage Farmer Bids</a></li>
                    <li><a href="<?= $base ?>public/estimator.html" class="nav-link">Estimator</a></li>
                <?php endif; ?>

                <!-- Mobile Action Links -->
                <li class="nav-actions-mobile">
                    <?php if (!$user): ?>
                        <a href="<?= $base ?>auth/login.php" class="btn btn-secondary btn-block">Sign In</a>
                        <a href="<?= $base ?>auth/register.php" class="btn btn-primary btn-block">Join Portal</a>
                    <?php else: ?>
                        <a href="<?= $base ?>auth/logout.php" class="btn btn-danger btn-block">Sign Out (<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>)</a>
                    <?php endif; ?>
                </li>
            </ul>

            <!-- Header Action Controls -->
            <div class="nav-actions">
                <?php if (!$user): ?>
                    <a href="<?= $base ?>auth/login.php" class="btn btn-secondary btn-sm">Sign In</a>
                    <a href="<?= $base ?>auth/register.php" class="btn btn-primary btn-sm">Join AgriConnect</a>
                <?php else: ?>
                    <div class="user-badge">
                        <span class="badge badge-role-<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= ucfirst(htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8')); ?>
                        </span>
                        <strong><?= htmlspecialchars(explode(' ', $user['name'])[0], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <a href="<?= $base ?>auth/logout.php" class="btn btn-outline btn-sm">Sign Out</a>
                <?php endif; ?>
            </div>

            <!-- Hamburger Button for Mobile Drawer -->
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Navigation Menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
    </header>

    <!-- Main Container Content Wrapper -->
    <main>
        <?php if ($flash): ?>
            <div class="container" style="padding-top: 1.5rem;">
                <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?= ($flash['type'] === 'success') ? '✅' : (($flash['type'] === 'danger') ? '❌' : 'ℹ️'); ?></span>
                    <div><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        <?php endif; ?>
