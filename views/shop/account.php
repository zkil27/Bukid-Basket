<?php
declare(strict_types=1);
/** @var array $user @var array|string $orders */
?>
<div class="stack-lg">
  <div class="row-between" style="align-items:flex-end;">
    <div>
      <h1 class="display page-title"><?= e($user['name'] ?: $user['email']) ?></h1>
      <div class="small muted" style="margin-top:6px;"><?= e($user['email']) ?></div>
    </div>
    <form method="post" action="/api/auth/logout">
      <?= csrf_field() ?>
      <button type="submit" class="btn">Sign out</button>
    </form>
  </div>

  <div class="grid-2" style="align-items:start;">
    <section class="stack" aria-labelledby="orders-title">
      <h2 id="orders-title" class="display section-title">My orders</h2>
      <?php if ($orders === 'offline'): ?>
        <div class="banner banner-err">We can't load your orders right now. Please try again in a few minutes.</div>
      <?php elseif (!$orders): ?>
        <div class="empty-note">No orders yet. <a href="/#catalog">See today's harvest</a>.</div>
      <?php else: ?>
        <?php foreach ($orders as $o):
          $meta = STATUS_META[$o['status']];
        ?>
          <article class="card stack-sm">
            <div class="row-between">
              <div class="row">
                <strong class="tabular" style="font-size:20px;font-weight:900;"><?= e($o['code']) ?></strong>
                <?= tone_chip($meta['shopLabel'], $meta['tone']) ?>
              </div>
              <strong class="tabular" style="font-size:18px;"><?= e(peso((int) $o['total'])) ?></strong>
            </div>
            <div class="small muted">
              Placed <?= e(fmt_datetime($o['createdAt'])) ?> · delivery <?= e($o['day'] ? fmt_day($o['day']) : '—') ?>, <?= e($o['slot']) ?> · <?= e(PAYMENT_LABEL[$o['payment']] ?? $o['payment']) ?>
            </div>
            <div class="small ink-2">
              <?= e(implode(', ', array_map(static fn($i) => $i['qty'] . '× ' . split_name($i['name'])['local'] . ' (' . $i['variant'] . ')', $o['items']))) ?>
            </div>
            <div class="row">
              <a href="/track?code=<?= rawurlencode($o['code']) ?>" class="btn btn-sm">Track</a>
              <a href="/receipt/<?= rawurlencode($o['code']) ?>" class="btn btn-sm">Receipt</a>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <form method="post" action="/api/account/delivery" class="print-panel stack-sm" aria-labelledby="delivery-title">
      <?= csrf_field() ?>
      <h2 id="delivery-title" class="display section-title">Delivery details</h2>
      <p class="small muted" style="margin:0 0 6px;">We fill these in for you at checkout. Past orders keep the details they were placed with.</p>
      <div>
        <label class="label" for="acc-phone">Mobile number</label>
        <input id="acc-phone" name="phone" class="input" required autocomplete="tel" inputmode="tel" value="<?= e($user['phone'] ?? '') ?>" placeholder="0917 123 4567">
      </div>
      <div>
        <label class="label" for="acc-address">House no., street, block &amp; lot</label>
        <input id="acc-address" name="address" class="input" required autocomplete="street-address" value="<?= e($user['address'] ?? '') ?>" placeholder="Blk 5 Lot 12, Mabini St.">
      </div>
      <div>
        <label class="label" for="acc-barangay">Barangay</label>
        <select id="acc-barangay" name="barangay" class="select" required>
          <option value="">Choose a barangay</option>
          <?php foreach (BARANGAYS as $b): ?>
            <option value="<?= e($b) ?>" <?= ($user['barangay'] ?? '') === $b ? 'selected' : '' ?>><?= e($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:6px;">Save details</button>
    </form>
  </div>
</div>
