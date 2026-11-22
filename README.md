# Bukid Basket

Academic capstone (course code "SIA") made by **EJ and Kiel**: an online shop
for a **fictional** farm-goods seller in Dasmariñas City, Cavite, Philippines.
It sells vegetables & greens, fruits, meat & poultry, eggs, and rice & grains,
delivered the next morning. Graded on a live deployed URL. No real business and
no real transactions; all data is seeded and fictional.

The app has two interfaces:

- **Storefront** — browse the catalog by category or dish, see live stock and
  price for every pack size, fill a basket, check out with GCash or cash on
  delivery, track an order, reopen and print its receipt, and save delivery
  details.
- **Admin** — role-gated area to confirm orders, send them out and mark them
  delivered, record stock (restocks, write-offs, walk-in sales, morning count),
  read the append-only stock ledger, set today's prices, and manage products and
  categories.

The load-bearing feature is the **stock ledger**: every stock change writes a
`stock_movements` row with a reason and a timestamp. Rows are never edited or
deleted; corrections are new entries that reverse a mistake.

## Tech stack

- **Vanilla PHP 8.2+**, no Composer packages, front controller `public/index.php`
- **MySQL 8 / MariaDB 10.4+** over PDO
- Hand-written CSS and JavaScript, no build step (`public/assets/`)
- Design system: "rice-sack print", documented in `DESIGN.md`

## Getting started (Windows + XAMPP)

1. Copy `.env.example` to `.env.local` and set the values (`DB_NAME=bukidbasket`,
   and a random `APP_SECRET`).
2. Create the database, import the schema, seed demo data:

   ```powershell
   & 'C:\xampp\mysql\bin\mysql.exe' -u root -e "CREATE DATABASE IF NOT EXISTS bukidbasket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   Get-Content database/schema.sql -Raw | & 'C:\xampp\mysql\bin\mysql.exe' -u root bukidbasket
   & 'C:\xampp\php\php.exe' database/seed.php
   ```

3. Run the dev server and open http://127.0.0.1:8000:

   ```powershell
   & 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public public/index.php
   ```

Demo accounts: `ej@bukidbasket.ph` / `admin123`, `kiel@bukidbasket.ph` /
`admin123` (admins), `maria@example.com` / `customer123` (customer).

## Checks

| Command | What it does |
| --- | --- |
| `php scripts/verify.php` | `php -l` on every file + footgun scan (floats for money, ledger edits, hard deletes, stray PDO, removed packer/driver/trip code) |
| `php scripts/check-db.php` | Read-only database integrity checks (no negative stock, FIFO cross-check, order totals, cancelled orders hold no stock) |

## Conventions

- **Money is integer centavos**, never a float (₱299.00 → `29900`).
- **Stock changes only through new `stock_movements` rows.**
- **Products and packs are soft-deleted** (`active = 0`), never hard-deleted.
- **`PDO` is created only in `src/db.php`.**
- Copy is English; Filipino is used for product and dish names.

## Documentation

- `docs/scope.md` — feature scope (source of truth)
- `docs/data-model.md`, `docs/diagrams/erd.mmd` — tables and relationships
- `docs/deployment.md` — local and production setup
- `docs/project-overview.md` — plain-language project guide
- `docs/demo-runbook.md` — demo script
- `docs/design/photo-credits.md` — photo sources and licenses
- `docs/changelog.md` — every change, newest first
- `AGENTS.md` — rules for AI coding agents
