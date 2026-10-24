#!/usr/bin/env python3
"""
AgriConnect — Local Development & Preview Runtime Server
Enables full browser previewing of both Stage 1 and Stage 2 without requiring external Apache/PHP services.
Uses an isolated local SQLite preview store so that official MySQL schemas (sql/database.sql) remain untouched.
"""

import os
import sys
import sqlite3
import urllib.parse
from http.server import HTTPServer, BaseHTTPRequestHandler
from http.cookies import SimpleCookie
import uuid
import mimetypes

PORT = 8000
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_FILE = os.path.join(BASE_DIR, '.preview.db')

SESSIONS = {}

def init_db():
    conn = sqlite3.connect(DB_FILE)
    c = conn.cursor()
    c.execute("""
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        phone TEXT NOT NULL,
        role TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    """)
    c.execute("""
    CREATE TABLE IF NOT EXISTS farmer_profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        land_acres REAL NOT NULL,
        soil_type TEXT NOT NULL,
        survey_number TEXT NOT NULL,
        state TEXT NOT NULL,
        district TEXT NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
    """)
    c.execute("""
    CREATE TABLE IF NOT EXISTS crop_demands (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        buyer_id INTEGER NOT NULL,
        crop_name TEXT NOT NULL,
        variety TEXT NOT NULL,
        required_quintals INTEGER NOT NULL,
        offered_price_per_quintal REAL NOT NULL,
        delivery_location TEXT NOT NULL,
        deadline TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
    )
    """)
    c.execute("""
    CREATE TABLE IF NOT EXISTS contracts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        demand_id INTEGER NOT NULL,
        farmer_id INTEGER NOT NULL,
        proposed_quantity INTEGER NOT NULL,
        bid_notes TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (demand_id) REFERENCES crop_demands(id) ON DELETE CASCADE,
        FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE
    )
    """)

    c.execute("SELECT COUNT(*) FROM users")
    if c.fetchone()[0] == 0:
        # Seed users (password: password123)
        c.execute("INSERT INTO users (id, name, email, password, phone, role) VALUES (1, 'Ramesh Patel', 'ramesh.farmer@agriconnect.org', 'password123', '9876543210', 'farmer')")
        c.execute("INSERT INTO users (id, name, email, password, phone, role) VALUES (2, 'Suresh Kumar', 'suresh.farmer@agriconnect.org', 'password123', '9823456789', 'farmer')")
        c.execute("INSERT INTO users (id, name, email, password, phone, role) VALUES (3, 'Vikram Sharma (ITC Agri-Business)', 'vikram.buyer@itcagro.com', 'password123', '9988776655', 'buyer')")
        c.execute("INSERT INTO users (id, name, email, password, phone, role) VALUES (4, 'Anita Desai (Kisan Fresh Logistics)', 'anita.buyer@kisanfresh.in', 'password123', '9765432109', 'buyer')")
        c.execute("INSERT INTO users (id, name, email, password, phone, role) VALUES (5, 'AgriConnect Administrator', 'admin@agriconnect.org', 'password123', '9123456780', 'admin')")

        c.execute("INSERT INTO farmer_profiles (user_id, land_acres, soil_type, survey_number, state, district) VALUES (1, 8.50, 'Alluvial Black Soil', 'SY-4021/B', 'Punjab', 'Ludhiana')")
        c.execute("INSERT INTO farmer_profiles (user_id, land_acres, soil_type, survey_number, state, district) VALUES (2, 12.00, 'Red Sandy Loam', 'SY-8812/A', 'Haryana', 'Karnal')")

        c.execute("INSERT INTO crop_demands (id, buyer_id, crop_name, variety, required_quintals, offered_price_per_quintal, delivery_location, deadline, status) VALUES (1, 3, 'Wheat', 'Sharbati (Grade A)', 500, 2450.00, 'Khanna Grain Terminal, Ludhiana, Punjab', '2026-12-15', 'open')")
        c.execute("INSERT INTO crop_demands (id, buyer_id, crop_name, variety, required_quintals, offered_price_per_quintal, delivery_location, deadline, status) VALUES (2, 3, 'Rice', 'Pusa 1121 Basmati', 800, 4150.00, 'Karnal Export Logistics Hub, Haryana', '2026-11-30', 'open')")
        c.execute("INSERT INTO crop_demands (id, buyer_id, crop_name, variety, required_quintals, offered_price_per_quintal, delivery_location, deadline, status) VALUES (3, 4, 'Cotton', 'Shankar-6 Organic', 350, 7200.00, 'Rajkot Agro Industrial Park, Gujarat', '2026-12-20', 'open')")
        c.execute("INSERT INTO crop_demands (id, buyer_id, crop_name, variety, required_quintals, offered_price_per_quintal, delivery_location, deadline, status) VALUES (4, 4, 'Soybean', 'Yellow Non-GMO (JS-335)', 600, 4800.00, 'Indore Agromart Mandi, Madhya Pradesh', '2027-01-10', 'open')")
        c.execute("INSERT INTO crop_demands (id, buyer_id, crop_name, variety, required_quintals, offered_price_per_quintal, delivery_location, deadline, status) VALUES (5, 3, 'Coconut', 'Pollachi De-Husked (Grade 1)', 1200, 3850.00, 'Pollachi Agro Processing Hub, Coimbatore, Tamil Nadu', '2026-12-25', 'open')")
        c.execute("INSERT INTO crop_demands (id, buyer_id, crop_name, variety, required_quintals, offered_price_per_quintal, delivery_location, deadline, status) VALUES (6, 4, 'Onion', 'Nashik Red (Lasalgaon A-Grade)', 750, 2600.00, 'Lasalgaon Mandi Terminal, Nashik, Maharashtra', '2026-11-28', 'open')")

        c.execute("INSERT INTO contracts (id, demand_id, farmer_id, proposed_quantity, bid_notes, status) VALUES (1, 1, 1, 250, 'Certified organic Sharbati wheat from Ludhiana farm with moisture strictly below 12%. Ready for December batch dispatch.', 'approved')")
        c.execute("INSERT INTO contracts (id, demand_id, farmer_id, proposed_quantity, bid_notes, status) VALUES (2, 2, 2, 400, 'A-grade Pusa Basmati grown under micro-irrigation. Grain length meets 8.35mm export standards. Pre-harvest inspection welcome.', 'pending')")
        c.execute("INSERT INTO contracts (id, demand_id, farmer_id, proposed_quantity, bid_notes, status) VALUES (3, 3, 1, 100, 'High staple length Shankar-6 cotton. Harvest ready late November.', 'rejected')")
        c.execute("INSERT INTO contracts (id, demand_id, farmer_id, proposed_quantity, bid_notes, status) VALUES (4, 5, 2, 300, 'West Coast Tall organic coconuts, minimum 550g nut weight. Regular monthly harvesting batches ready for copra oil milling.', 'approved')")
        c.execute("INSERT INTO contracts (id, demand_id, farmer_id, proposed_quantity, bid_notes, status) VALUES (5, 6, 1, 200, 'Lasalgaon medium-large dark red onions. Properly cured and dried under ambient shed. Moisture 12-14%.', 'pending')")

    conn.commit()
    conn.close()

init_db()

class PreviewHandler(BaseHTTPRequestHandler):
    def get_session(self):
        cookie_header = self.headers.get('Cookie')
        if not cookie_header:
            return None
        cookie = SimpleCookie(cookie_header)
        if 'agri_session' in cookie:
            sid = cookie['agri_session'].value
            return SESSIONS.get(sid)
        return None

    def set_session(self, user):
        sid = str(uuid.uuid4())
        SESSIONS[sid] = user
        return f"agri_session={sid}; Path=/; HttpOnly; SameSite=Lax"

    def parse_post_data(self):
        content_length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(content_length).decode('utf-8', errors='replace')
        parsed = urllib.parse.parse_qs(body)
        return {k: v[0] for k, v in parsed.items()}

    def send_html(self, html_content, status=200, extra_headers=None):
        self.send_response(status)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        if extra_headers:
            for k, v in extra_headers.items():
                self.send_header(k, v)
        self.end_headers()
        self.wfile.write(html_content.encode('utf-8'))

    def redirect(self, location, cookie=None):
        self.send_response(302)
        self.send_header('Location', location)
        if cookie:
            self.send_header('Set-Cookie', cookie)
        self.end_headers()

    def serve_static(self, rel_path):
        clean_path = rel_path.lstrip('/')
        # handle subfolder /agriconnect/ prefix if requested
        if clean_path.startswith('agriconnect/'):
            clean_path = clean_path[len('agriconnect/'):]

        file_path = os.path.join(BASE_DIR, clean_path)
        if os.path.isfile(file_path):
            ctype, _ = mimetypes.guess_type(file_path)
            if not ctype:
                ctype = 'application/octet-stream'
            if file_path.endswith('.css'):
                ctype = 'text/css'
            elif file_path.endswith('.js'):
                ctype = 'application/javascript'
            elif file_path.endswith('.html'):
                ctype = 'text/html; charset=utf-8'

            with open(file_path, 'rb') as f:
                content = f.read()

            self.send_response(200)
            self.send_header('Content-Type', ctype)
            self.send_header('Content-Length', str(len(content)))
            self.end_headers()
            self.wfile.write(content)
            return True
        return False

    def do_HEAD(self):
        self.send_response(200)
        self.end_headers()

    def do_GET(self):
        parsed = urllib.parse.urlparse(self.path)
        path = parsed.path
        if path.startswith('/agriconnect/'):
            path = '/' + path[len('/agriconnect/'):]

        # Root redirect
        if path in ('', '/', '/index.php', '/index.html'):
            session = self.get_session()
            if session:
                if session['role'] == 'farmer':
                    return self.redirect('/farmer/dashboard.php')
                else:
                    return self.redirect('/buyer/dashboard.php')
            return self.redirect('/public/index.html')

        # Static assets
        if path.startswith(('/css/', '/js/', '/public/', '/docs/')):
            if self.serve_static(path):
                return
            self.send_error(404, "File Not Found")
            return

        # Auth endpoints
        if path == '/auth/login.php':
            session = self.get_session()
            if session:
                return self.redirect('/farmer/dashboard.php' if session['role'] == 'farmer' else '/buyer/dashboard.php')
            return self.render_login()

        if path == '/auth/register.php':
            return self.redirect('/public/register.html')

        if path == '/auth/logout.php':
            cookie_header = self.headers.get('Cookie')
            if cookie_header:
                cookie = SimpleCookie(cookie_header)
                if 'agri_session' in cookie:
                    sid = cookie['agri_session'].value
                    SESSIONS.pop(sid, None)
            return self.redirect('/auth/login.php', cookie="agri_session=deleted; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT")

        # Protected Farmer Endpoints
        session = self.get_session()
        if path.startswith('/farmer/'):
            if not session or session.get('role') != 'farmer':
                return self.redirect('/auth/login.php?error=unauthorized')
            if path == '/farmer/dashboard.php':
                return self.render_farmer_dashboard(session)
            if path == '/farmer/browse_contracts.php':
                return self.render_farmer_browse(session)
            if path == '/farmer/my_bids.php':
                return self.render_farmer_bids(session)

        # Protected Buyer Endpoints
        if path.startswith('/buyer/'):
            if not session or session.get('role') not in ('buyer', 'admin'):
                return self.redirect('/auth/login.php?error=unauthorized')
            if path == '/buyer/dashboard.php':
                return self.render_buyer_dashboard(session)
            if path == '/buyer/post_contract.php':
                return self.render_buyer_post_contract(session)
            if path == '/buyer/manage_bids.php':
                return self.render_buyer_manage_bids(session, parsed.query)

        self.send_error(404, "Endpoint Not Found")

    def do_POST(self):
        parsed = urllib.parse.urlparse(self.path)
        path = parsed.path
        if path.startswith('/agriconnect/'):
            path = '/' + path[len('/agriconnect/'):]

        data = self.parse_post_data()

        if path == '/auth/login.php':
            email = data.get('email', '').strip().lower()
            password = data.get('password', '').strip()

            conn = sqlite3.connect(DB_FILE)
            c = conn.cursor()
            c.execute("SELECT id, name, email, password, role FROM users WHERE LOWER(email) = ?", (email,))
            user = c.fetchone()
            conn.close()

            # Support password123, Farmer@123, Buyer@123
            if user and (password in ('password123', 'Farmer@123', 'Buyer@123', 'Admin@123') or password == user[3]):
                session_user = {
                    'user_id': user[0],
                    'name': user[1],
                    'email': user[2],
                    'role': user[4]
                }
                cookie = self.set_session(session_user)
                if user[4] == 'farmer':
                    return self.redirect('/farmer/dashboard.php', cookie=cookie)
                else:
                    return self.redirect('/buyer/dashboard.php', cookie=cookie)
            else:
                return self.render_login(error="Invalid email or password. Use password123 for demo accounts.")

        session = self.get_session()
        if not session:
            return self.redirect('/auth/login.php')

        # Farmer Bid Submission
        if path == '/farmer/browse_contracts.php':
            demand_id = int(data.get('demand_id', 0))
            proposed_qty = int(data.get('proposed_quantity', 0))
            notes = data.get('bid_notes', '')

            if demand_id > 0 and proposed_qty > 0:
                conn = sqlite3.connect(DB_FILE)
                c = conn.cursor()
                c.execute("INSERT INTO contracts (demand_id, farmer_id, proposed_quantity, bid_notes, status) VALUES (?, ?, ?, ?, 'pending')",
                          (demand_id, session['user_id'], proposed_qty, notes))
                conn.commit()
                conn.close()
                return self.redirect('/farmer/my_bids.php')

        # Buyer Tender Demand Publishing
        if path == '/buyer/post_contract.php':
            crop = data.get('crop_name', 'Wheat')
            variety = data.get('variety', '')
            qty = int(data.get('required_quintals', 0))
            price = float(data.get('offered_price_per_quintal', 0))
            loc = data.get('delivery_location', '')
            deadline = data.get('deadline', '')

            conn = sqlite3.connect(DB_FILE)
            c = conn.cursor()
            c.execute("INSERT INTO crop_demands (buyer_id, crop_name, variety, required_quintals, offered_price_per_quintal, delivery_location, deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'open')",
                      (session['user_id'], crop, variety, qty, price, loc, deadline))
            conn.commit()
            conn.close()
            return self.redirect('/buyer/dashboard.php')

        # Buyer Bid Status Update (Approve / Reject)
        if path == '/buyer/manage_bids.php':
            bid_id = int(data.get('bid_id', 0))
            status = data.get('status', 'pending')
            if bid_id > 0 and status in ('approved', 'rejected', 'pending'):
                conn = sqlite3.connect(DB_FILE)
                c = conn.cursor()
                c.execute("UPDATE contracts SET status = ? WHERE id = ?", (status, bid_id))
                conn.commit()
                conn.close()
            return self.redirect('/buyer/manage_bids.php')

        self.send_error(400, "Bad Request")

    # View Renderers
    def render_login(self, error=None):
        err_msg = f'<div class="alert alert-danger" style="margin-bottom:1.5rem;"><span>⚠️</span><div>{error}</div></div>' if error else ''
        html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In — AgriConnect</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <div class="msp-ticker-wrapper">
        <div class="container-wide msp-ticker-container">
            <span class="msp-badge">Official MSP 2026</span>
            <div class="msp-ticker-track">
                <div class="msp-item"><span class="crop-title">🌾 Sharbati Wheat:</span> <span class="price">₹2,450 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🍚 Pusa 1121 Basmati:</span> <span class="price">₹4,150 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🌱 Shankar-6 Cotton:</span> <span class="price">₹7,200 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🥥 Pollachi Coconut:</span> <span class="price">₹3,850 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🧅 Nashik Red Onion:</span> <span class="price">₹2,600 / Qtl</span></div>
                <div class="msp-item"><span class="crop-title">🫘 Yellow Soybean:</span> <span class="price">₹4,800 / Qtl</span></div>
            </div>
        </div>
    </div>

    <header class="site-header">
        <div class="container navbar">
            <a href="/public/index.html" class="brand-logo">
                <div class="brand-icon">🌱</div>
                <span>AgriConnect</span>
                <span class="brand-tag">Preview Mode</span>
            </a>
            <ul class="nav-menu">
                <li><a href="/public/index.html" class="nav-link">Home</a></li>
                <li><a href="/public/contracts.html" class="nav-link">Tenders</a></li>
                <li><a href="/public/estimator.html" class="nav-link">Yield Calculator</a></li>
                <li><a href="/public/register.html" class="nav-link">Register</a></li>
            </ul>
        </div>
    </header>

    <div class="section">
        <div class="container" style="max-width: 520px;">
            <div class="text-center" style="margin-bottom: 2rem;">
                <div class="brand-icon" style="margin: 0 auto 1rem auto; width: 50px; height: 50px; font-size: 1.6rem;">🌱</div>
                <h1 style="font-size: 1.85rem; margin-bottom: 0.5rem;">Sign In to AgriConnect</h1>
                <p class="text-muted">Access your personalized Contract Farming & Procurement Portal</p>
            </div>

            {err_msg}

            <div class="card" style="margin-bottom: 1.5rem; background: var(--bg-alt); padding: 1.25rem; border-left: 4px solid var(--accent);">
                <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--accent-hover); margin-bottom: 0.5rem;">
                    🎓 1-Click Fast Login (Click to Enter Dashboard)
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickLogin('ramesh.farmer@agriconnect.org', 'password123')">
                        👨‍🌾 Farmer (Ramesh)
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickLogin('vikram.buyer@itcagro.com', 'password123')">
                        🏢 Buyer (ITC Agro)
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickLogin('admin@agriconnect.org', 'password123')">
                        🛡️ Admin
                    </button>
                </div>
            </div>

            <div class="card" style="padding: 2.25rem;">
                <form action="/auth/login.php" method="POST" id="loginForm">
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" value="ramesh.farmer@agriconnect.org" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span class="required">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" value="password123" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:1.5rem;">Sign In to Dashboard</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    function quickLogin(email, pwd) {{
        document.getElementById('email').value = email;
        document.getElementById('password').value = pwd;
        document.getElementById('loginForm').submit();
    }}
    </script>
</body>
</html>"""
        return self.send_html(html)

    def render_farmer_dashboard(self, session):
        conn = sqlite3.connect(DB_FILE)
        c = conn.cursor()
        c.execute("SELECT land_acres, soil_type, survey_number, state, district FROM farmer_profiles WHERE user_id = ?", (session['user_id'],))
        prof = c.fetchone() or (8.5, 'Alluvial Black Soil', 'SY-4021/B', 'Punjab', 'Ludhiana')

        c.execute("""
            SELECT COUNT(c.id),
                   SUM(CASE WHEN c.status = 'approved' THEN 1 ELSE 0 END),
                   SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END),
                   SUM(CASE WHEN c.status = 'approved' THEN (c.proposed_quantity * cd.offered_price_per_quintal) ELSE 0 END)
            FROM contracts c
            JOIN crop_demands cd ON c.demand_id = cd.id
            WHERE c.farmer_id = ?
        """, (session['user_id'],))
        stats = c.fetchone()
        tot, app, pen, rev = stats[0] or 0, stats[1] or 0, stats[2] or 0, stats[3] or 0.0

        c.execute("SELECT id, crop_name, variety, offered_price_per_quintal, delivery_location, required_quintals FROM crop_demands WHERE status = 'open' LIMIT 3")
        demands = c.fetchall()
        conn.close()

        demands_html = "".join([f"""
        <tr>
            <td><strong>{d[1]}</strong> ({d[2]})</td>
            <td>₹{d[3]:,.2f} / Qtl</td>
            <td>{d[5]} Quintals</td>
            <td>{d[4]}</td>
            <td><a href="/farmer/browse_contracts.php" class="btn btn-primary btn-sm">Bid Now</a></td>
        </tr>
        """ for d in demands])

        html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Farmer Dashboard — AgriConnect</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container navbar">
            <a href="/farmer/dashboard.php" class="brand-logo">
                <div class="brand-icon">🌱</div>
                <span>AgriConnect</span>
            </a>
            <ul class="nav-menu">
                <li><a href="/farmer/dashboard.php" class="nav-link active">Dashboard</a></li>
                <li><a href="/farmer/browse_contracts.php" class="nav-link">Browse Tenders</a></li>
                <li><a href="/farmer/my_bids.php" class="nav-link">My Contract Bids</a></li>
                <li><a href="/public/estimator.html" class="nav-link">Yield Calculator</a></li>
            </ul>
            <div class="nav-actions">
                <span class="user-badge">👨‍🌾 <strong>{session['name']}</strong></span>
                <a href="/auth/logout.php" class="btn btn-outline btn-sm">Sign Out</a>
            </div>
        </div>
    </header>

    <div class="section">
        <div class="container">
            <div style="margin-bottom: 2rem;">
                <span class="badge badge-role-farmer">Verified Cultivator</span>
                <h1 style="margin-top: 0.5rem;">Welcome, {session['name']}</h1>
                <p class="text-muted">Landholding: {prof[0]} Acres ({prof[1]}) | Survey No: {prof[2]} | {prof[4]}, {prof[3]}</p>
            </div>

            <div class="grid grid-4" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-value">{tot}</div>
                    <div class="stat-label">Total Submitted Bids</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color:var(--success);">{app}</div>
                    <div class="stat-label">Approved Contracts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color:var(--accent);">{pen}</div>
                    <div class="stat-label">Pending Decision</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color:var(--primary);">₹{rev:,.2f}</div>
                    <div class="stat-label">Guaranteed Revenue</div>
                </div>
            </div>

            <div class="card" style="padding: 1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h3>Open Agribusiness Procurement Tenders</h3>
                    <a href="/farmer/browse_contracts.php" class="btn btn-outline btn-sm">View All Tenders &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Crop & Variety</th>
                                <th>Offered Price</th>
                                <th>Target Volume</th>
                                <th>Delivery Hub</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {demands_html}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>"""
        return self.send_html(html)

    def render_farmer_browse(self, session):
        conn = sqlite3.connect(DB_FILE)
        c = conn.cursor()
        c.execute("""
            SELECT cd.id, cd.crop_name, cd.variety, cd.required_quintals, cd.offered_price_per_quintal, cd.delivery_location, cd.deadline, u.name
            FROM crop_demands cd
            JOIN users u ON cd.buyer_id = u.id
            WHERE cd.status = 'open'
        """)
        demands = c.fetchall()

        c.execute("SELECT demand_id, status FROM contracts WHERE farmer_id = ?", (session['user_id'],))
        existing = dict(c.fetchall())
        conn.close()

        cards = ""
        for d in demands:
            did = d[0]
            if did in existing:
                btn = f"<span class='badge badge-{existing[did]}'>Applied: {existing[did].capitalize()}</span>"
            else:
                btn = f"""<button type="button" class="btn btn-primary btn-block" onclick="openBid({did}, '{d[1]} ({d[2]})', '{d[7]}', '₹{d[4]:,.2f}', {d[3]})">Submit Pre-Harvest Bid</button>"""

            cards += f"""<div class="contract-card">
                <div>
                    <div class="contract-header">
                        <div>
                            <span class="crop-tag">{d[1]}</span>
                            <h3 style="margin-top:0.35rem;">{d[1]} ({d[2]})</h3>
                        </div>
                        <span class="badge badge-open">Open</span>
                    </div>
                    <div class="contract-price">₹{d[4]:,.2f} <span>/ Quintal</span></div>
                    <div class="contract-meta">
                        <div class="contract-meta-item"><span class="meta-label">Total Volume</span><span class="meta-value">{d[3]} Quintals</span></div>
                        <div class="contract-meta-item"><span class="meta-label">Buyer Company</span><span class="meta-value">{d[7]}</span></div>
                        <div class="contract-meta-item"><span class="meta-label">Delivery Hub</span><span class="meta-value">{d[5]}</span></div>
                        <div class="contract-meta-item"><span class="meta-label">Deadline</span><span class="meta-value">{d[6]}</span></div>
                    </div>
                </div>
                {btn}
            </div>"""

        html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Tenders — AgriConnect</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container navbar">
            <a href="/farmer/dashboard.php" class="brand-logo">
                <div class="brand-icon">🌱</div>
                <span>AgriConnect</span>
            </a>
            <ul class="nav-menu">
                <li><a href="/farmer/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="/farmer/browse_contracts.php" class="nav-link active">Browse Tenders</a></li>
                <li><a href="/farmer/my_bids.php" class="nav-link">My Contract Bids</a></li>
            </ul>
            <div class="nav-actions">
                <span class="user-badge">👨‍🌾 <strong>{session['name']}</strong></span>
                <a href="/auth/logout.php" class="btn btn-outline btn-sm">Sign Out</a>
            </div>
        </div>
    </header>

    <div class="section">
        <div class="container">
            <div style="margin-bottom: 2rem;">
                <h1>Corporate Procurement Tenders</h1>
                <p class="text-muted">Select an active demand to lock your harvest sale before sowing.</p>
            </div>

            <div class="grid grid-3">
                {cards}
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal-backdrop" id="bidModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>
                    <h3 id="modalTitle" style="margin:0;">Submit Contract Bid</h3>
                    <span id="modalBuyer" class="text-muted" style="font-size:0.85rem;"></span>
                </div>
                <button type="button" class="modal-close" onclick="closeBid()">&times;</button>
            </div>
            <form action="/farmer/browse_contracts.php" method="POST">
                <input type="hidden" name="demand_id" id="modalDemandId">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Offered Contract Price</label>
                        <input type="text" id="modalPrice" class="form-control" readonly style="background:var(--bg-alt); font-weight:700; color:var(--primary);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Committed Volume (Quintals) <span class="required">*</span></label>
                        <input type="number" name="proposed_quantity" class="form-control" placeholder="e.g. 150" min="5" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quality & Harvest Notes</label>
                        <textarea name="bid_notes" class="form-control" rows="3" placeholder="Specify moisture, certification, or harvesting batch date"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeBid()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm & Transmit Bid</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openBid(id, title, buyer, price, maxQty) {{
        document.getElementById('modalDemandId').value = id;
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('modalBuyer').innerText = 'Published by ' + buyer;
        document.getElementById('modalPrice').value = price + ' / Quintal (Firm Price Lock)';
        document.getElementById('bidModal').classList.add('active');
    }}
    function closeBid() {{
        document.getElementById('bidModal').classList.remove('active');
    }}
    </script>
</body>
</html>"""
        return self.send_html(html)

    def render_farmer_bids(self, session):
        conn = sqlite3.connect(DB_FILE)
        c = conn.cursor()
        c.execute("""
            SELECT c.id, cd.crop_name, cd.variety, c.proposed_quantity, cd.offered_price_per_quintal,
                   (c.proposed_quantity * cd.offered_price_per_quintal) AS total_val,
                   c.status, u.name, c.applied_at
            FROM contracts c
            JOIN crop_demands cd ON c.demand_id = cd.id
            JOIN users u ON cd.buyer_id = u.id
            WHERE c.farmer_id = ?
            ORDER BY c.applied_at DESC
        """, (session['user_id'],))
        bids = c.fetchall()
        conn.close()

        rows = "".join([f"""
        <tr>
            <td><strong>#BID-{b[0]:04d}</strong></td>
            <td><strong>{b[1]}</strong> ({b[2]})</td>
            <td>{b[3]} Qtl</td>
            <td>₹{b[4]:,.2f}</td>
            <td style="font-weight:700; color:var(--primary);">₹{b[5]:,.2f}</td>
            <td>{b[7]}</td>
            <td><span class="badge badge-{b[6]}">{b[6].capitalize()}</span></td>
            <td>{b[8][:10]}</td>
        </tr>
        """ for b in bids])

        html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bids — AgriConnect</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container navbar">
            <a href="/farmer/dashboard.php" class="brand-logo">
                <div class="brand-icon">🌱</div>
                <span>AgriConnect</span>
            </a>
            <ul class="nav-menu">
                <li><a href="/farmer/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="/farmer/browse_contracts.php" class="nav-link">Browse Tenders</a></li>
                <li><a href="/farmer/my_bids.php" class="nav-link active">My Contract Bids</a></li>
            </ul>
            <div class="nav-actions">
                <span class="user-badge">👨‍🌾 <strong>{session['name']}</strong></span>
                <a href="/auth/logout.php" class="btn btn-outline btn-sm">Sign Out</a>
            </div>
        </div>
    </header>

    <div class="section">
        <div class="container">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                <div>
                    <h1>My Pre-Harvest Contract Bids</h1>
                    <p class="text-muted">Track buyer evaluation, approval status, and payout commitments.</p>
                </div>
                <a href="/farmer/browse_contracts.php" class="btn btn-primary">+ Browse New Tenders</a>
            </div>

            <div class="card" style="padding:1.5rem;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bid ID</th>
                                <th>Crop & Variety</th>
                                <th>Offered Volume</th>
                                <th>Price/Qtl</th>
                                <th>Total Contract Value</th>
                                <th>Buyer Company</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>"""
        return self.send_html(html)

    def render_buyer_dashboard(self, session):
        conn = sqlite3.connect(DB_FILE)
        c = conn.cursor()
        c.execute("""
            SELECT COUNT(DISTINCT cd.id),
                   COUNT(c.id),
                   SUM(CASE WHEN c.status = 'approved' THEN c.proposed_quantity ELSE 0 END),
                   SUM(CASE WHEN c.status = 'approved' THEN (c.proposed_quantity * cd.offered_price_per_quintal) ELSE 0 END)
            FROM crop_demands cd
            LEFT JOIN contracts c ON cd.id = c.demand_id
            WHERE cd.buyer_id = ?
        """, (session['user_id'],))
        stats = c.fetchone()
        tenders, bids, vol, cap = stats[0] or 0, stats[1] or 0, stats[2] or 0, stats[3] or 0.0

        c.execute("""
            SELECT c.id, cd.crop_name, u.name, c.proposed_quantity, (c.proposed_quantity * cd.offered_price_per_quintal), c.status
            FROM contracts c
            JOIN crop_demands cd ON c.demand_id = cd.id
            JOIN users u ON c.farmer_id = u.id
            WHERE cd.buyer_id = ?
            ORDER BY c.applied_at DESC
            LIMIT 5
        """, (session['user_id'],))
        recent_bids = c.fetchall()
        conn.close()

        bids_rows = "".join([f"""
        <tr>
            <td><strong>#BID-{b[0]:04d}</strong></td>
            <td>{b[1]}</td>
            <td>{b[2]}</td>
            <td>{b[3]} Quintals</td>
            <td>₹{b[4]:,.2f}</td>
            <td><span class="badge badge-{b[5]}">{b[5].capitalize()}</span></td>
            <td><a href="/buyer/manage_bids.php" class="btn btn-secondary btn-sm">Review</a></td>
        </tr>
        """ for b in recent_bids])

        html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Buyer Dashboard — AgriConnect</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container navbar">
            <a href="/buyer/dashboard.php" class="brand-logo">
                <div class="brand-icon">🌱</div>
                <span>AgriConnect</span>
            </a>
            <ul class="nav-menu">
                <li><a href="/buyer/dashboard.php" class="nav-link active">Dashboard</a></li>
                <li><a href="/buyer/post_contract.php" class="nav-link">Post Tender</a></li>
                <li><a href="/buyer/manage_bids.php" class="nav-link">Manage Farmer Bids</a></li>
            </ul>
            <div class="nav-actions">
                <span class="user-badge">🏢 <strong>{session['name']}</strong></span>
                <a href="/auth/logout.php" class="btn btn-outline btn-sm">Sign Out</a>
            </div>
        </div>
    </header>

    <div class="section">
        <div class="container">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:2rem;">
                <div>
                    <span class="badge badge-role-buyer">Corporate Agribusiness Desk</span>
                    <h1 style="margin-top:0.5rem;">{session['name']}</h1>
                    <p class="text-muted">Procurement & Sourcing Management Dashboard</p>
                </div>
                <a href="/buyer/post_contract.php" class="btn btn-primary">+ Post New Tender Demand</a>
            </div>

            <div class="grid grid-4" style="margin-bottom:2rem;">
                <div class="stat-card">
                    <div class="stat-value">{tenders}</div>
                    <div class="stat-label">Published Tenders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color:var(--accent);">{bids}</div>
                    <div class="stat-label">Farmer Bids Received</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color:var(--success);">{vol} Qtl</div>
                    <div class="stat-label">Locked Procurement</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color:var(--primary);">₹{cap:,.2f}</div>
                    <div class="stat-label">Committed Capital</div>
                </div>
            </div>

            <div class="card" style="padding:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h3>Recent Incoming Farmer Proposals</h3>
                    <a href="/buyer/manage_bids.php" class="btn btn-outline btn-sm">Manage All Bids &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bid ID</th>
                                <th>Crop</th>
                                <th>Farmer Name</th>
                                <th>Committed Quantity</th>
                                <th>Total Outlay</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {bids_rows}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>"""
        return self.send_html(html)

    def render_buyer_post_contract(self, session):
        html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post Tender — AgriConnect</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container navbar">
            <a href="/buyer/dashboard.php" class="brand-logo">
                <div class="brand-icon">🌱</div>
                <span>AgriConnect</span>
            </a>
            <ul class="nav-menu">
                <li><a href="/buyer/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="/buyer/post_contract.php" class="nav-link active">Post Tender</a></li>
                <li><a href="/buyer/manage_bids.php" class="nav-link">Manage Farmer Bids</a></li>
            </ul>
            <div class="nav-actions">
                <span class="user-badge">🏢 <strong>{session['name']}</strong></span>
                <a href="/auth/logout.php" class="btn btn-outline btn-sm">Sign Out</a>
            </div>
        </div>
    </header>

    <div class="section">
        <div class="container container-narrow">
            <h1>Publish Crop Procurement Demand</h1>
            <p class="text-muted">Post your volume demand to allow verified cultivators to bid.</p>

            <div class="card" style="margin-top:1.5rem; padding:2rem;">
                <form action="/buyer/post_contract.php" method="POST">
                    <div class="grid grid-2" style="gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Crop Name <span class="required">*</span></label>
                            <select name="crop_name" class="form-control">
                                <option value="Wheat">Wheat</option>
                                <option value="Rice">Rice / Basmati</option>
                                <option value="Cotton">Cotton</option>
                                <option value="Soybean">Soybean</option>
                                <option value="Coconut">Coconut</option>
                                <option value="Onion">Onion</option>
                                <option value="Maize">Maize</option>
                                <option value="Mustard">Mustard</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Variety / Grade <span class="required">*</span></label>
                            <input type="text" name="variety" class="form-control" placeholder="e.g. Sharbati A-Grade" required>
                        </div>
                    </div>

                    <div class="grid grid-2" style="gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Required Volume (Quintals) <span class="required">*</span></label>
                            <input type="number" name="required_quintals" class="form-control" placeholder="500" min="10" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Offered Price / Quintal (₹) <span class="required">*</span></label>
                            <input type="number" name="offered_price_per_quintal" class="form-control" placeholder="2450.00" min="100" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Delivery Mandi / Terminal <span class="required">*</span></label>
                        <input type="text" name="delivery_location" class="form-control" placeholder="Khanna Grain Terminal, Punjab" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Application Deadline Date <span class="required">*</span></label>
                        <input type="date" name="deadline" class="form-control" value="2026-12-15" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:1.5rem;">Publish Procurement Listing</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>"""
        return self.send_html(html)

    def render_buyer_manage_bids(self, session, query):
        conn = sqlite3.connect(DB_FILE)
        c = conn.cursor()
        c.execute("""
            SELECT c.id, cd.crop_name, cd.variety, u.name, u.phone, fp.land_acres, fp.district, fp.state,
                   c.proposed_quantity, cd.offered_price_per_quintal,
                   (c.proposed_quantity * cd.offered_price_per_quintal) AS total_val,
                   c.status, c.bid_notes
            FROM contracts c
            JOIN crop_demands cd ON c.demand_id = cd.id
            JOIN users u ON c.farmer_id = u.id
            LEFT JOIN farmer_profiles fp ON u.id = fp.user_id
            WHERE cd.buyer_id = ?
            ORDER BY c.applied_at DESC
        """, (session['user_id'],))
        bids = c.fetchall()
        conn.close()

        cards = ""
        for b in bids:
            bid_id = b[0]
            status = b[11]
            notes = f"<div style='margin-top:0.5rem; font-size:0.85rem; color:var(--text-muted);'><strong>Notes:</strong> {b[12]}</div>" if b[12] else ""

            if status == 'pending':
                actions = f"""
                <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                    <form action="/buyer/manage_bids.php" method="POST" style="margin:0;">
                        <input type="hidden" name="bid_id" value="{bid_id}">
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" class="btn btn-primary btn-sm">Approve Contract</button>
                    </form>
                    <form action="/buyer/manage_bids.php" method="POST" style="margin:0;">
                        <input type="hidden" name="bid_id" value="{bid_id}">
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                    </form>
                </div>
                """
            else:
                actions = f"<div style='margin-top:1rem;'><span class='badge badge-{status}'>Contract Status: {status.capitalize()}</span></div>"

            cards += f"""
            <div class="card" style="padding:1.5rem; margin-bottom:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span class="crop-tag">{b[1]}</span>
                            <span class="text-muted">#BID-{bid_id:04d}</span>
                        </div>
                        <h3 style="margin-top:0.25rem;">{b[1]} ({b[2]}) — {b[8]} Quintals</h3>
                        <p class="text-muted" style="margin:0.25rem 0;">Farmer: <strong>{b[3]}</strong> ({b[4]}) | Farm: {b[5]} Acres in {b[6]}, {b[7]}</p>
                        {notes}
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1.4rem; font-weight:800; color:var(--primary);">₹{b[10]:,.2f}</div>
                        <span class="text-muted">@ ₹{b[9]:,.2f} / Qtl</span>
                    </div>
                </div>
                {actions}
            </div>
            """

        html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Farmer Bids — AgriConnect</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container navbar">
            <a href="/buyer/dashboard.php" class="brand-logo">
                <div class="brand-icon">🌱</div>
                <span>AgriConnect</span>
            </a>
            <ul class="nav-menu">
                <li><a href="/buyer/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="/buyer/post_contract.php" class="nav-link">Post Tender</a></li>
                <li><a href="/buyer/manage_bids.php" class="nav-link active">Manage Farmer Bids</a></li>
            </ul>
            <div class="nav-actions">
                <span class="user-badge">🏢 <strong>{session['name']}</strong></span>
                <a href="/auth/logout.php" class="btn btn-outline btn-sm">Sign Out</a>
            </div>
        </div>
    </header>

    <div class="section">
        <div class="container">
            <div style="margin-bottom:2rem;">
                <h1>Evaluate Farmer Contract Proposals</h1>
                <p class="text-muted">Review incoming farmer tenders, inspect farm records, and approve pre-harvest sales.</p>
            </div>

            {cards}
        </div>
    </div>
</body>
</html>"""
        return self.send_html(html)

if __name__ == '__main__':
    server = HTTPServer(('0.0.0.0', PORT), PreviewHandler)
    print(f"🌱 AgriConnect Preview Server running on http://localhost:{PORT}")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    server.server_close()
