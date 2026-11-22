# Bukid Basket — Agent Guide

Portable project context for AI coding tools. This file is the single source of truth.

## MANDATORY: document every change (hard rule — no exceptions)

**Every change an AI agent makes to this repository MUST be documented in `docs/`.**
1. Append an entry to `docs/changelog.md` (Date, What changed, Why, Impact).
2. Update any affected topic doc (`docs/data-model.md`, `docs/diagrams/erd.mmd`, `docs/scope.md`, `docs/deployment.md`, `docs/project-overview.md`).
3. Name the updated docs in your final summary.

> If code changed and `docs/` did not, the task is incomplete.

## Product

Academic capstone ("SIA") by **EJ and Kiel** for **Bukid Basket**, a **fictional** farm-goods seller in Dasmariñas City, Cavite, Philippines (vegetables & greens, fruits, meat & poultry, eggs, rice & grains). Two interfaces only — **customer and admin**:
- **Storefront (`views/shop/`)** — browse the catalog by category and dish, product detail with live ledger stock and price per pack, browser-storage basket, checkout (GCash or COD, morning delivery slot), order tracking (`/track` + `/receipt/{code}`, gated by owner/admin or a last-4-phone-digits token), and saved delivery details (`/account`).
- **Admin (`views/admin/`)** — role-gated (`ADMIN`) dashboard with collected sales, orders board (confirm → send out → mark delivered, bulk confirm/cancel, 2h unpaid-GCash release), stock desk, append-only ledger, today's prices, soft-deleted product CRUD, and categories.

There are **no packer or driver roles, no trips, no handoff PIN and no bag labels**. They were removed on 2026-11-22; `scripts/verify.php` fails if that code comes back.

Order flow: `PENDING → CONFIRMED → READY ("Out for delivery") → COMPLETED`, or `CANCELLED`. COD orders auto-confirm at checkout. GCash counts as collected on confirm; COD on delivery.

## Tech stack

- **Vanilla PHP 8.2+** (zero Composer packages, front controller in `public/index.php`).
- **MySQL 8.0+ / MariaDB 10.4+** via `PDO` (`pdo_mysql`, `ATTR_EMULATE_PREPARES => false`, InnoDB `utf8mb4`).
- **Zero-build frontend** — `public/assets/app.css` (rice-sack print design system, see `DESIGN.md`) + `public/assets/app.js`. Font: Archivo from Google Fonts.
- **Env:** Windows / PowerShell (use `;` not `&&`). Local XAMPP path: `C:\xampp\php\php.exe` and `C:\xampp\mysql\bin\mysql.exe`.

## Non-negotiable rules

- **Money is integer `INT` centavos**, never float (`₱299.00 → 29900`). Parse peso inputs via `parse_peso_input()`, format only at display via `peso()`.
- **Stock changes always go through `stock_movements`** (`INSERT` only; never `UPDATE`/`DELETE`). Current stock = `SUM(quantity)`.
- **Products & variants are soft-deleted** (`active = 0`), never hard-deleted.
- **Instantiate `PDO` only inside `src/db.php`** (`db()` singleton).
- **Customer + admin only.** Do not reintroduce `PACKER`/`DRIVER` roles, `trips`, `trip_id` or `handoff_pin`.

## Commands

- Local dev server: `& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public public/index.php`
- Create the database: `& 'C:\xampp\mysql\bin\mysql.exe' -u root -e "CREATE DATABASE IF NOT EXISTS bukidbasket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`
- Import schema (drops and recreates every table): `Get-Content database/schema.sql -Raw | & 'C:\xampp\mysql\bin\mysql.exe' -u root bukidbasket`
- Seed demo data: `& 'C:\xampp\php\php.exe' database/seed.php`
- **Verify (run once at the very end of a task): `& 'C:\xampp\php\php.exe' scripts/verify.php`** (`php -l` + static footgun scan).
- DB integrity check: `& 'C:\xampp\php\php.exe' scripts/check-db.php`
