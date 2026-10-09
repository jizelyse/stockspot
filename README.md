# StockSpot: Inventory Management for Leonny G's (Greenwoods Branch)

A web-based inventory system that works alongside a retail shop's existing POS. It tracks stock by batch (with expiry
dates), warns about low stock and expiring items, logs every stock movement, manages suppliers and purchase orders,
and lets rotating staff leave shift-handover notes.

Academic project: Web Systems and Technologies, Mapúa University. Author: Jizandra Elyse Q. Miaral (2024 106 863).

## Features
- Login with roles (Owner / Staff), lockout after repeated failures, session timeout
- Products, categories, suppliers (add / edit / search / filter / archive)
- Stock in, stock out, waste, and count adjustments with **batch + expiry tracking** (first-expiry-first-out)
- Dashboard: low-stock alerts, expiring-soon alerts, shift notes
- Full audit log of every movement (who, what, when, why)
- Reorder suggestions grouped by supplier, purchase orders (Draft / Ordered / Received), receiving updates stock
- CSV import of sales (all-or-nothing validation)
- Reports (low stock, expiry, movement summary, wastage) with CSV download
- User management (Owner), change password
- Responsive layout (phone / tablet / desktop)

## Tech stack
PHP 8.3 (no framework, PDO prepared statements) &middot; MariaDB 10.11 / MySQL &middot; Apache &middot; Docker Compose &middot;
Caddy (automatic HTTPS) &middot; Terraform (Azure) &middot; GitHub Actions (CI)

## Run it locally (easiest: Docker)
```bash
git clone <your repo url> stockspot && cd stockspot
docker compose up --build
# open http://localhost:8080
```
Demo logins (sample data): `owner / Owner@123` and `staff / Staff@123`. **Change these after first login** (Account page / Users page).

## Run it without Docker
1. Install PHP 8.1+ with `pdo_mysql`, and MySQL/MariaDB.
2. Create a database and a user, then import `database/schema.sql` and `database/seed.sql`.
3. Set environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (or edit the defaults in `includes/config.php`).
4. Serve the `public/` folder, e.g. `php -S 127.0.0.1:8080 -t public`.

On shared hosting: upload `includes/` and `public/` keeping the same relative layout (`public` as the web root, or
move the contents of `public/` to `public_html/` and keep `includes/` one level up), import the two SQL files with
phpMyAdmin, and edit the DB values in `includes/config.php`.

## Tests
```bash
python3 tests/run_tests.py            # 75 automated functional/security tests against http://127.0.0.1:8080
python3 tests/capture_screenshots.py  # captures UI screenshots (needs: pip install playwright && playwright install chromium)
```
The tests expect a freshly seeded database. CI runs them on every push (`.github/workflows/ci.yml`).

## Deploy to the cloud (Azure, free student credits)
See **DEPLOY.md** for the full step-by-step guide (Terraform + Docker + HTTPS + backups).

## Project layout
```
public/        web root (pages + assets)         includes/   shared PHP (config, db, auth, stock logic, layout)
database/      schema.sql, seed.sql              tests/      automated tests + screenshot script
docker/        Apache, PHP, MariaDB, Caddy config, backup script
terraform/     Azure infrastructure as code      .github/    CI/CD workflows
```

## Security notes
Prepared statements everywhere; output escaping; CSRF tokens on every POST; password hashing (`password_hash`);
session cookies HttpOnly + SameSite (+ Secure behind HTTPS); security headers incl. CSP; role checks on the server;
input validation on every form; uploads limited to small CSV files.
