<?php
declare(strict_types=1);
/** @var array $ledger @var array $variants @var string $variantId @var int $days @var string $kind */
$rows = $ledger['rows'];
$truncated = $ledger['truncated'];
?>
<div class="stack">
  <div class="admin-head">
    <div>
      <h1 class="display page-title">Stock ledger</h1>
      <p class="admin-sub">Every bunch, kilo and tray in or out, newest first. Rows are never edited or deleted. To fix a mistake, add a correction that reverses it.</p>
    </div>
    <a href="/admin/stock" class="btn btn-primary">Record a change</a>
  </div>

  <form method="get" action="/admin/ledger" class="bulk-bar">
    <label for="lg-variant" class="sr-only">Product</label><select id="lg-variant" name="variant" class="select" style="flex:1;min-width:220px;">
      <option value="">All products</option>
      <?php foreach ($variants as $v): ?>
        <option value="<?= e($v['id']) ?>" <?= $variantId === $v['id'] ? 'selected' : '' ?>>
          <?= e($v['productName']) ?> — <?= e($v['name']) ?> (<?= (int) $v['stock'] ?>)<?= $v['archived'] ? ' [archived]' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label for="lg-days" class="sr-only">Period</label><select id="lg-days" name="days" class="select" style="width:150px;">
      <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 days</option>
      <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 days</option>
      <option value="3650" <?= $days >= 3650 ? 'selected' : '' ?>>All time</option>
    </select>
    <label for="lg-kind" class="sr-only">Type</label><select id="lg-kind" name="kind" class="select" style="width:170px;">
      <?php foreach (['all' => 'All types', 'sale' => 'Sales', 'restock' => 'Restocks', 'cancel' => 'Cancellations', 'loss' => 'Spoilage & damage', 'count' => 'Morning count', 'correction' => 'Corrections'] as $k => $lbl): ?>
        <option value="<?= e($k) ?>" <?= $kind === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Show</button>
  </form>

  <?php if ($truncated): ?>
    <div class="banner banner-warn">Showing the newest <?= LEDGER_LIMIT ?> entries. Pick a product or a shorter period to see the rest.</div>
  <?php endif; ?>

  <div class="card table-wrap" style="padding:0;">
    <table class="data">
      <thead>
        <tr>
          <th>Ref</th>
          <th>When</th>
          <th>Product</th>
          <th class="num">Change</th>
          <th class="num">Balance</th>
          <th>Reason</th>
          <th>By</th>
          <th><span class="sr-only">Correct</span></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8"><div class="empty-note" style="margin:8px 0;">No entries match. Try a longer period or all products.</div></td></tr>
        <?php else: foreach ($rows as $r):
          $q = (int) $r['quantity'];
        ?>
          <tr>
            <td class="small tabular" style="font-weight:800;"><?= e($r['ref']) ?></td>
            <td class="small muted tabular" style="white-space:nowrap;"><?= e(fmt_datetime($r['at'])) ?></td>
            <td class="small">
              <strong><?= e($r['product']) ?></strong> · <?= e($r['variant']) ?><br>
              <span class="mono muted"><?= e($r['sku']) ?></span>
            </td>
            <td class="num tabular <?= $q >= 0 ? 'qty-pos' : 'qty-neg' ?>"><?= $q >= 0 ? '+' . $q : '−' . abs($q) ?></td>
            <td class="num tabular"><strong><?= (int) $r['balance'] ?></strong></td>
            <td class="small">
              <?= e($r['reason']) ?>
              <?php if ($r['orderCode']): ?>
                <a href="/admin/orders?status=ALL" class="small tabular" title="Open orders"><?= e($r['orderCode']) ?></a>
              <?php endif; ?>
            </td>
            <td class="small muted"><?= e($r['actor']) ?></td>
            <td>
              <?php if (!$r['orderCode']): ?>
                <button type="button" class="btn btn-sm" data-counter="<?= e($r['id']) ?>" data-ref="<?= e($r['ref']) ?>">Reverse</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('click', async ev => {
  const btn = ev.target.closest('[data-counter]');
  if (!btn) return;
  if (!confirm(`Add a correction that reverses ${btn.dataset.ref}? The original entry stays in the ledger.`)) return;
  btn.disabled = true;
  try {
    await BB.post('/api/admin/stock/counter', { movementId: btn.dataset.counter, label: 'Correction' });
    BB.toast('Correction added.', 'ok');
    location.reload();
  } catch (e) { BB.toast(e.message, 'err'); btn.disabled = false; }
});
</script>
