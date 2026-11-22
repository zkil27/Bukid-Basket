# Deployment Runbook — PHP 8.2 + MySQL (XAMPP & Production Hosting)

Bukid Basket runs as a zero-dependency Vanilla PHP 8.2+ application backed by MySQL 8.0+ or MariaDB 10.4+ (InnoDB, `utf8mb4_unicode_ci`). There is **no Node.js build step** and **no Composer installation**.

---

## 1. Requirements

- **PHP 8.2+** with extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `json`
- **MySQL 8.0+** or **MariaDB 10.4+** (needed for CTE `WITH` and window functions `SUM(...) OVER (...)` used by FIFO stock aging and running ledger balances)
- **Web server:** Apache with `mod_rewrite` (`.htaccess` included in `public/`), Nginx pointing to `public/index.php`, or PHP's built-in server for local development.

---

## 2. Environment Configuration

Copy `.env.example` to `.env.local` (gitignored) and configure your database connection and HMAC secret:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bukidbasket
DB_USER=root
DB_PASS=
APP_SECRET=replace-with-random-64-char-hex
APP_ENV=development
```

Generate a strong secret on any PHP install:

```powershell
php -r "echo bin2hex(random_bytes(32));"
```

Set `APP_ENV=production` on live hosts so internal error details are hidden and `APP_SECRET` is enforced.

---

## 3. Local Setup (Windows / XAMPP)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Create the database and import the schema (the import drops and recreates every table, so it also resets an existing copy):
   ```powershell
   & 'C:\xampp\mysql\bin\mysql.exe' -u root -e "CREATE DATABASE IF NOT EXISTS bukidbasket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   Get-Content database/schema.sql -Raw | & 'C:\xampp\mysql\bin\mysql.exe' -u root bukidbasket
   ```
3. Seed the demo accounts, catalog, 7-day order history, and ledger movements:
   ```powershell
   & 'C:\xampp\php\php.exe' database/seed.php
   ```
4. Verify syntax, footgun rules, and database integrity:
   ```powershell
   & 'C:\xampp\php\php.exe' scripts/verify.php
   & 'C:\xampp\php\php.exe' scripts/check-db.php
   ```
5. Run locally using PHP's built-in web server (point document root at `public/`):
   ```powershell
   & 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public public/index.php
   ```
   Open `http://127.0.0.1:8000` in your browser.

   *(Alternatively, configure an Apache VirtualHost in XAMPP pointing `DocumentRoot` to `<repo>\public` with `AllowOverride All` so `public/.htaccess` handles clean URLs.)*

---

## 4. Production Hosting (cPanel / VPS / Railway / Render / Fly)

1. Point the web server document root strictly to `public/` (never the repo root, so `src/`, `database/`, and `.env.local` are unreachable over HTTP).
2. Provision a MySQL 8+ or MariaDB 10.4+ database and import `database/schema.sql`.
3. Set environment variables (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_SECRET`, `APP_ENV=production`).
4. Run `php database/seed.php` once if seeding demo data.
5. Ensure HTTPS is enabled so session cookies and checkout traffic are encrypted.

---

## 5. Demo Accounts (seeded by `database/seed.php`)

| Role | Email | Password | Lands on |
|---|---|---|---|
| `ADMIN` | `ej@bukidbasket.ph` | `admin123` | `/admin` |
| `ADMIN` | `kiel@bukidbasket.ph` | `admin123` | `/admin` |
| `CUSTOMER` | `maria@example.com` | `customer123` | `/` |

Four more customers (`jose`, `ana`, `pedro`, `liza` `@example.com`, password `customer123`) hold the seeded order history. There are no packer or driver accounts.

---

## 6. Separate copy of the old project

Bukid Basket was copied from the Hook & Box (seafood) project into its own folder and git repository on 2026-11-22. It uses its own database name, `bukidbasket`, so the old `hookandbox` database is never touched by this app's schema import or seed.
