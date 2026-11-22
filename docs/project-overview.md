# Bukid Basket — Project Overview (Plain-Language Guide)

This is the "explain it like I'm not a programmer" guide to our project. If you
get asked to describe the project, defend a decision, or point at where
something lives, the answer is somewhere on this page. Read it once out loud
before the check and you'll be fine.

The deeper technical docs live next to this file (`scope.md`, `data-model.md`,
`deployment.md`, etc.). This page is the map; those are the territory.

**Made by:** EJ and Kiel.

---

## 1. What is this project, in one breath?

**Bukid Basket is an online shop for a made-up farm-goods seller in Dasmariñas
City, Cavite.** It sells vegetables and greens, fruit, meat and poultry, eggs,
and rice and grains, and delivers the next morning. It's a school project
(course "SIA"). The business is fictional, the customers are fictional, and no
real money ever changes hands. We are graded on a **live website link**, not on
the code running on someone's laptop.

Think of it as two websites sharing one database:

1. **The storefront** — what a customer sees. Browse the harvest, see prices and
   how much stock is left, add things to a basket, and check out.
2. **The admin panel** — what the two shop owners see. Confirm orders, send them
   out for delivery, mark them delivered, change prices, record stock, and look
   at the stock ledger. Only admin accounts can get in.

There are only two kinds of account: **customer** and **admin**. (An earlier
version had packer and driver phone screens, delivery trips and handoff PINs.
We removed all of that to keep the project simple.)

### The real-world problem we're solving

The pretend shop runs on a whiteboard and a notebook. They take orders over
Facebook Messenger, count stock by hand every morning when the harvest comes in,
and announce price changes in social media posts. That causes real headaches:

- They sell the same tray of eggs twice because Messenger orders and walk-in
  sales don't talk to each other.
- Nobody remembers *why* stock is low: was it a sale, spoilage, or a miscount?
- Old orders vanish once the notebook fills up.

Our system fixes those by keeping everything in one place with a proper record.

---

## 2. The single most important feature (say this in the demo)

If you remember only one thing, remember the **stock ledger**.

Instead of storing "we have 30 trays of eggs" as a number that gets overwritten,
we store a **running list of every change**: "+38 morning harvest", "−2 sold",
"−1 cracked tray", and so on. The current stock is just those numbers added up.

Why this matters:

- It's like a **bank statement** for inventory. You never erase a line; if you
  make a mistake, you add a correcting line, exactly like accounting does.
- It answers the shop's real pain point: *"why is stock at 3 when we received
  10?"* The answer is always in the list.
- Most student projects just show a number. Ours shows the *history*.

The word for this list is a **ledger**, and it is **append-only**: you can only
add to it, never edit or delete. Those two phrases are worth memorizing.

---

## 3. Who uses it

| User | What they can do |
|---|---|
| **Customer** | Browse the catalog, filter by category or by dish (sinigang, adobo, tinola…), view a product's price and stock for each pack size, fill a basket, check out with GCash or cash on delivery and a morning delivery slot, track an order by its code, reopen and print its receipt, see their own past orders, and keep saved delivery details that fill in checkout. |
| **Admin** (EJ and Kiel) | Everything a customer can't: confirm orders, send them out for delivery, mark them delivered (one at a time or in bulk), undo the last step, cancel orders with a reason, release unpaid GCash orders after 2 hours, record restocks, write-offs, walk-in sales and the morning count, read the stock ledger, set today's prices, and add, edit or archive products and categories. The dashboard shows money collected today (GCash on confirm, cash on delivery) and what's still to collect. |

The only thing separating them is a label on their account called a **role**
(`CUSTOMER` or `ADMIN`). A customer who opens an admin page gets sent to the
sign-in page, and every admin action checks the role again on the server.

### How an order moves

1. **Placed.** The customer checks out. Cash-on-delivery orders are confirmed
   straight away; GCash orders wait in **To confirm** until an admin sees the
   payment.
2. **Confirmed.** An admin confirms it (a green "Confirmed" stamp lands on the
   screen). It now waits in **To send out**.
3. **Out for delivery.** On the morning of the delivery, the admin presses
   **Send out**.
4. **Delivered.** When it arrives, the admin presses **Mark delivered**. Cash on
   delivery counts as collected at this point.

The customer's tracking page shows the same four steps. Delivery itself never
touches the stock ledger: stock left the shelf when the order was placed, and a
cancelled order puts it back with a new ledger line.

A few behaviours worth knowing:

- **Tracking is private.** Order codes count up (BB-0001, BB-0002…), so a code
  alone doesn't open an order. It opens for the customer who placed it (signed
  in), an admin, or anyone who enters the code **and the last 4 digits of the
  delivery phone**. See `docs/security.md`.
- **Receipts can be reopened** at `/receipt/BB-0005` with the same access rule.
  They say clearly that they are not official receipts.
- **Cancelling needs a sign-in.** Only the order's own customer (or an admin)
  can cancel, and only before it goes out for delivery.
- **The stock ledger page shows the newest 300 entries** for the chosen item and
  period. Balances are always exact.

---

## 4. How the information is organized (the database, in plain words)

We split the information into **eight tables**:

1. **User** — a person with an account. We never store the password itself,
   only a scrambled "hash". Customers can keep a saved phone and address; each
   order keeps its own copy, so changing the saved address never rewrites a past
   order.
2. **Category** — a shelf like "Vegetables & Greens" or "Rice & Grains".
3. **Product** — something customers recognize, like "Dinorado (Premium Rice)".
   A product has **no price and no stock** by itself.
4. **ProductVariant** (a "pack") — a specific way to buy it: "Dinorado 1kg",
   "5kg", or "Sack 25kg". **Price and stock live here**, because each pack costs
   a different amount and sells separately.
5. **StockMovement** — the ledger from Section 2. One row per stock change.
6. **Order** — a customer's purchase: who, when, status, total, delivery details
   and payment method. Shown as a code like **BB-0005**.
7. **OrderItem** — one line in an order ("2 × Kamatis 500g at ₱45 each").
8. **OrderStatusEvent** — the order's timeline: one row each time its status
   changes (who, when, and any note). Like the ledger, it is never edited.

### How they relate

- One **Category** has many **Products**.
- One **Product** has many **Variants** (packs).
- One **User** has many **Orders**.
- One **Order** has many **OrderItems** and many **OrderStatusEvents**.
- One **Variant** has many **StockMovements** and appears in many **OrderItems**.

---

## 5. Decisions the prof will probably ask about

### "Why is money stored as a whole number instead of ₱299.00?"

Computers are bad at decimal math: add enough prices with decimals and totals
drift by a centavo. So we store money as a **whole number of centavos**: ₱299.00
becomes `29900`. We only turn it back into "₱299" when we show it on screen.

### "Why the stock ledger instead of just a stock number?"

A single number tells you *what* stock is, never *how it got there*. The ledger
is an audit trail, and untraceable stock is the client's main problem.

### "Why do you hide products instead of deleting them?"

Old orders point at the products that were bought. Deleting one would break the
order history, so we switch `active` off instead. That's a **soft delete**.

### "Why split Product and Variant?"

Because price and stock belong to a *specific pack*, not to the idea of
"dinorado rice". Keeping them separate avoids one price for every size or
copy-pasting the name across sizes.

### "Why only customer and admin?"

A two-person shop doesn't need separate packer and driver accounts. Fewer roles
means fewer screens to test, and every order step is still recorded with who did
it and when.

---

## 6. What we deliberately did NOT build (this is a strength, not a gap)

Point at `docs/scope.md` if pressed. On purpose, we do **not** do:

- **Real payments.** Checkout lets the customer pick GCash or cash on delivery
  and shows demo instructions, but it captures no money.
- **Couriers, delivery trips or live GPS.** The admin just moves the order to
  "Out for delivery" and "Delivered".
- **Packer or driver accounts**, handoff PINs or printed bag labels.
- **Email or text notifications.** None at all.
- **Photo uploads.** Products use stock photos chosen from a list.
- **An automated test suite.** We test by clicking through it ourselves, plus
  `scripts/verify.php` and `scripts/check-db.php`.

---

## 7. The tools we used (and why, in one line each)

- **PHP 8.2+** — page routing, server-rendered pages, sign-in and actions, with
  zero third-party packages.
- **MySQL / MariaDB** — the database, through PHP's `PDO`, storing centavos as
  whole numbers and keeping the append-only ledger.
- **PHP sessions & bcrypt** — secure sign-ins and password hashing.
- **Hand-written CSS and JavaScript** (`public/assets/`) — the "rice-sack print"
  look (see `DESIGN.md`), the basket drawer, the lot-number stamp when you add
  something, and printable receipts. No build step.

---

## 8. Where everything lives (repo tour)

| Folder / file | What's in it |
|---|---|
| `public/index.php` | Front controller and router for the storefront, admin and `/api/*` actions. |
| `public/assets/` | `app.css` (design system) and `app.js` (basket, stamp, checkout). |
| `public/products/`, `public/scenes/` | Stock photos (credits in `docs/design/photo-credits.md`). |
| `database/` | `schema.sql` (tables), `catalog-data.php` (the farm catalog), `seed.php` (demo data). |
| `src/` | `env.php`, `db.php` (database connection), `meta.php` (money, time, status and role rules), `auth.php`, `http.php`. |
| `src/services/` | `stock.php`, `catalog.php`, `orders.php`, `dashboard.php`. |
| `views/` | Page templates: `layouts/`, `shop/`, `admin/`, `partials/` (brand seal, product card). |
| `scripts/` | `verify.php` (syntax + rule checks) and `check-db.php` (database integrity checks). |
| `docs/` | Documentation and diagrams. |

---

## 9. How to demo it (the 6-minute story)

The full script is in `docs/demo-runbook.md`. The short version:

1. **Open the live link.** Browse the harvest, filter "For sinigang", open a
   product and switch pack sizes. *"Every price and stock count comes from the
   database."*
2. **Add to basket** and point at the red "Lot" stamp and the basket count.
   Check out with cash on delivery, then open the tracking page.
3. **Sign in as admin** (`kiel@bukidbasket.ph`). Send that order out, then mark
   it delivered. *"Cash on delivery counts as collected only now."*
4. **Record a walk-in sale, then open the Stock ledger.** The new line sits on
   top with its reason, who did it and the running balance. Press **Reverse** on
   it. *"We fix mistakes by adding a line, never by editing one."*
5. **Show the `docs/` folder**: scope, diagrams, requirements.
6. **Close:** what's built, what's next, and what's intentionally left out.

---

## 10. Quick glossary

- **Role** — the `ADMIN` or `CUSTOMER` label that controls access.
- **Variant / pack** — a specific size of a product; where price and stock live.
- **Centavos (integer)** — how we store money to avoid rounding errors.
- **Ledger / append-only** — the stock history you add to but never edit.
- **Soft delete** — hiding a product instead of erasing it, to protect old orders.
- **Seed** — the script that loads demo data.
- **SKU** — a unique code for one exact pack, like `RICE-DINORADO-25`.
- **CRUD** — Create, Read, Update, Delete.

---

*If any answer here ever disagrees with the code, the code and
`database/schema.sql` win, and this file should be updated.*
