# Demo Runbook

For EJ and Kiel. Rehearse this end to end before the panel, from the live URL,
on the machine you'll present from. Not a mental walkthrough: click every step.

---

## The rule

**Demo from the live URL. Never from localhost.** A cold local server in front
of a panel is how a working project looks broken.

## Before the demo

- [ ] Live URL loads and the harvest shows seeded products and photos
- [ ] Re-seed the demo database the day before (`php database/seed.php`) so the
      order history and "collected today" numbers look fresh
- [ ] Admin sign-in tested **today** (`kiel@bukidbasket.ph` / `admin123`)
- [ ] Browser tabs open: storefront, admin sign-in, repo, `docs/` folder
- [ ] Signed out of any admin session, so the sign-in step is real
- [ ] Basket empty in the demo browser
- [ ] Notifications silenced; phone hotspot ready
- [ ] A screen recording of the full flow saved locally as a last resort

## The script — about 6 minutes

**1. Storefront (75s)**
Open the live URL. Point at the net-weight stamp: the 25 kg sack price and the
sacks left are live numbers. Tap **For sinigang** to filter, open **Liempo**,
switch 1kg ↔ 500g and watch price and stock change. Press **Add to basket**:
a red "In basket · Lot ####" stamp lands on the photo and the basket count ticks.
> "Everything on screen comes from the database. The stock you see is the sum of
> the stock ledger."

**2. Checkout and tracking (60s)**
Sign in as `maria@example.com` / `customer123`. Check out with **cash on
delivery** and a morning slot. The tracking page opens on **Confirmed**: cash on
delivery confirms itself. Show **Receipt**.
> "Placing that order already took the stock off the shelf, as a ledger line."

**3. Admin (75s)**
Sign out, sign in as `kiel@bukidbasket.ph`. On **Today**, confirm a waiting GCash
order (the green Confirmed stamp). Open **Orders**, find Maria's order and press
**Send out**, then **Mark delivered**. Back on **Today**, "Collected today" went
up by her total.
> "Cash on delivery only counts as collected when it's delivered. GCash counts
> when we confirm the payment."

**4. Stock and the ledger (90s)** — *the important one*
On **Stock desk**, record a **Walk-in sale**. Open **Stock ledger**: the new line
sits on top with the reason, who did it and the running balance. Press
**Reverse** on it to add a correction.
> "Stock changes are an append-only ledger. Every change has a reason and a
> time; mistakes are fixed with a new line, never by editing one."

Don't rush this part.

**5. Prices (30s)**
On **Today's prices**, change one price and save. Refresh the storefront: the
product now says "Price updated this morning".

**6. Documentation and close (45s)**
Show `docs/`: scope with its out-of-scope list, the ERD, this runbook. Say
plainly what's built, what's next, and what's deliberately left out.

---

## Likely questions

**"Why no real payment?"** Checkout records GCash or cash on delivery and shows
demo instructions; nothing is captured. Deliberately out of scope
(`docs/scope.md`).

**"Why store money as integers?"** Floating point can't represent most decimal
fractions exactly, so sums drift. Whole centavos keep every total exact.

**"Why a stock ledger?"** A number says what stock is, never how it got there.
The ledger is the audit trail.

**"Where are the delivery riders?"** A two-person shop doesn't need them. The
admin marks each order out for delivery and delivered; every step is still
recorded with who and when.

**"Who made it?"** EJ and Kiel. The git history backs it up.

---

## If something breaks mid-demo

Say what you're doing and keep moving: "That's a known issue, let me show you
the next part." Come back only if there's time. If the whole site is down, play
the screen recording.
