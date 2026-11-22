# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Customers** — households in Dasmariñas City, Cavite, buying vegetables, fruit, meat, eggs and rice for home cooking. They browse by category or by dish, pick a pack size (weight, bundle, tray, piece or sack), fill a basket, and check out with GCash or cash on delivery for a next-morning slot. Mostly on phones.
- **Admins** — the two owners, EJ and Kiel. They confirm orders, send them out, mark them delivered, record stock and set the day's prices, on both desktop and phone. There are no other staff roles.

## Product Purpose

Bukid Basket replaces Messenger orders, a whiteboard stock count and a paper order notebook with one source of truth. Customers see live stock and price per pack; admins move orders through four plain steps and manage stock through an append-only ledger. Success: no overselling, every stock change explained, and daily admin work done fast.

Academic capstone ("SIA") made by EJ and Kiel; the business is fictional and graded on a live deployed URL.

## Positioning

A neighborhood farm-goods seller that runs on an auditable stock ledger: every bunch, kilo, tray or sack in or out has a timestamped reason, and corrections are new entries, never edits. The ledger is the demo centerpiece.

## Operating Context

- The morning harvest arrives from farms around Cavite; stock is counted each morning; prices change with the market and the season.
- Orders flow Pending → Confirmed → Out for delivery → Delivered, or Cancelled. Cash-on-delivery orders confirm themselves; GCash waits for an admin.
- Remaining stock lots are evaluated FIFO; stock 7+ days old is flagged **Sell first** for admin review.
- Delivery is local and next-morning, in three slots of 12 orders each.

## Capabilities and Constraints

- Scope: `docs/scope.md` is authoritative. Customer storefront plus admin only; packer and driver roles, delivery trips, handoff PINs and bag labels were removed on purpose.
- Never: real payment capture, courier or GPS integration, email/SMS, image upload, multi-vendor, automated tests.
- Money is integer centavos; stock changes only via new `stock_movements` rows; products and packs are soft-deleted.
- Dashboard charts are hand-built; no charting library, export or drill-down.
- Stack: vanilla PHP 8.2 + MySQL/MariaDB over PDO, hand-written CSS/JS, no build step.

## Brand Commitments

- Name: **Bukid Basket**. Farm goods, Dasmariñas City, Cavite. Makers credited as **EJ and Kiel** only.
- Visual world: rice-sack flexo print (see `DESIGN.md`): two inks, flexo red and field green, plus key black on sack white.
- Voice: **English copy, with Filipino only for produce and dish names** (Pechay, Kangkong, Liempo, Dinorado; Sinigang, Adobo, Tinola, Pinakbet). No Taglish slogans.
- The storefront should feel satisfying to use (the lot stamp on Add, the basket tick), while the admin stays quiet, intuitive and fast.

## Evidence on Hand

- Seeded fictional catalog (25 products, 39 packs) in `database/catalog-data.php`, seeded by `database/seed.php`.
- Product imagery: real Unsplash photos, one per product plus three scenes, each with its source embedded and listed in `docs/design/photo-credits.md` and on `/credits`.
- No real customers, reviews, testimonials or sales figures exist; demo numbers come from seeded data, never invented claims.

## Product Principles

1. **Truth over decoration** — stock, price and order status shown are always the real ledger-derived values.
2. **The ledger is the story** — every stock change is visible, explained and append-only, in UI and data.
3. **Fast hands** — the most frequent admin actions (confirm, send out, mark delivered, record stock, find a product) take the fewest steps on phone and desktop.
4. **Delight with a job** — every animation confirms an action or shows state; none is decoration alone.

## Accessibility & Inclusion

Respect `prefers-reduced-motion` for all animation; keyboard operability for every admin action; WCAG AA contrast on text and status colors.
