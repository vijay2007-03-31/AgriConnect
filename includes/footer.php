<?php
/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Shared Global Footer & Asset Ingestion
 */
$inSubdir = (strpos($_SERVER['SCRIPT_NAME'], '/farmer/') !== false || 
             strpos($_SERVER['SCRIPT_NAME'], '/buyer/') !== false || 
             strpos($_SERVER['SCRIPT_NAME'], '/auth/') !== false);
$base = $inSubdir ? '../' : './';
?>
    </main>

    <!-- Global Agritech Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="brand-logo" style="color: #ffffff; margin-bottom: 1rem;">
                        <div class="brand-icon">🌱</div>
                        <span>AgriConnect</span>
                    </div>
                    <p style="color: #94a3b8; max-width: 340px; margin-bottom: 1.5rem;">
                        Empowering rural cultivators through guaranteed price contracts, eliminating exploitative intermediaries, and bringing institutional transparency to agricultural supply chains.
                    </p>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <span class="badge" style="background: rgba(255,255,255,0.1); color: #4ade80;">Stage 1 & 2 Evaluated</span>
                        <span class="badge" style="background: rgba(255,255,255,0.1); color: #38bdf8;">PHP 8 + MySQL 3NF</span>
                    </div>
                </div>

                <div>
                    <h4 class="footer-heading">Platform Links</h4>
                    <ul class="footer-links">
                        <li><a href="<?= $base ?>public/index.html">Platform Overview</a></li>
                        <li><a href="<?= $base ?>public/contracts.html">Procurement Tenders</a></li>
                        <li><a href="<?= $base ?>public/estimator.html">Yield & Revenue Calculator</a></li>
                        <li><a href="<?= $base ?>public/register.html">Stage 1 Registration</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="footer-heading">User Portals</h4>
                    <ul class="footer-links">
                        <li><a href="<?= $base ?>auth/login.php">Portal Sign In</a></li>
                        <li><a href="<?= $base ?>auth/register.php">Create Account</a></li>
                        <li><a href="<?= $base ?>farmer/dashboard.php">Farmer Workspace</a></li>
                        <li><a href="<?= $base ?>buyer/dashboard.php">Agribusiness Tender Desk</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="footer-heading">Academic Information</h4>
                    <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.75rem;">
                        Developed as a production-grade Capstone Project following IEEE & ISO Web Engineering standards.
                    </p>
                    <ul class="footer-links" style="font-size: 0.8rem;">
                        <li><strong>Server:</strong> Apache / PHP 8.2+ PDO</li>
                        <li><strong>Database:</strong> MariaDB / MySQL 3NF</li>
                        <li><strong>Security:</strong> BCRYPT, XSS Sanitized, CSRF Protected</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div>
                    &copy; <?= date('Y'); ?> AgriConnect Portal. All agricultural rights reserved. Built for Academic Review & Evaluation.
                </div>
                <div style="display: flex; gap: 1.5rem;">
                    <a href="<?= $base ?>docs/Review_01_Presentation.md" style="color: #64748b;">Stage 1 Review Deck</a>
                    <a href="<?= $base ?>docs/Review_02_Presentation.md" style="color: #64748b;">Stage 2 Review Deck</a>
                    <a href="<?= $base ?>docs/Viva_Preparation_Guide.md" style="color: #64748b;">Viva Guide</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Mobile Drawer & Global Interaction Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('mobileToggle');
            const menu = document.getElementById('navMenu');
            if (toggle && menu) {
                toggle.addEventListener('click', () => {
                    menu.classList.toggle('is-active');
                });
            }
        });
    </script>
    <script src="<?= $base ?>js/validation.js"></script>
</body>
</html>
