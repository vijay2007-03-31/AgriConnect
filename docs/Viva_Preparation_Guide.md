# AgriConnect — Comprehensive Technical Viva Preparation Guide
## Top 10 Core Academic Questions & Exhaustive Engineering Answers

This document prepares students and presenters for rigorous technical questioning by academic examiners and evaluation committees. Each answer provides theoretical computer science fundamentals followed by concrete implementation evidence from the **AgriConnect** codebase.

---

### Question 1: Explain the architectural distinction between HTTP GET and POST methods. Why must state-altering actions (such as contract bid approval) never use GET?

#### 🎓 Academic Answer:
Under the **RFC 9110 HTTP Semantics specification**, HTTP methods are categorized according to two core properties: **Safety** and **Idempotency**.

1. **Safety:** An HTTP method is safe if it represents a read-only operation that does not alter server-side resource state. `GET`, `HEAD`, and `OPTIONS` are safe methods.
2. **Idempotency:** A method is idempotent if executing $N > 1$ identical requests yields the identical server state as executing a single request. `GET`, `PUT`, and `DELETE` are idempotent; `POST` is non-idempotent.

**Why GET Must Never Mutate State:**
- **URL Parameter Exposure:** `GET` transmits parameters within the Uniform Resource Identifier (URI query string). These URIs are routinely cached by intermediate proxies, recorded in browser navigation history, and exposed in web server access logs (`/var/log/apache2/access.log`).
- **Prefetching & Web Crawlers:** Search engine bots and browser prefetching algorithms speculatively execute `GET` requests on hyperlinks. If a link like `<a href="manage_bids.php?action=approve&id=5">` exists, a web crawler could inadvertently approve corporate contracts.
- **CSRF Vulnerability:** An attacker can trigger unauthorized state mutations simply by embedding an image tag: `<img src="http://example.com/approve.php?id=5">`. Browsers will execute this `GET` request automatically with user cookies attached.

#### 💻 Implementation in AgriConnect:
In `buyer/manage_bids.php`, bid approval and rejection are strictly guarded:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    // Process status update securely
}
```
All actions are triggered via HTML forms using `method="POST"`, ensuring compliance with HTTP standards.

---

### Question 2: How does PHP session storage operate under the hood, and how did you prevent Session Fixation and Session Hijacking?

#### 🎓 Academic Answer:
HTTP is inherently a **stateless protocol**. To maintain continuous user authentication across multiple requests, PHP implements a hybrid client-server session state architecture:

1. **Server-Side State Allocation:** When `session_start()` executes, PHP allocates an internal data structure in the server filesystem (typically `/tmp/sess_<session_id>`) or in-memory store (Redis/Memcached).
2. **Client-Side Token Binding:** The server transmits an HTTP response header `Set-Cookie: PHPSESSID=38a9bc...; path=/; HttpOnly; SameSite=Lax`. The client browser stores this opaque identifier and retransmits it in the `Cookie:` header of subsequent requests.

**Vulnerabilities & AgriConnect Countermeasures:**
- **Session Fixation:** Occurs when an adversary forces a victim to authenticate using a pre-determined session ID known to the attacker.
  - *Countermeasure:* In `auth/login.php`, the moment user credentials are confirmed via `password_verify()`, the system invokes:
    ```php
    session_regenerate_id(true);
    ```
    This destroys the old session file and generates a completely new, cryptographically random session ID, invalidating any fixed ID held by an attacker.
- **Session Hijacking via Cross-Site Scripting (XSS):**
  - *Countermeasure:* In `config/db.php`, we configure hardened session cookie flags before session instantiation:
    ```php
    session_set_cookie_params([
        'lifetime' => 86400,
        'httponly' => true,      // Disallows access via document.cookie in JavaScript
        'samesite' => 'Lax',     // Blocks transmission in cross-site requests
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
    ]);
    ```

---

### Question 3: Prove that the AgriConnect database schema satisfies Third Normal Form (3NF). How does this design eliminate data redundancy and prevent insertion, deletion, and update anomalies?

#### 🎓 Academic Answer:
A relational schema is in **Third Normal Form (3NF)** if and only if it satisfies **Second Normal Form (2NF)** and no non-prime attribute is transitively dependent on any candidate key.

$$\forall X \rightarrow A, \quad \text{either } A \in X \text{ (trivial), } X \text{ is a superkey, or } A \text{ is a prime attribute.}$$

**1. Normalization Proof in AgriConnect:**
- **First Normal Form (1NF):** Every column in `users`, `farmer_profiles`, `crop_demands`, and `contracts` holds purely atomic, indivisible scalar values. Repeating groups and comma-delimited strings are prohibited.
- **Second Normal Form (2NF):** Every entity utilizes a single-column surrogate primary key (`id INT AUTO_INCREMENT`). Because primary keys are non-composite, no partial functional dependencies ($X_{\text{part}} \rightarrow Y$) can exist. Every non-prime attribute is fully functionally dependent on the entire primary key.
- **Third Normal Form (3NF):** Non-prime attributes depend solely and directly on the primary key without transitive dependencies ($X \rightarrow Y \rightarrow Z$). By isolating agronomic data (`land_acres`, `soil_type`, `survey_number`, `state`, `district`) into `farmer_profiles`, non-farmer users (corporate buyers and admins) do not store sparse, wasteful `NULL` fields.

**2. Prevention of Relational Anomalies & Elimination of Redundancy:**
- **Elimination of Data Redundancy:** Without normalization, denormalizing buyer corporate details or farmer soil parameters across every contract row would duplicate company names, phone numbers, and soil types thousands of times, consuming excessive disk storage and cache memory.
- **Insertion Anomaly Prevention:** A new corporate buyer can register and publish a crop demand before any farmer submits a bid. In an unnormalized single-table design, a procurement demand could not be recorded without simultaneously inserting a dummy farmer record.
- **Deletion Anomaly Prevention:** If a farmer withdraws their bid from `contracts`, the underlying `crop_demands` published by the corporate buyer and the farmer's registered identity in `users` remain fully intact.
- **Update (Modification) Anomaly Prevention:** If an agribusiness buyer updates their primary delivery depot or contact number, the change is executed in a single row in `users` or `crop_demands`. It is immediately reflected across all associated contracts without risk of inconsistent partial updates.

Therefore, the AgriConnect relational model is provably normalized to **3NF**, eliminating redundancy and guaranteeing structural data integrity.

---

### Question 4: What is Referential Integrity, and what are the operational implications of using `ON DELETE CASCADE` on foreign key constraints?

#### 🎓 Academic Answer:
**Referential Integrity** is a relational database property enforcing that every foreign key value in a child table must match an existing primary key value in the referenced parent table, or otherwise be null.

$$\pi_{\text{FK}}(R_{\text{child}}) \subseteq \pi_{\text{PK}}(R_{\text{parent}})$$

**Foreign Key Constraints in AgriConnect:**
In `sql/database.sql`:
```sql
CONSTRAINT `fk_contracts_demand`
    FOREIGN KEY (`demand_id`) REFERENCES `crop_demands` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
```

**Implications of `ON DELETE CASCADE`:**
1. **Automated Lifecycle Cleanliness:** If an agribusiness buyer withdraws or cancels a published tender (`DELETE FROM crop_demands WHERE id = 5`), the relational engine automatically cascades the deletion to all associated pending or approved bids in `contracts`.
2. **Prevention of Orphaned Tuples:** Without `CASCADE`, deleting a demand would either be rejected by the RDBMS (`RESTRICT` / `NO ACTION`), or leave dangling foreign keys pointing to non-existent demands if constraints were disabled.
3. **Operational Caution in Financial Systems:** While `CASCADE` is ideal for transient bids, in enterprise accounting, soft deletion (`status = 'archived'`) or `ON DELETE RESTRICT` is preferred to maintain permanent financial audit trails. AgriConnect demonstrates cascading mastery while enforcing role-based status closures.

---

### Question 5: How does the ACID model guarantee data reliability during farmer contract registration and bidding?

#### 🎓 Academic Answer:
The **ACID** model defines the four foundational guarantees of database transactions:

1. **Atomicity (All-or-Nothing):**
   - *Scenario:* User registration as a farmer requires inserting a tuple into `users` and a corresponding tuple into `farmer_profiles`.
   - *Implementation:* In `auth/register.php`, the operations are bounded by a PDO transaction:
     ```php
     $pdo->beginTransaction();
     // Insert into users
     $newUserId = $pdo->lastInsertId();
     // Insert into farmer_profiles
     $pdo->commit();
     ```
   - If the second query fails (e.g., duplicate survey number), the `catch` block invokes `$pdo->rollBack()`. The user record is erased, preventing partial, corrupted account existence.
2. **Consistency (Integrity Constraints):**
   - Every transaction transitions the database from one valid state to another, satisfying all schema constraints (e.g., `CHECK (proposed_quantity > 0)`, unique email addresses, and foreign key references).
3. **Isolation (Concurrency Control):**
   - Multiple farmers bidding concurrently on the same crop tender do not corrupt aggregated volume calculations. In MySQL InnoDB, row-level locking ensures concurrent transactions execute without race conditions.
4. **Durability (Fault Recovery):**
   - Once `$pdo->commit()` succeeds, the committed contract bid is written to the InnoDB write-ahead redo log (`ib_logfile`), guaranteeing survival even across sudden server hardware or power failures.

---

### Question 6: How does the PHP PDO driver interface with MySQL? Explain how prepared statements with `PDO::ATTR_EMULATE_PREPARES => false` guarantee SQL injection immunity.

#### 🎓 Academic Answer:
**1. How PHP PDO Interfaces with MySQL:**
**PHP Data Objects (PDO)** is an abstraction data-access layer providing a uniform interface for communicating with relational database management systems. When interfacing with MySQL, PHP utilizes the **`pdo_mysql` driver**, which wraps the underlying MySQL Native Driver (`mysqlnd`) or MySQL Client Library (`libmysqlclient`).

```
[ PHP Application Code ($pdo->prepare()) ]
                     │
                     ▼
[ PDO Core Abstraction Layer (PHP Extension) ]
                     │
                     ▼
[ pdo_mysql Driver Interface ]
                     │
                     ▼
[ mysqlnd (MySQL Native Driver) / TCP Socket (Port 3306) ]
                     │
                     ▼
[ MySQL Database Engine 8.x (InnoDB Parser & Optimizer) ]
```

**Key Advantages of PDO over legacy `mysql_*` and `mysqli`:**
- **Database Portability:** PDO abstracts database-specific connection syntax into a unified DSN (`mysql:host=localhost;dbname=...`).
- **Object-Oriented Architecture:** Consistent exception-driven error handling via `PDOException` and `PDO::ERRMODE_EXCEPTION`.
- **Prepared Statement Binary Protocol:** Communicates with MySQL using the native binary protocol rather than text-based concatenation.

**2. Anatomy of a SQL Injection (SQLi) Vulnerability:**
SQL Injection occurs when untrusted user input is directly concatenated into a dynamic SQL query string, allowing an attacker to manipulate the query's abstract syntax tree (AST).

*Vulnerable Example:*
```php
$sql = "SELECT * FROM users WHERE email = '" . $_POST['email'] . "'";
// Malicious Input: ' OR '1'='1
// Resulting Query: SELECT * FROM users WHERE email = '' OR '1'='1'
```
Here, the database interpreter treats the attacker's string `'1'='1'` as SQL grammar rather than literal data.

**3. How Prepared Statements Neutralize SQLi (The Two-Phase Protocol):**
Prepared statements decouple query compilation from parameter execution across two discrete phases over the MySQL binary client-server protocol:

1. **Preparation Phase (COM_STMT_PREPARE):** The application transmits the query template with named placeholders (`:email`) to the MySQL server:
   ```php
   $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = :email");
   ```
   MySQL compiles, parses the SQL Abstract Syntax Tree (AST), and optimizes the execution plan.
2. **Execution Phase (COM_STMT_EXECUTE):** The application transmits only the raw variable data:
   ```php
   $stmt->execute([':email' => $email]);
   ```
   The MySQL engine binds `:email` strictly as a literal data scalar in memory. Even if the input contains `' OR '1'='1; DROP TABLE users;--`, MySQL treats it purely as character text and **never** compiles it as executable SQL grammar.

**4. The Critical Role of `PDO::ATTR_EMULATE_PREPARES => false`:**
By default, PHP PDO historically emulated prepared statements client-side by escaping strings in PHP memory before sending a normal query. Setting `PDO::ATTR_EMULATE_PREPARES => false` forces true server-side binary preparation directly inside MySQL, eliminating all emulation edge cases (such as multibyte character encoding exploits).

---

### Question 7: Contrast Cross-Site Scripting (XSS) with SQL Injection. Why is input sanitization alone insufficient for XSS prevention?

#### 🎓 Academic Answer:
While **SQL Injection** targets the backend database interpreter by breaking query grammar, **Cross-Site Scripting (XSS)** targets the client's browser DOM by injecting malicious JavaScript.

| Metric | SQL Injection (SQLi) | Cross-Site Scripting (XSS) |
| :--- | :--- | :--- |
| **Execution Environment** | Database Server (MySQL Engine) | Client Browser (V8 / JavaScript Engine) |
| **Attacker Objective** | Data exfiltration, schema drop, auth bypass | Session cookie theft, keystroke logging, phishing |
| **Primary Defense** | Server-side Prepared Statements | Context-Aware Output Encoding (`htmlspecialchars`) |

**Why Input Sanitization Fails for XSS:**
Sanitizing input at ingress (e.g., stripping `<script>` tags on `POST`) is flawed because:
1. **Context Dependency:** A payload can be harmless in plain HTML text but fatal inside an attribute (`<input value="<?= $val ?>">`), within a URI (`<a href="<?= $val ?>">`), or inside an inline script block.
2. **Filter Evasion:** Attackers bypass regex filters using novel encodings, nested tags (`<scr<script>ipt>`), or event handlers (`<img src=x onerror=alert(1)>`).

**The Solution: Context-Aware Output Encoding:**
Data must be encoded at egress based on the HTML context. In AgriConnect, every dynamic output is wrapped in:
```php
htmlspecialchars($variable, ENT_QUOTES, 'UTF-8');
```
This converts special characters into inert HTML entities:
- `&` $\rightarrow$ `&amp;`
- `<` $\rightarrow$ `&lt;`
- `>` $\rightarrow$ `&gt;`
- `"` $\rightarrow$ `&quot;`
- `'` $\rightarrow$ `&#039;`

The browser parses the entity strictly as display text, completely neutralizing script execution.

---

### Question 8: If server-side validation is strictly required for security, why invest engineering effort into client-side JavaScript validation (`validation.js`)?

#### 🎓 Academic Answer:
In full-stack engineering, client-side and server-side validation serve two entirely distinct, non-overlapping architectural objectives:

1. **Client-Side Validation (`js/validation.js`): User Experience & Bandwidth Optimization**
   - *Immediate Feedback Loop:* Informs the farmer that their phone number has 9 digits instead of 10 within milliseconds as they type (`input` / `blur` event), without requiring a full round-trip network request.
   - *Server Load Conservation:* Filters out trivially incomplete or malformed requests before they hit web server processes and consume database connections.
   - *Graceful Accessibility:* Highlights invalid DOM nodes and scrolls the viewport smoothly to the first error.

2. **Server-Side Validation (`auth/register.php`): Security & Integrity Boundary**
   - *The Zero-Trust Boundary:* Client-side JavaScript can be trivialized, disabled, or completely bypassed by an adversary using tools like `cURL`, Postman, or intercepting proxies (Burp Suite / OWASP ZAP).
   - *Business Rule Authority:* Checks that require database inspection (such as verifying if an email address is already registered, or verifying if an auction tender has passed its deadline) can **only** be executed authoritatively on the server.

**Conclusion:** Client-side validation is an **ergonomic UX enhancement**; server-side validation is an **uncompromising security imperative**. Neither is dispensable.

---

### Question 9: Why are fast cryptographic hash functions like MD5 and SHA-256 obsolete for password storage? How does BCRYPT solve the problem?

#### 🎓 Academic Answer:
**The Vulnerability of Fast Hashing Algorithms:**
`MD5`, `SHA-1`, and `SHA-256` are general-purpose cryptographic hash functions designed for digital signatures and data integrity verification. Their primary design goal is **maximum throughput and minimal CPU cycles**.

Modern consumer Graphics Processing Units (GPUs) and specialized ASIC miners can compute over **100 billion SHA-256 hashes per second**. If an attacker exfiltrates an MD5-hashed database, they can compute precomputed rainbow tables or brute-force standard passwords in minutes.

**How BCRYPT Operates:**
BCRYPT is an adaptive password-hashing function based on Bruce Schneier's Blowfish cipher, designed specifically to be computationally expensive. It incorporates three vital security properties:

1. **Built-in Cryptographic Salt:**
   - A unique 128-bit random salt is generated for every password and stored within the hash string (e.g., `$2y$10$...`).
   - This ensures two users with identical passwords (`Farmer@123`) produce radically different hash strings, rendering Rainbow Table attacks entirely ineffective.
2. **Configurable Work Factor (Cost Parameter):**
   - BCRYPT uses an exponential iteration parameter ($2^{\text{cost}}$). In AgriConnect:
     ```php
     password_hash($pwd, PASSWORD_BCRYPT); // Default cost = 10 (1024 iterations)
     ```
   - As hardware capabilities advance over time, engineers can increase the work factor to 12 or 14, proportionally escalating the computation time and keeping brute-force attacks mathematically infeasible.
3. **Constant-Time Verification:**
   - `password_verify()` compares hashes in constant time to thwart side-channel **timing attacks**.

---

### Question 10: In web application architecture, contrast synchronous and asynchronous execution. Where does each paradigm apply in AgriConnect?

#### 🎓 Academic Answer:
**Synchronous Execution (Blocking Model):**
- In a synchronous request-response model, the client thread initiates a network call and halts execution, waiting for the server to process the request, execute database queries, and return the full HTML document.
- *Where AgriConnect Uses Synchronous Processing:*
  - Primary navigation, authentication redirects, and role dashboard initialization (`login.php`, `farmer/dashboard.php`). Synchronous rendering ensures that authenticated state, CSRF tokens, and security headers are fully established before the page layout is displayed to the user.

**Asynchronous Execution (Non-Blocking Model):**
- In an asynchronous model, tasks are dispatched to an event loop or background worker thread, allowing the main execution thread to remain responsive to user interactions without freezing.
- *Where AgriConnect Uses Asynchronous Principles:*
  - **Client-Side:** In `js/estimator.js`, recalculation of harvest profit margins occurs asynchronously on the UI thread via non-blocking event dispatchers (`addEventListener('input')`). DOM updates occur instantaneously without page reloads.
  - **Search & Filtering:** In `js/filter.js`, array searching and sorting across procurement tenders occurs in-memory, updating card visibility without blocking user scrolling or typing.
  - **Enterprise Expansion (Stage 3):** Long-running tasks—such as transmitting SMS notifications to 4,000 farmers via Twilio or processing high-resolution satellite land imagery—are decoupled from HTTP requests and dispatched to asynchronous background message queues (e.g., RabbitMQ or Redis Workers).
