# 🌱 AgriConnect: Smart Contract Farming & Agritech Management Portal

> **Academic Project Submission across Two Evaluated Milestones:**
> - **Stage 1:** Responsive Static Web Architecture + Modular ES6 Client-Side Scripting
> - **Stage 2:** Dynamic Server-Side Web Application (PHP 8 + PDO) + Normalized MySQL Relational Database (3NF)

---

## 📑 Table of Contents
1. [Project Overview](#1-project-overview)
2. [Complete Directory Architecture](#2-complete-directory-architecture)
3. [Technology Stack & System Requirements](#3-technology-stack--system-requirements)
4. [Step-by-Step Local Setup Guide (XAMPP / WAMP)](#4-step-by-step-local-setup-guide-xampp--wamp)
5. [Pre-Seeded Demo Test Accounts](#5-pre-seeded-demo-test-accounts)
6. [Academic Milestones & Feature Matrix](#6-academic-milestones--feature-matrix)
7. [Academic Review Deliverables & Viva Materials](#7-academic-review-deliverables--viva-materials)
8. [Security & Engineering Standards](#8-security--engineering-standards)

---

## 1. Project Overview

**AgriConnect** is an academic-grade, full-stack digital contract farming portal that connects agricultural cultivators directly with corporate agribusinesses, grain millers, and FMCG food processors.

### Core Objectives:
- **Pre-Harvest Price Locks:** Safeguards farmers against post-harvest mandi price crashes by locking in legally binding contract terms prior to sowing.
- **Zero Intermediary Exploitation:** Eliminates 3–5 layers of middlemen (Arhtiyas/traders), transferring up to 30% higher net revenues to primary cultivators.
- **Econometric Yield & Profit Modeling:** Empowers farmers with a real-time calculator modeling gross yields, input costs, revenues, and net profits across 6 staple crops (**Wheat, Rice, Cotton, Soybean, Coconut, Onion**).
- **Corporate Sourcing Assurance:** Provides food processors with guaranteed commodity volume, verified moisture/quality grades, and land survey records.

---

## 2. Complete Directory Architecture

```
agriconnect/
├── sql/
│   └── database.sql                   # Schema creation (3NF), relations, sample seed data
├── config/
│   └── db.php                         # Secure MySQL PDO connection, error modes, session bootstrap
├── css/
│   └── styles.css                     # Unified modern CSS (Variables, Grid, Flexbox, responsive design)
├── js/
│   ├── validation.js                  # Real-time regex & constraint validation for all forms
│   ├── estimator.js                   # Crop yield, production cost, and net profit calculator
│   └── filter.js                      # Search and multi-category filtering logic for contracts
├── includes/
│   ├── header.php                     # Shared global navigation, auth state indicator, mobile toggle
│   ├── footer.php                     # Global footer with quick links, legal, MSP ticker preview
│   └── auth_check.php                 # Middleware script for role-based route protection
├── public/ (Stage 1 Entrypoints)
│   ├── index.html                     # Responsive landing page with features, MSP ticker, mission
│   ├── estimator.html                 # Interactive JavaScript Yield & Revenue Calculator
│   ├── contracts.html                 # Static procurement listings with live JS search/filter
│   └── register.html                  # Multi-step farmer/buyer registration form with live JS validation
├── auth/
│   ├── register.php                   # Secure backend registration handler (hashing, role allocation)
│   ├── login.php                      # Authentication controller with secure session creation
│   └── logout.php                     # Complete session teardown and redirect
├── farmer/
│   ├── dashboard.php                  # Farmer summary (Active Bids, Land Summary, Approved Contracts)
│   ├── browse_contracts.php           # Real-time tenders from DB with modal to submit a bid
│   └── my_bids.php                    # Table of submitted applications with status badges (Pending/Approved/Rejected)
├── buyer/
│   ├── dashboard.php                  # Agribusiness summary (Total tenders, active contracts, volume target)
│   ├── post_contract.php              # Form to publish crop demand (crop, quantity, price, deadline)
│   └── manage_bids.php                # Review farmer applications with "Approve" and "Reject" action handlers
├── docs/
│   ├── Review_01_Presentation.md      # Complete slide-by-slide deck outline & script for Stage 1
│   ├── Review_02_Presentation.md      # Complete slide-by-slide deck outline & script for Stage 2
│   └── Viva_Preparation_Guide.md      # 10 core technical viva questions with direct, academic answers
├── index.html                         # Root static entrypoint redirecting to public/index.html
├── index.php                          # Root intelligent router (routes to dashboard or public/index.html)
└── README.md                          # Comprehensive project documentation & evaluation guide
```

---

## 3. Technology Stack & System Requirements

| Tier | Technology | Specification / Standard |
| :--- | :--- | :--- |
| **Frontend Layout** | Semantic HTML5 | W3C Compliant, Accessible ARIA Attributes |
| **Frontend Styling**| Vanilla CSS3 | CSS Custom Properties, CSS Grid, Flexbox, Micro-Animations |
| **Client Scripting**| Modular ES6 JavaScript | Zero external libraries (No jQuery/React overhead) |
| **Backend Engine**  | PHP 8.0+ | Strict Mode, PDO, Parameterized Queries, Native Sessions |
| **Database RDBMS**  | MySQL 8.x / MariaDB 10.4+ | InnoDB Engine, `utf8mb4_unicode_ci`, 3NF Normalized |
| **Web Server**      | Apache (XAMPP / WAMP) | Standard Apache virtual host or alias hosting |

---

## 4. Step-by-Step Local Setup Guide (XAMPP / WAMP)

Follow these exact steps to run AgriConnect on Apache and MySQL:

1. **Deploy Project to Web Root:**
   Copy or extract the `agriconnect` project directory into your web server document root:
   - **XAMPP (Windows):** `C:\xampp\htdocs\agriconnect`
   - **XAMPP (macOS):** `/Applications/XAMPP/htdocs/agriconnect`
   - **WAMP (Windows):** `C:\wamp64\www\agriconnect`

2. **Start Services:**
   Open the **XAMPP Control Panel** (or WAMP manager) and start both the **Apache** and **MySQL** services. Ensure MySQL is running on port `3306`.

3. **Import Database Schema (MySQL 3NF):**
   - Open your browser and navigate to **phpMyAdmin**: `http://localhost/phpmyadmin`
   - Click **New** in the sidebar, name the database `agriconnect_db` (Collation: `utf8mb4_unicode_ci`), and click **Create**.
   - Select the newly created `agriconnect_db` database, navigate to the **Import** tab.
   - Choose the file [`sql/database.sql`](file:///Users/vijayamanikandang/agriconnect/sql/database.sql) from the `agriconnect/sql/` folder.
   - Click **Import** (or **Go**) at the bottom. This will create and seed all 4 normalized tables: `users`, `farmer_profiles`, `crop_demands`, and `contracts`.
   *(Alternative via terminal: `mysql -u root -p agriconnect_db < sql/database.sql`)*

4. **Access the Application in Browser:**
   - **Stage 1 Static Web Portal:** `http://localhost/agriconnect/public/index.html`
   - **Stage 2 Dynamic Login Portal:** `http://localhost/agriconnect/auth/login.php`
   - **Root Automatic Router:** `http://localhost/agriconnect/`

---

## 5. Pre-Seeded Demo Test Accounts

For evaluation and viva demonstrations, the MySQL database includes pre-configured accounts with realistic contract history. All default accounts use the password: `password123` (hashed with `password_hash('password123', PASSWORD_BCRYPT)`).

| Role | Name | Email Address | Password | Pre-loaded Data |
| :--- | :--- | :--- | :--- | :--- |
| **Farmer** | Ramesh Patel | `ramesh.farmer@agriconnect.org` | `password123` | 8.5 Acres in Ludhiana, Punjab; Approved Wheat & Pending Onion bids |
| **Farmer** | Suresh Kumar | `suresh.farmer@agriconnect.org` | `password123` | 12.0 Acres in Karnal, Haryana; Approved Coconut & Pending Rice bids |
| **Corporate Buyer** | Vikram Sharma (ITC Agro) | `vikram.buyer@itcagro.com` | `password123` | 3 published tenders (Wheat, Basmati Rice, Pollachi Coconut) |
| **Corporate Buyer** | Anita Desai (Kisan Fresh)| `anita.buyer@kisanfresh.in` | `password123` | 3 published tenders (Organic Cotton, Soybean, Nashik Red Onion) |
| **Administrator** | System Admin | `admin@agriconnect.org` | `password123` | Cross-portal supervisory access |

> 💡 **1-Click Auto-Fill:** On `auth/login.php`, click any of the "Quick Demo Logins" buttons to instantly populate credentials.

---

## 6. Academic Milestones & Feature Matrix

### Milestone 1: Responsive Static Web Architecture + Modular ES6 Scripting
- ✅ **Responsive Modern Design:** Unified `css/styles.css` using custom properties, accessible active rings, zebra-striped tables, and mobile drawer menu.
- ✅ **Official Government MSP Ticker:** Live infinite-loop marquee banner displaying current MSP rates across Kharif and Rabi staple crops.
- ✅ **Real-Time Regex Validation (`js/validation.js`):** Client-side validation for 10-digit Indian phones (`/^[6-9]\d{9}$/`), email formats, password entropy meter, and submission event guards.
- ✅ **Yield, Cost & Profit Calculator (`js/estimator.js`):** Econometric model computing gross output, input costs, and net farm profits in Indian Rupees (`Intl.NumberFormat('en-IN')`) for Wheat, Rice, Cotton, Soybean, Coconut, and Onion.
- ✅ **Contract Search & Filtering Engine (`js/filter.js`):** Real-time text indexing, multi-category dropdowns, price/volume sorting, and empty-state fallbacks.

### Milestone 2: Dynamic Server-Side Web Application + Normalized MySQL Relational Database (3NF)
- ✅ **3NF Relational Database:** Proof of First, Second, and Third Normal Form in `sql/database.sql` with Foreign Key constraints and `ON DELETE CASCADE`.
- ✅ **Strict PDO Database Connection:** Prepared statements with `PDO::ATTR_EMULATE_PREPARES => false` for absolute immunity against SQL Injection attacks.
- ✅ **Role-Based Access Control (RBAC):** Middleware in `includes/auth_check.php` enforcing authorization guards between Farmers and Agribusiness Buyers.
- ✅ **Defense-in-Depth Security:** BCRYPT password hashing (`cost=10`), `session_regenerate_id(true)` preventing session fixation, and XSS sanitization via `htmlspecialchars()`.
- ✅ **Farmer Portal:** Land profile summary, real-time tender bidding modal, duplicate bid prevention, and status tracking in `my_bids.php`.
- ✅ **Corporate Agribusiness Portal:** Demand publishing with minimum price constraints, live budget calculators, and one-click Approve/Reject actions in `manage_bids.php`.

---

## 7. Academic Review Deliverables & Viva Materials

The project includes documentation tailored for academic review presentations:

1. **[Review 01 Presentation Deck (`docs/Review_01_Presentation.md`)](file:///Users/vijayamanikandang/agriconnect/docs/Review_01_Presentation.md):**
   - 8-slide presentation script covering Stage 1: Problem Definition, Domain Context, UI/UX Architecture, and ES6 Client-Side Scripting Algorithms.
2. **[Review 02 Presentation Deck (`docs/Review_02_Presentation.md`)](file:///Users/vijayamanikandang/agriconnect/docs/Review_02_Presentation.md):**
   - 8-slide presentation script covering Stage 2: Apache $\leftrightarrow$ PHP 8 (PDO) $\leftrightarrow$ MySQL (3NF) Architecture, Relational Schema, BCRYPT Hashing, and SQLi Prevention.
3. **[Viva Preparation Guide (`docs/Viva_Preparation_Guide.md`)](file:///Users/vijayamanikandang/agriconnect/docs/Viva_Preparation_Guide.md):**
   - Core technical viva questions with direct, academic answers addressing PHP PDO with MySQL, 3NF normalization, session fixation, BCRYPT cryptography, and defense against SQLi and XSS.

---

## 8. Security & Engineering Standards

- **SQL Injection Defense:** All queries utilize named PDO parameters (`:email`, `:bid_id`). No dynamic concatenation is permitted.
- **Cross-Site Scripting (XSS) Defense:** All dynamic browser outputs pass through `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
- **Session Hardening:** Configured with `httponly = true` (disallowing JavaScript access) and `samesite = Lax` (countering CSRF).
- **Password Cryptography:** Enforced using `password_hash($pwd, PASSWORD_BCRYPT)` with individual cryptographic salts.

---

*AgriConnect is developed as an academic capstone project adhering to IEEE software engineering standards and modern agritech design specifications.*
