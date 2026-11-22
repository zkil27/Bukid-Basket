<?php
declare(strict_types=1);
/** @var ?array $user @var ?array $slots */
?>
<div class="stack">
  <div>
    <h1 class="display page-title">Checkout</h1>
    <p class="muted" style="margin:8px 0 0;">Morning delivery in Dasmariñas City. Free delivery on orders ₱500 and up.</p>
  </div>

  <?php if (!$user): ?>
    <div class="print-panel stack" style="max-width:620px;">
      <h2 class="display section-title">Sign in to place your order</h2>
      <p class="ink-2" style="margin:0;">Your basket stays saved in this browser while you sign in or create an account.</p>
      <div class="row">
        <a href="/login?next=/checkout" class="btn btn-accent btn-lg">Sign in</a>
        <a href="/register?next=/checkout" class="btn btn-lg">Create an account</a>
      </div>
    </div>
  <?php else: ?>
    <div class="grid-2" style="align-items:start;">
      <form id="checkout-form" class="card stack">
        <fieldset class="stack-sm" style="border:0;padding:0;margin:0;">
          <legend class="display section-title" style="margin-bottom:10px;">Deliver to</legend>
          <div>
            <label class="label" for="co-name">Receiver's name</label>
            <input id="co-name" name="name" class="input" required autocomplete="name" value="<?= e($user['name'] ?? '') ?>" placeholder="Maria Santos">
          </div>
          <div>
            <label class="label" for="co-phone">Mobile number</label>
            <input id="co-phone" name="phone" class="input" required autocomplete="tel" inputmode="tel" value="<?= e($user['phone'] ?? '') ?>" placeholder="0917 123 4567">
          </div>
          <div>
            <label class="label" for="co-address">House no., street, block &amp; lot</label>
            <input id="co-address" name="address" class="input" required autocomplete="street-address" value="<?= e($user['address'] ?? '') ?>" placeholder="Blk 5 Lot 12, Mabini St.">
          </div>
          <div>
            <label class="label" for="co-barangay">Barangay (Dasmariñas City)</label>
            <select id="co-barangay" name="barangay" class="select" required>
              <option value="">Choose a barangay</option>
              <?php foreach (BARANGAYS as $b): ?>
                <option value="<?= e($b) ?>" <?= ($user['barangay'] ?? '') === $b ? 'selected' : '' ?>><?= e($b) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <label class="check-line small">
            <input type="checkbox" name="saveDetails" checked>
            <span>Save these details to my account</span>
          </label>
        </fieldset>

        <hr>
        <fieldset class="stack-sm" style="border:0;padding:0;margin:0;">
          <legend class="display section-title" style="margin-bottom:10px;">Delivery slot · <?= e($slots ? fmt_day($slots['day']) : fmt_day(delivery_date_for())) ?></legend>
          <?php if ($slots === null): ?>
            <div class="banner banner-err">We can't check slot availability right now. Try again in a few minutes.</div>
          <?php else:
            $firstOpen = null;
            foreach ($slots['slots'] as $i => $s) {
                if ((int) $s['left'] > 0) { $firstOpen = $i; break; }
            }
            foreach ($slots['slots'] as $i => $s):
              $left = (int) $s['left'];
          ?>
            <label class="choice">
              <span class="row">
                <input type="radio" name="slot" value="<?= e($s['slot']) ?>" <?= $i === $firstOpen ? 'checked' : '' ?> <?= $left <= 0 ? 'disabled' : '' ?>>
                <strong class="tabular"><?= e($s['slot']) ?></strong>
              </span>
              <span class="chip <?= $left <= 0 ? 'chip-gray' : ($left <= 3 ? 'chip-yellow' : 'chip-green') ?>"><?= $left <= 0 ? 'Full' : "{$left} left" ?></span>
            </label>
          <?php endforeach; endif; ?>
        </fieldset>

        <hr>
        <fieldset class="stack-sm" style="border:0;padding:0;margin:0;">
          <legend class="display section-title" style="margin-bottom:10px;">Payment</legend>
          <label class="choice">
            <span class="row">
              <input type="radio" name="payment" value="COD" checked>
              <span><strong>Cash on delivery</strong><br><span class="small muted">Confirmed right away. Pay when it arrives.</span></span>
            </span>
          </label>
          <label class="choice">
            <span class="row">
              <input type="radio" name="payment" value="GCASH">
              <span><strong>GCash</strong><br><span class="small muted">We confirm once we see your payment.</span></span>
            </span>
          </label>
          <div id="cod-note" class="banner">Please have the exact amount ready when your order arrives.</div>
          <div id="gcash-note" class="banner banner-warn hidden">
            <strong>Demo only:</strong> send the total to Bukid Basket, 0917 888 0000, with your order code. This is a school project, so no real payment is taken. Unconfirmed GCash orders are released after <?= STALE_GCASH_HOURS ?> hours.
          </div>
        </fieldset>

        <button id="checkout-submit-btn" type="submit" class="btn btn-accent btn-lg btn-block" <?= $slots === null ? 'disabled' : '' ?>>Place order</button>
      </form>

      <aside class="print-panel stack-sm" aria-labelledby="summary-title" style="position:sticky;top:96px;">
        <h2 id="summary-title" class="display section-title">Your basket</h2>
        <div id="checkout-items-preview"></div>
      </aside>
    </div>
  <?php endif; ?>
</div>
