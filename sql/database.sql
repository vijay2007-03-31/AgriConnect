-- ============================================================================
-- AgriConnect: Smart Contract Farming & Agritech Management Portal
-- Relational Database Schema Definition & Seed Data
-- Architecture: Academic Milestone 2 (Strict MySQL 8.x + 3NF)
-- Database Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

-- Create and select the database
CREATE DATABASE IF NOT EXISTS `agriconnect_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `agriconnect_db`;

-- Drop tables in reverse order of foreign key dependencies to prevent constraint violations
DROP TABLE IF EXISTS `contracts`;
DROP TABLE IF EXISTS `crop_demands`;
DROP TABLE IF EXISTS `farmer_profiles`;
DROP TABLE IF EXISTS `users`;

-- ----------------------------------------------------------------------------
-- 1. Table: users
-- Core identity and authentication table supporting Role-Based Access Control (RBAC).
-- Normalization: 1NF & 2NF compliant. Passwords stored using standard BCRYPT ($2y$10$...).
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `role` ENUM('farmer', 'buyer', 'admin') NOT NULL DEFAULT 'farmer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: farmer_profiles
-- 1-to-1 extension of users storing normalized agronomic metadata (land, soil, survey).
-- Normalization: Kept separate from `users` to satisfy 3NF (non-farmers do not
-- carry null agronomic attributes, eliminating transitive dependency).
-- ----------------------------------------------------------------------------
CREATE TABLE `farmer_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `land_acres` DECIMAL(6,2) NOT NULL,
    `soil_type` VARCHAR(100) NOT NULL,
    `survey_number` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `district` VARCHAR(100) NOT NULL,
    INDEX `idx_farmer_profiles_user` (`user_id`),
    CONSTRAINT `fk_farmer_profiles_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: crop_demands
-- Procurement tenders published by verified corporate agribusiness buyers.
-- Represents commercial market demand specifying volume, price floor, location, and deadline.
-- ----------------------------------------------------------------------------
CREATE TABLE `crop_demands` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `buyer_id` INT NOT NULL,
    `crop_name` VARCHAR(100) NOT NULL,
    `variety` VARCHAR(120) NOT NULL,
    `required_quintals` INT NOT NULL,
    `offered_price_per_quintal` DECIMAL(10,2) NOT NULL,
    `delivery_location` VARCHAR(255) NOT NULL,
    `deadline` DATE NOT NULL,
    `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_crop_demands_status` (`status`),
    INDEX `idx_crop_demands_buyer` (`buyer_id`),
    INDEX `idx_crop_demands_crop` (`crop_name`),
    CONSTRAINT `fk_crop_demands_buyer`
        FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table: contracts
-- Bids & pre-harvest contract agreements bridging farmers with buyer procurement demands.
-- Encapsulates committed delivery volume, quality notes, and contract lifecycle state.
-- ----------------------------------------------------------------------------
CREATE TABLE `contracts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `demand_id` INT NOT NULL,
    `farmer_id` INT NOT NULL,
    `proposed_quantity` INT NOT NULL,
    `bid_notes` TEXT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'fulfilled') NOT NULL DEFAULT 'pending',
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_contracts_status` (`status`),
    INDEX `idx_contracts_demand` (`demand_id`),
    INDEX `idx_contracts_farmer` (`farmer_id`),
    CONSTRAINT `fk_contracts_demand`
        FOREIGN KEY (`demand_id`) REFERENCES `crop_demands` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_contracts_farmer`
        FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- REALISTIC SEED DATA INSERTION
-- All default user passwords are set to: password123
-- Generated using PHP: password_hash('password123', PASSWORD_BCRYPT)
-- BCRYPT Hash: $2y$10$qbMLfRXUgKMieh09pDmWhOvUe0n5nyGSbbo2k.GbVvu3EGuviYBMK
-- ============================================================================

-- 1. Insert Users (2 Farmers, 2 Corporate Buyers, 1 Admin)
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `phone`, `role`, `created_at`) VALUES
(1, 'Ramesh Patel', 'ramesh.farmer@agriconnect.org', '$2y$10$qbMLfRXUgKMieh09pDmWhOvUe0n5nyGSbbo2k.GbVvu3EGuviYBMK', '9876543210', 'farmer', '2026-09-01 09:30:00'),
(2, 'Suresh Kumar', 'suresh.farmer@agriconnect.org', '$2y$10$qbMLfRXUgKMieh09pDmWhOvUe0n5nyGSbbo2k.GbVvu3EGuviYBMK', '9823456789', 'farmer', '2026-09-03 11:15:00'),
(3, 'Vikram Sharma (ITC Agri-Business)', 'vikram.buyer@itcagro.com', '$2y$10$qbMLfRXUgKMieh09pDmWhOvUe0n5nyGSbbo2k.GbVvu3EGuviYBMK', '9988776655', 'buyer', '2026-08-20 14:00:00'),
(4, 'Anita Desai (Kisan Fresh Logistics)', 'anita.buyer@kisanfresh.in', '$2y$10$qbMLfRXUgKMieh09pDmWhOvUe0n5nyGSbbo2k.GbVvu3EGuviYBMK', '9765432109', 'buyer', '2026-08-22 16:45:00'),
(5, 'AgriConnect Administrator', 'admin@agriconnect.org', '$2y$10$qbMLfRXUgKMieh09pDmWhOvUe0n5nyGSbbo2k.GbVvu3EGuviYBMK', '9123456780', 'admin', '2026-08-01 08:00:00');

-- 2. Insert Farmer Profiles (Normalized agronomic attributes for users 1 & 2)
INSERT INTO `farmer_profiles` (`id`, `user_id`, `land_acres`, `soil_type`, `survey_number`, `state`, `district`) VALUES
(1, 1, 8.50, 'Alluvial Black Soil', 'SY-4021/B', 'Punjab', 'Ludhiana'),
(2, 2, 12.00, 'Red Sandy Loam', 'SY-8812/A', 'Haryana', 'Karnal');

-- 3. Insert Active Crop Demands (Procurement tenders from buyers 3 & 4)
INSERT INTO `crop_demands` (`id`, `buyer_id`, `crop_name`, `variety`, `required_quintals`, `offered_price_per_quintal`, `delivery_location`, `deadline`, `status`, `created_at`) VALUES
(1, 3, 'Wheat', 'Sharbati (Grade A)', 500, 2450.00, 'Khanna Grain Terminal, Ludhiana, Punjab', '2026-12-15', 'open', '2026-10-01 10:00:00'),
(2, 3, 'Rice', 'Pusa 1121 Basmati', 800, 4150.00, 'Karnal Export Logistics Hub, Haryana', '2026-11-30', 'open', '2026-10-05 11:30:00'),
(3, 4, 'Cotton', 'Shankar-6 Organic', 350, 7200.00, 'Rajkot Agro Industrial Park, Gujarat', '2026-12-20', 'open', '2026-10-10 14:15:00'),
(4, 4, 'Soybean', 'Yellow Non-GMO (JS-335)', 600, 4800.00, 'Indore Agromart Mandi, Madhya Pradesh', '2027-01-10', 'open', '2026-10-12 15:00:00'),
(5, 3, 'Coconut', 'Pollachi De-Husked (Grade 1)', 1200, 3850.00, 'Pollachi Agro Processing Hub, Coimbatore, Tamil Nadu', '2026-12-25', 'open', '2026-10-14 09:00:00'),
(6, 4, 'Onion', 'Nashik Red (Lasalgaon A-Grade)', 750, 2600.00, 'Lasalgaon Mandi Terminal, Nashik, Maharashtra', '2026-11-28', 'open', '2026-10-15 11:00:00');

-- 4. Insert Contract Bids (Testing pending, approved, and rejected states)
INSERT INTO `contracts` (`id`, `demand_id`, `farmer_id`, `proposed_quantity`, `bid_notes`, `status`, `applied_at`, `updated_at`) VALUES
(1, 1, 1, 250, 'Certified organic Sharbati wheat from Ludhiana farm with moisture strictly below 12%. Ready for December batch dispatch.', 'approved', '2026-10-04 10:20:00', '2026-10-06 14:10:00'),
(2, 2, 2, 400, 'A-grade Pusa Basmati grown under micro-irrigation. Grain length meets 8.35mm export standards. Pre-harvest inspection welcome.', 'pending', '2026-10-08 16:30:00', '2026-10-08 16:30:00'),
(3, 3, 1, 100, 'High staple length Shankar-6 cotton. Harvest ready late November.', 'rejected', '2026-10-11 09:45:00', '2026-10-13 11:00:00'),
(4, 5, 2, 300, 'West Coast Tall organic coconuts, minimum 550g nut weight. Regular monthly harvesting batches ready for copra oil milling.', 'approved', '2026-10-15 14:00:00', '2026-10-16 10:30:00'),
(5, 6, 1, 200, 'Lasalgaon medium-large dark red onions. Properly cured and dried under ambient shed. Moisture 12-14%.', 'pending', '2026-10-16 16:20:00', '2026-10-16 16:20:00');
