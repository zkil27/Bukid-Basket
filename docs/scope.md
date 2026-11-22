# Scope Document

**Status:** FROZEN
**Owner:** Project lead. Changes require agreement from the whole team.

---

## 1. Client profile

**Business name:** Bukid Basket

**Made by:** EJ and Kiel.

**What they sell:** Farm goods from around Cavite: vegetables & greens, fruits,
meat & poultry, eggs, and rice & grains, sold by weight, bundle, tray, piece or
sack.

**Size and setup:** A small seller with one pick-up counter in Dasmariñas City,
run by two admins. Delivers the next morning within the city. Has a Facebook page
used for orders and price announcements.

**How they operate today:**
- Orders come in through Facebook Messenger and walk-ins.
- Stock is tracked on a whiteboard, counted every morning when the harvest
  arrives and adjusted after each sale.
- Prices change with the market and the season; updates are announced through
  social media posts.
- Order history is logged by hand in a notebook.

**Pain points:**
1. No single source of truth for stock; overselling happens when Messenger
   orders clash with walk-in sales.
2. Price updates mean editing several posts; customers keep asking "is this
   still available?" or "how much is rice today?"
3. Order history is lost once the notebook is full.
4. No record of why stock changed: spoilage, bruising and miscounts are
   invisible.
5. The owners cannot see daily or weekly sales without tallying receipts.

**Users of the system:**
- **Customer** — browses the catalog, views product detail with live stock and
  price per pack, builds a basket, checks out (choosing a payment method), tracks
  an order by code, and sees their own order history.
- **Admin** — manages products and packs, adjusts stock with reasons, moves
  orders through their statuses, reviews the stock ledger, and sees a basic
  dashboard summary. There are no other staff roles.

---

## 2. In scope

### Storefront `(shop)`

| Feature | Done means |
|---|---|
| Product listing | Seeded products render from the database with category filter and dish filter. |
| Category browsing | Selecting a category jumps to that shelf; the URL reflects it. |
| Product detail | Single product page showing packs (weight, bundle, tray, sack), price, and stock. |
| Search | Text match on product name and description. Basic. Not fuzzy, not ranked. |
| Customer auth | Register and log in with credentials; session persists. |
| Customer order history | Logged-in customer can view their own past orders and statuses. |
| Shopping basket | Client-side basket: add/remove items, adjust quantity, running subtotal, and a delivery-fee rule (free over ₱500). Basket state lives in the browser. |
| Checkout flow | One-page checkout: delivery information, morning delivery slot, payment method, order review. GCash orders start `PENDING` (on hold); COD orders auto-confirm to `CONFIRMED`. |
| Payment method selection | Customer picks GCash or Cash on Delivery. UI only — shows demo GCash instructions. No real capture, no integration. |
| Order tracking | Customer enters an order code (plus the last 4 digits of the delivery phone when not signed in as the order's owner) and sees its status against a fixed set of stages (Placed → Confirmed → Out for delivery → Delivered). |

### Admin panel `(admin)`

| Feature | Done means |
|---|---|
| Role-gated access | A customer account hitting any `/admin` route is rejected. Verified by actually logging in as each role. |
| Product list | Table of all products with search and category filter. |
| Product create / edit | Form creates and updates a product and its variants, including price in centavos and SKU. |
| Product archive | Soft-delete / deactivate. Products are never hard-deleted — orders reference them. |
| Category management | Admin can create and rename categories. |
| Stock adjustment | Admin can adjust pack stock with a reason; writes a `stock_movements` row. Remaining stock lots are evaluated FIFO-style, and stock at least 7 days old is flagged **Sell first** for admin review only. |
| Stock history | Read-only ledger view per variant showing every movement, newest first. |
| Order list & status update | Table of orders with status, payment state (`GCash · On hold`, `COD · On delivery`, etc.), and total. Admin moves each order Pending → Confirmed → Out for delivery → Delivered (one at a time or in bulk), can undo the last step, and cancels stale or open orders with a reason. |
| Dashboard summary | Today's order count, collected sales (`GCash` on confirm, `COD` on delivery) with `on hold` / `on delivery` sub-totals, orders to send out, and sold-out / low-stock / sell-first / stale GCash alerts on the /admin landing page. |

### Documentation deliverables

- This scope document.
- ERD, derived from `database/schema.sql` (`docs/diagrams/erd.mmd`).
- Use-case diagram.
- Level-0 and Level-1 data flow diagrams.
- SRS sections 1–3 (see `docs/srs-outline.md`).

---

## 3. Out of scope

This section exists so that on Day 9, when someone suggests adding a feature,
the answer is a link rather than an argument. **Nothing here gets built before
September 5, and most of it never gets built at all.**

**Never in this project:**
- Real payment processing. No card capture, no GCash/Maya/Stripe/PayPal
  integration, no stored payment credentials. Orders end at a record with status
  `PENDING` — payment is handled outside the system. The checkout collects a
  chosen payment method (GCash / COD) and shows static payment instructions, but
  captures and settles nothing.
- Real shipping or courier APIs — no courier integration and no live GPS
  delivery tracking. The order tracker shows internal order status only.
- Staff roles beyond admin: no packer or driver screens, no delivery trips,
  no handoff PINs, no bag labels (removed on 2026-11-22).
- Email or SMS sending of any kind — no order confirmations, no password reset
  emails, no notifications.
- Multi-vendor or marketplace functionality. One client, one catalog.
- Any handling of real customer personal data. All data is seeded and fictional.

**Not before September 5** (revisit only after the vertical slice is done):
- Product reviews and ratings.
- Recommendations, "related products", or anything resembling a recommender.
- Discount codes, promotions, or dynamic pricing. (The only pricing rule in
  scope is the flat free-delivery-over-₱500 threshold shown at checkout.)
- Advanced analytics and revenue reporting. The dashboard may show summary
  counts and simple at-a-glance charts (weekly sales, top sellers), but nothing
  with export, drill-down, or a charting/reporting library.
- Image upload. Seeded products use static or placeholder image URLs.
- Wishlists, saved items, or user profile pages beyond basic account info.
- Mobile app, PWA features, offline support.
- Internationalization or multi-currency. PHP only.
- Automated test suite. Manual verification against the checklist in AGENTS.md.

**Deliberately shallow:**
- Search is a name match, not full-text search.
- Auth is credentials only — no OAuth, no magic links, no 2FA.
- Accessibility: semantic HTML, keyboard focus and reduced-motion support; no formal audit.
- The dashboard is counts, alerts, and simple hand-built bar/progress charts —
  no charting library, no export features.
- The cart and checkout are a client-side flow. There is no server-side cart
  persistence, and payment is method-selection plus static instructions only.

---

## 4. Assumptions

- Graders evaluate a live deployed URL, not a local run.
- Seeded data is acceptable as demonstration data.
- The fictional client is not a real business and no real transactions occur.
- One MySQL/MariaDB database serves as both dev and demo database.

---

## 5. Executive summary (for SRS Section 1)

Bukid Basket is a small farm-goods seller in Dasmariñas City, Cavite that currently
manages orders and inventory by hand through social media and paper logs, which
leads to overselling, lost order history, and no visibility into stock movements
or sales. This project, made by EJ and Kiel, delivers a web-based shop with two
interfaces: a public catalog where customers browse vegetables, fruit, meat, eggs
and rice by category and see live stock and prices, and a role-protected admin
panel where the owners manage products, move orders from confirmation to
delivery, and track every stock change through an append-only ledger. Customers
fill a browser-side basket and check out with a delivery slot and a chosen
payment method, then follow the order on a tracking page. The system is built
with Vanilla PHP 8.2+ (PDO MySQL over InnoDB, no Composer dependencies) and
hand-written CSS/JavaScript, deployable on XAMPP or any standard PHP + MySQL host.
It explicitly excludes real payment processing, courier integrations,
notifications, extra staff roles, and analytics beyond a basic dashboard.

---

## 6. Change log

| Date | Change | Agreed by |
|---|---|---|
| 2026-08-22 | Scope frozen — Hook and Box (fresh seafood) | Lead |
| 2026-09-03 | Aligned scope with the built prototype: moved shopping cart, multi-step checkout, GCash/COD payment-method selection (UI only), and order-status tracking into scope. Real payment settlement and courier integrations remain out of scope. | Lead |
| 2026-10-10 | UI overhaul (`docs/ui-overhaul-plan.md`). No feature leaves or enters scope; recorded here because the implementation adds: schema fields needed by existing in-scope features (order payment method, delivery details, readable order number, product image path, variant soft-delete flag, price-change timestamp); an append-only `OrderStatusEvent` timeline behind order status updates; and four admin flows that are all *stock adjustments* or *order updates* in scope terms — Walk-in sale, Morning count, Today's prices board, order timeline. Speed UX (⌘K palette, shortcuts, bulk confirm, undo) adds no new capability. Product photos are static Unsplash images (no upload). | Pending team agreement |
| 2026-10-10 | Customer self-service order cancellation on the order tracking surface (`/track`) when an order is not yet in delivery (`PENDING` or `CONFIRMED`). Reuses existing append-only `StockMovement` compensating return entries and `OrderStatusEvent` timeline auditing. | Team |
| 2026-11-05 | Customer cancellation now requires signing in as the order's owner (staff may also cancel). Closes a hole where anyone could cancel any order by its sequential code. No capability added. | Pending team agreement |
| 2026-11-05 | Developer tools section (`/admin/dev`): health and region checks, integrity checks, timing probes, add-only test-order scenarios, UI state gallery. Developer-only (ADMIN + `DEV_EMAILS` allowlist, off when unset), hidden from staff navigation; no customer or staff feature added. See `docs/dev-tools.md`. | Pending team agreement |
| 2026-11-07 | Saved delivery details: one phone, address and barangay per customer account, shown and editable on `/account`, prefilled at checkout. Treated as the "basic account info" allowed by section 3. An address book with several addresses stays out of scope. All data remains fictional. | Pending team agreement |
| 2026-11-07 | Order receipts can be reopened, printed and saved at `/receipt/[code]`, linked from checkout and `/track`. A read-only view of the in-scope order history and tracking data, public by order code like `/track`, with no delivery details. Stays out of scope: email or SMS delivery, a PDF library, payment confirmation, official invoices, and receipts for walk-in sales. | Pending team agreement |
| 2026-11-08 | Own-truck delivery replaces the "Lalamove" label: `Trip` records (day, slot, vehicle label, driver, numbered stops), an office Dispatch board, a loading checklist, and a driver trip screen. Still no courier API, no GPS or live location, no route optimisation, no fleet records; "Open in Maps" is a plain link. | Pending team agreement |
| 2026-11-08 | Staff roles `PACKER` and `DRIVER` with phone screens under `/staff` and no admin access. A new `PACKED` order status between Confirmed and On the truck. Roles are assigned by seed/script; no role-management UI. | Pending team agreement |
| 2026-11-08 | Proof of delivery by a 4-digit handoff PIN shown only to the customer; without it the driver logs a reason and who received it. Photo proof, signatures, SMS/OTP stay out of scope (no uploads, no SMS). | Pending team agreement |
| 2026-11-08 | Cash-on-delivery remit record: the driver confirms cash per stop, and the office records the cash handed in per trip with a note when it differs. A handover between staff only: nothing is captured or settled, so the "no real payment processing" rule still holds. | Pending team agreement |
| 2026-11-08 | Delivery day and slot capacity at checkout: orders get a delivery date (before 9 PM → next morning, never Sunday) and each slot takes 12 orders a day. | Pending team agreement |
| 2026-11-07 | Bag labels: confirming an order (one or in bulk) prints a 4×6 in (100×150 mm) bag label in the browser; packers and the office can reprint it. HTML + print CSS + `window.print()`, the same pattern as the printable receipt. Shows code, first name, barangay, delivery day/slot, COD amount or "Paid · GCash", and an item checklist; never the phone, address, stop number or handoff PIN. Stays out of scope: a PDF library, printer drivers or direct thermal-printer integration, barcodes/QR. | Pending team agreement |
| 2026-11-07 | Dev account switcher: an allowlisted developer (ADMIN + `DEV_EMAILS`) can switch to any account in one click to test each role, and switch back. Developer-only and off when `DEV_EMAILS` is unset; no staff or customer capability added. See `docs/dev-tools.md`. | Pending team agreement |
| 2026-11-08 | Private order tracking: `/track` and `/receipt` need the signed-in owner, staff, or the order code plus the last 4 phone digits (which gives a shareable tracking link). Closes enumeration of every order's items and totals by sequential code. No capability added. See `docs/security.md`. | Pending team agreement |
| 2026-11-21 | Operational automation & reporting refinements: collected-sales reporting (`GCash` on confirm, `COD` on delivery) with `on hold`/`on delivery` pipeline sub-totals; compact non-truncating 4×6 bag waybill; removal of admin cash-received remit confirmation (trips close on driver return while drivers keep per-stop COD confirmation); auto-confirmed COD checkout + packer batch label printing; auto-planned/assigned trips on pack in route order; bulk cancel/print and 2h stale GCash release. Still no real payment settlement, courier GPS, or notifications. | Pending team agreement |
| 2026-11-22 | Restructured implementation on `feat/php-mysql` to Vanilla PHP 8.2 + PDO MySQL (zero Composer/Node build step) with full Storefront, Admin, and Staff Pack/Load/Trip parity; `/admin/dev` omitted. | User / Team |
| 2026-11-22 | Rebuilt as **Bukid Basket** (farm goods, made by EJ and Kiel) in a separate copy of the project. Catalog changed from seafood to vegetables, fruit, meat, eggs and rice. **Removed** the packer and driver roles, `/staff` screens, delivery trips and Dispatch board, handoff PINs, bag labels and the `PACKED` status: the admin now moves orders Pending → Confirmed → Out for delivery → Delivered. New "rice-sack print" design across storefront and admin. No capability added. | User |
