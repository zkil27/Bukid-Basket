<?php
declare(strict_types=1);
/** @var array $dash */
$maxRev = 1;
foreach ($dash['days'] as $d) {
    $maxRev = max($maxRev, (int) $d['revenue']);
}
$readyCount = (int) ($dash['pipeline']['READY'] ?? 0);
$confirmedCount = (int) ($dash['pipeline']['CONFIRMED'] ?? 0);
?>
<div class="stack">
  <div class="admin-head">
    <div>
      <h1 class="display page-title">Today</h1>
      <p class="admin-sub"><?= e(fmt_long_day(shop_date())) ?> · Manila time</p>
    </div>
    <div class="row">
      <a href="/admin/orders?status=PENDING" class="btn">Confirm orders</a>
      <a href="/admin/stock" class="btn btn-primary">Record stock</a>
    </div>
  </div>

  <div class="ledger-strip">
    <div>
      <div class="figure-label">Collected today</div>
      <div class="figure-value"><?= e(peso((int) $dash['todayRevenue'])) ?></div>
      <div class="figure-note">GCash counts on confirm, cash on delivery</div>
    </div>
    <div>
      <div class="figure-label">Still to collect</div>
      <div class="figure-value"><?= e(peso((int) $dash['onHoldRevenue'] + (int) $dash['onDeliveryRevenue'])) ?></div>
      <div class="figure-note tabular">GCash on hold <?= e(peso((int) $dash['onHoldRevenue'])) ?> · COD <?= e(peso((int) $dash['onDeliveryRevenue'])) ?></div>
    </div>
    <div>
      <div class="figure-label">Orders today</div>
      <div class="figure-value"><?= (int) $dash['todayCount'] ?></div>
      <div class="figure-note">Placed today, not cancelled</div>
    </div>
    <div>
      <div class="figure-label">To send out</div>
      <div class="figure-value"><?= $confirmedCount ?></div>
      <div class="figure-note"><a href="/admin/orders?status=CONFIRMED">Confirmed orders to deliver</a></div>
    </div>
  </div>

  <div class="pipeline" aria-label="Orders by status">
    <?php foreach (['PENDING', 'CONFIRMED', 'READY', 'COMPLETED', 'CANCELLED'] as $st):
      $m = STATUS_META[$st];
    ?>
      <a href="/admin/orders?status=<?= e($st) ?>"><?= tone_chip($m['label'], $m['tone']) ?><strong><?= (int) ($dash['pipeline'][$st] ?? 0) ?></strong></a>
    <?php endforeach; ?>
  </div>

  <section class="card stack-sm" aria-labelledby="pending-title">
    <div class="row-between">
      <h2 id="pending-title" class="display section-title">Waiting for confirmation · <?= (int) $dash['pendingCount'] ?></h2>
      <?php if ($dash['staleGcashIds']): ?>
        <button type="button" class="btn btn-danger btn-sm" id="release-stale-btn" data-ids="<?= e(json_encode($dash['staleGcashIds'])) ?>">
          Release <?= count($dash['staleGcashIds']) ?> unpaid GCash (<?= STALE_GCASH_HOURS ?>h+)
        </button>
      <?php endif; ?>
    </div>
    <?php if (!$dash['pendingOrders']): ?>
      <p class="muted" style="margin:0;">Nothing waiting. Every order is confirmed.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Order</th><th>Customer</th><th>Slot</th><th class="num">Total</th><th>Waiting</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($dash['pendingOrders'] as $o): ?>
              <tr>
                <td><strong class="tabular"><?= e($o['code']) ?></strong><div class="tiny muted"><?= e(PAYMENT_LABEL[$o['payment']] ?? $o['payment']) ?></div></td>
                <td><?= e($o['customer']) ?><div class="tiny muted"><?= (int) $o['items'] ?> item<?= (int) $o['items'] === 1 ? '' : 's' ?></div></td>
                <td class="small tabular"><?= e($o['slot']) ?></td>
                <td class="num tabular"><strong><?= e(peso((int) $o['total'])) ?></strong></td>
                <td>
                  <span class="small muted"><?= e(ago($o['createdAt'])) ?></span>
                  <?php if ($o['stale']): ?><div><span class="chip chip-red">Over <?= STALE_GCASH_HOURS ?>h</span></div><?php endif; ?>
                </td>
                <td class="num"><button type="button" class="btn btn-primary btn-sm" data-confirm-pending="<?= e($o['id']) ?>">Confirm</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($readyCount > 0): ?>
    <div class="banner"><strong><?= $readyCount ?> order<?= $readyCount === 1 ? '' : 's' ?> out for delivery.</strong> <a href="/admin/orders?status=READY">Mark them delivered</a> once they arrive.</div>
  <?php endif; ?>

  <div class="grid-2">
    <section class="card stack-sm" aria-labelledby="week-title">
      <div class="row-between">
        <h2 id="week-title" class="display section-title">Collected, last 7 days</h2>
      </div>
      <div class="bar-chart" role="img" aria-label="Collected sales for each of the last 7 days">
        <?php foreach ($dash['days'] as $idx => $d):
          $pct = max(3, (int) round(((int) $d['revenue'] / $maxRev) * 100));
        ?>
          <div class="bar-col" title="<?= e(fmt_date($d['start']) . ': ' . peso((int) $d['revenue']) . ' from ' . $d['orders'] . ' orders') ?>">
            <span class="bar-val"><?= $d['revenue'] > 0 ? e(peso((int) $d['revenue'])) : '—' ?></span>
            <div class="bar-fill <?= $idx === 6 ? 'today' : '' ?>" style="height:<?= $pct ?>%;"></div>
            <span class="bar-day"><?= $idx === 6 ? 'Today' : e(fmt_weekday($d['start'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card stack-sm" aria-labelledby="top-title">
      <h2 id="top-title" class="display section-title">Best sellers, 7 days</h2>
      <?php if (!$dash['topSellers']): ?>
        <p class="muted" style="margin:0;">No collected sales in the last 7 days yet.</p>
      <?php else: ?>
        <table class="data">
          <thead><tr><th>Product</th><th class="num">Units</th><th class="num">Collected</th></tr></thead>
          <tbody>
            <?php foreach ($dash['topSellers'] as $s): ?>
              <tr>
                <td><?= e(split_name($s['name'])['local']) ?></td>
                <td class="num tabular"><?= (int) $s['qty'] ?></td>
                <td class="num tabular"><strong><?= e(peso((int) $s['revenue'])) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>

  <div class="grid-3">
    <section class="card stack-sm" aria-labelledby="out-title">
      <h2 id="out-title" class="caps" style="margin:0;">Sold out · <?= count($dash['outOfStock']) ?></h2>
      <?php if (!$dash['outOfStock']): ?>
        <span class="small muted">Everything on sale has stock.</span>
      <?php else: ?>
        <div class="alert-list">
          <?php foreach (array_slice($dash['outOfStock'], 0, 6) as $v): ?>
            <div><span><?= e(split_name($v['productName'])['local']) ?> · <?= e($v['name']) ?></span><span class="chip chip-red">0</span></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card stack-sm" aria-labelledby="low-title">
      <h2 id="low-title" class="caps" style="margin:0;">Running low (<?= LOW_STOCK_AT ?> or fewer) · <?= count($dash['lowStock']) ?></h2>
      <?php if (!$dash['lowStock']): ?>
        <span class="small muted">Nothing is running low.</span>
      <?php else: ?>
        <div class="alert-list">
          <?php foreach (array_slice($dash['lowStock'], 0, 6) as $v): ?>
            <div><span><?= e(split_name($v['productName'])['local']) ?> · <?= e($v['name']) ?></span><span class="chip chip-yellow tabular"><?= (int) $v['stock'] ?> left</span></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card stack-sm" aria-labelledby="sale-title">
      <h2 id="sale-title" class="caps" style="margin:0;">Sell first (<?= TO_SALE_AFTER_DAYS ?>+ days old) · <?= count($dash['toSale']) ?></h2>
      <?php if (!$dash['toSale']): ?>
        <span class="small muted">No stock older than <?= TO_SALE_AFTER_DAYS ?> days.</span>
      <?php else: ?>
        <div class="alert-list">
          <?php foreach (array_slice($dash['toSale'], 0, 6) as $v): ?>
            <div><span><?= e(split_name($v['productName'])['local']) ?> · <?= e($v['name']) ?></span><span class="chip chip-yellow tabular"><?= (int) $v['oldestDays'] ?>d · <?= (int) $v['stock'] ?> left</span></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
document.addEventListener('click', async ev => {
  const btn = ev.target.closest('[data-confirm-pending]');
  if (!btn) return;
  btn.disabled = true;
  try {
    await BB.post('/api/admin/orders/advance', { orderId: btn.dataset.confirmPending, from: 'PENDING' });
    await BB.stampConfirm('Confirmed');
    location.reload();
  } catch (e) { BB.toast(e.message, 'err'); btn.disabled = false; }
});
const staleBtn = document.getElementById('release-stale-btn');
if (staleBtn) {
  staleBtn.addEventListener('click', async () => {
    const ids = JSON.parse(staleBtn.dataset.ids || '[]');
    if (!confirm(`Cancel ${ids.length} unpaid GCash order(s) and put their items back on the shelf?`)) return;
    try {
      const r = await BB.post('/api/admin/orders/cancel-bulk', { orderIds: ids, reason: 'Unpaid GCash released after ' + <?= STALE_GCASH_HOURS ?> + 'h' });
      BB.toast(`Released ${r.count} unpaid GCash order(s).`, 'ok');
      location.reload();
    } catch (e) { BB.toast(e.message, 'err'); }
  });
}
</script>
