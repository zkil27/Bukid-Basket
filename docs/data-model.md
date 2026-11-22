# Data Model

Written for the documentator and anyone who doesn't read SQL. The authoritative
version is `database/schema.sql` (MySQL 8.0+ / MariaDB 10.4+, InnoDB,
`utf8mb4_unicode_ci`) — if the two ever disagree, `database/schema.sql` wins and
this file needs updating.

The ERD (`docs/diagrams/erd.mmd`, Mermaid) is derived from `database/schema.sql`.
Update the Mermaid file in the same commit as any schema change.

---

## The eight tables (`snake_case`)

### `users`
A person with an account. The `role` column is an ENUM — `ADMIN` or
`CUSTOMER` — separating shoppers from the two admins, EJ and Kiel
(`src/meta.php` + `src/auth.php`). There are no packer or driver roles. Passwords are stored bcrypt-hashed in `password_hash`. `phone`,
`address` and `barangay` store the customer's saved delivery details (prefills
checkout, editable on `/account`).

### `categories`
A grouping of products (`name`, unique `slug`), e.g. "Vegetables & Greens", "Rice & Grains".

### `products`
A sellable item as a customer thinks of it (`name`, `description`, static
`image_url` under `/public/products/`, `category_id`, soft-delete `active`).

### `product_variants`
A specific purchasable pack: a weight, bundle, tray or sack (`product_id`, `name`, unique `sku`,
`price_centavos` integer, `price_updated_at`, soft-delete `active`).

### `orders`
Customer purchase with `order_number` (`AUTO_INCREMENT`, shown as `BB-0005`),
`customer_id`, `status`, `payment_method` (`GCASH` | `COD`), delivery snapshot
(`delivery_name`, `delivery_phone`, `delivery_address`, `delivery_barangay`,
`delivery_slot`, `delivery_date`), `delivery_fee_centavos` and `total_centavos`. Status runs
`PENDING → CONFIRMED → READY` (shown as "Out for delivery") `→ COMPLETED`, or
`CANCELLED`; an admin moves every step from the orders board.

### `order_items`
One line on an order (`order_id`, `variant_id`, `quantity`, `price_at_time`
captured in centavos at checkout).

### `stock_movements`
The **append-only** stock ledger (`variant_id`, `quantity`, `reason`, optional
`order_id`, optional `actor_id`, `created_at`). Current stock is always
`SUM(quantity)`; corrections add compensating rows (`UPDATE`/`DELETE` forbidden
by `scripts/verify.php`).

### `order_status_events`
Append-only order timeline (`order_id`, `from_status`, `to_status`, `note`,
`actor_id`, `created_at`).

---

## How stock is read (`src/services/stock.php`)

- **Current stock:** `SUM(quantity)` per `variant_id`.
- **FIFO age (7-day To-sale queue):** MySQL 8 / MariaDB 10.4 CTE + `SUM(quantity) OVER (PARTITION BY variant_id ORDER BY created_at DESC, id DESC)` in `oldest_inflow_by_variant()`.
- **Ledger running balance:** window sum over `stock_movements` in `load_ledger()`.
- `php scripts/check-db.php` cross-checks SQL FIFO results against a pure-PHP chronological replay.

## Indexes (`database/schema.sql`)

| Table | Index | Serves |
|---|---|---|
| `stock_movements` | `(variant_id, created_at)`, `(variant_id, quantity)`, `(order_id)` | Covering stock sums, history, order returns |
| `orders` | `(status, created_at)`, `(created_at)`, `(customer_id, created_at)` | Admin tabs, dashboard, customer history |
| `orders` | `(delivery_date, delivery_slot, status)` | Delivery slot capacity |
| `order_items` | `(order_id)`, `(variant_id)` | Order lines, top sellers |
| `order_status_events` | `(order_id, created_at)`, `(to_status, created_at)` | Order timelines, collected-sales dates |
| `products` / `product_variants` | `(category_id)`, `(product_id)` | Catalog reads |

---

## Design decisions worth defending

These are the questions a panel is most likely to ask. Have the answer ready.

**Why separate Product from ProductVariant?**
Because price and stock are properties of a specific pack (500g, a bundle, a
tray of 30, a 25kg sack), not of the product concept. Flattening them would mean either one price for all sizes, or
duplicating the name and description across every variation.

**Why store money as an integer?**
Floating-point arithmetic cannot represent most decimal fractions exactly, so
sums of prices drift. Storing centavos as whole numbers makes every arithmetic
operation exact. Conversion to a readable peso amount happens only at display time.

**Why a stock ledger instead of just a stock number?**
A single number tells you what stock is, never how it got there. The ledger gives
an audit trail — the difference between a system that tracks inventory and one
that merely displays it. It also means stock discrepancies are diagnosable rather
than mysterious, which is the client's actual pain point.

**Why copy the price onto OrderItem?**
So that historical orders stay historically accurate when prices change.

**Why does a delivery address live on both User and Order?**
They mean different things. `User.address` is where the customer usually wants
delivery, and it can change at any time. `Order.deliveryAddress` is where that
order actually went, so it is a snapshot, for the same reason as `priceAtTime`.
If orders read from the account, moving house would rewrite delivery history.

**Why are products archived rather than deleted?**
Orders reference variants. Deleting a product would orphan or destroy order
history. Archiving hides it from the storefront while preserving the record.
