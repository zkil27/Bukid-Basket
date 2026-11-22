# Security notes

Written for EJ, Kiel and the panel. All data in this project is fictional, but
the app is built as if it weren't. If this file and the code disagree, the code
wins: fix this file.

## Order tracking by code

**The risk.** Order codes are sequential (`BB-0001`, `BB-0002`…). If a code
alone opened `/track?code=…` or `/receipt/{code}`, anyone could step through
every number and read each order's items, totals, payment method, delivery slot
and cancellation notes, and count the shop's orders. That is an insecure direct
object reference (IDOR).

**The fix.** `lookup_order()` in `src/services/orders.php`, with the token
helpers in `src/auth.php`. An order opens only for:

1. the signed-in customer who placed it;
2. an admin;
3. anyone holding its **tracking link** (`?t=…`): `track_token()`, an
   HMAC-SHA256 of the order number under `APP_SECRET`. The `/track` form hands
   out the link only after the visitor enters the code **and the last 4 digits
   of the delivery phone** (`/api/track/verify` in `public/index.php`).

A missing order and a wrong code get the same "locked" answer for visitors, so
the form doesn't reveal which order numbers exist. Token comparison uses
`hash_equals`. Receipts never show the phone or address.

## Roles

There are two roles, `CUSTOMER` and `ADMIN` (`src/meta.php`). `GATED_AREAS`
protects `/admin`: the front controller sends anyone else to `/login?denied=1`
before a page renders, and every `/api/admin/*` action calls `require_admin()`
again, so a direct POST can't skip the gate. Packer and driver roles, delivery
trips and handoff PINs were removed on 2026-11-22, and `scripts/verify.php`
fails if any of that code returns.

## Sessions and forms

- Sessions are HttpOnly, `SameSite=Lax`, and the ID is regenerated on sign-in
  and registration (`src/auth.php`).
- Every POST (forms and JSON) must carry the session's CSRF token (`_csrf`
  field or `X-CSRF-Token` header), checked with `hash_equals` in
  `verify_csrf()`.
- Passwords are stored with bcrypt (`password_hash` / `password_verify`).
- All output goes through `e()` (HTML escaping); all SQL uses PDO prepared
  statements with emulation off.

## Residual risk

- **No rate limit on the 4 digits.** One order can be opened in at most 10,000
  guesses. That turns a free sweep of every order into a slow, per-order
  attack, and the data behind it has no name, phone or address. A real limiter
  stays out of scope.
- **Tracking links don't expire.** They are tied to `APP_SECRET`; rotating the
  secret invalidates every link.
- **Admins see everything.** Both admin accounts can read every customer's
  delivery details. Keep the admin passwords private and change the seeded ones
  on any public deployment.
