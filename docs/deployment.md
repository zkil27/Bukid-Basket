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

## 6. Live hosting: FourSys (SSH deploy)

The graded site runs at **https://orange-cobra-186442.foursyscloud.com** (FourSys, a HestiaCP panel, PHP 8.3 FPM).

**Layout on the server** (`~/web/orange-cobra-186442.foursyscloud.com/`):

| Path | Holds |
|---|---|
| `private/bukidbasket/` | App code (`src/`, `views/`, `database/`, `scripts/`, `public/`), the production `.env.local` and a `REVISION` file. It can't be reached over HTTP, but it is inside PHP's `open_basedir`. |
| `public_html/` | A copy of `public/` plus a shim `index.php` that loads `private/bukidbasket/public/index.php`. |

**One-time setup per computer.** Ask a teammate to add your SSH public key in the FourSys panel (or to `~/.ssh/authorized_keys`), then add this to `~/.ssh/config`:

```
Host bukidbasket
    HostName ssh.foursyscloud.com
    Port 65002
    User fs000031
    IdentityFile ~/.ssh/bukidbasket_deploy
    IdentitiesOnly yes
```

**Deploy** (sends the committed code only; uncommitted edits are never deployed):

```powershell
.\scripts\deploy.ps1          # deploys HEAD
.\scripts\deploy.ps1 master   # deploys a branch, tag or commit
```

**Automatic deploy on push.** `.github/workflows/deploy.yml` deploys every push to `master`. It runs `verify.php`, then `scripts/deploy.sh` with the pushed commit, then checks that the home page returns HTTP 200. To redeploy without pushing, open the Actions tab, pick **Deploy**, then **Run workflow**. It needs two repository secrets (*Settings → Secrets and variables → Actions*):

| Secret | Value |
|---|---|
| `FOURSYS_SSH_KEY` | Private key of the GitHub-only deploy key (`bukidbasket-github-actions`; its public half is in the server's `~/.ssh/authorized_keys`). |
| `FOURSYS_KNOWN_HOSTS` | Output of `ssh-keyscan -p 65002 ssh.foursyscloud.com`, so the runner refuses an impostor server. |

To revoke GitHub's access, delete the `bukidbasket-github-actions` line from the server's `~/.ssh/authorized_keys`.

From Git Bash: `bash scripts/deploy.sh`. The script `git archive`s `public src views database scripts`, uploads them to `~/tmp` on the server, and runs `scripts/server-deploy.sh`. That script runs `php scripts/verify.php` (it stops on failure), rsyncs the code into `private/bukidbasket/`, rsyncs `public/` into `public_html/`, and writes the shim. It never sends or overwrites the server's `.env.local`.

**Production `.env.local`** lives only at `private/bukidbasket/.env.local` (mode 600). It sets `APP_ENV=production` and an `APP_SECRET` generated on the server. `DB_NAME`, `DB_USER` and `DB_PASS` come from the database created in the FourSys panel (MariaDB 11.4; the panel adds the `fs000031_` prefix to both names). Use `DB_HOST=127.0.0.1`, not `localhost`: with `localhost`, PDO tries a Unix socket at the wrong path and fails with `SQLSTATE[HY000] [2002] No such file or directory`. To import or reset the database:

```bash
ssh bukidbasket
cd ~/web/orange-cobra-186442.foursyscloud.com/private/bukidbasket
mysql -u "$DB_USER" -p "$DB_NAME" < database/schema.sql   # drops and recreates every table
php database/seed.php
php scripts/check-db.php
```

---

## 7. Separate copy of the old project

Bukid Basket was copied from the Hook & Box (seafood) project into its own folder and git repository on 2026-11-22. It uses its own database name, `bukidbasket`, so the old `hookandbox` database is never touched by this app's schema import or seed.
