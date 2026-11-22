<?php
declare(strict_types=1);
/** @var array $variants */
?>
<div class="stack">
  <div class="admin-head">
    <div>
      <h1 class="display page-title">Stock desk</h1>
      <p class="admin-sub">Every change you record here is added to the stock ledger. Nothing is overwritten.</p>
    </div>
    <a href="/admin/ledger" class="btn">Open the ledger</a>
  </div>

  <div class="grid-2" style="align-items:start;">
    <form id="adjust-form" class="card stack-sm">
      <h2 class="display section-title">Restock or write off</h2>
      <div>
        <label class="label" for="adj-var">Product &amp; pack</label>
        <select id="adj-var" name="variantId" class="select" required>
          <?php foreach ($variants as $v): ?>
            <option value="<?= e($v['id']) ?>"><?= e($v['productName']) ?> — <?= e($v['name']) ?> (<?= (int) $v['stock'] ?> on hand)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid-2">
        <div>
          <label class="label" for="adj-reason">Reason</label>
          <select id="adj-reason" name="reason" class="select">
            <?php foreach (ADJUST_REASONS as $r): ?>
              <option value="<?= e($r['id']) ?>" data-sign="<?= (int) $r['sign'] ?>"><?= e($r['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label" for="adj-qty">Change (+ in, − out)</label>
          <input id="adj-qty" name="quantity" type="number" class="input tabular" required value="5">
        </div>
      </div>
      <div>
        <label class="label" for="adj-note">Note (optional)</label>
        <input id="adj-note" name="note" class="input" maxlength="140" placeholder="Morning harvest from Silang, wilted leaves, cracked tray">
      </div>
      <button type="submit" class="btn btn-primary">Record change</button>
    </form>

    <form id="walkin-form" class="card stack-sm">
      <h2 class="display section-title">Walk-in sale</h2>
      <p class="small muted" style="margin:0;">For buyers at the counter. Takes the items out of stock as a "Walk-in sale".</p>
      <div>
        <label class="label" for="wi-var">Product &amp; pack</label>
        <select id="wi-var" name="variantId" class="select" required>
          <?php foreach ($variants as $v): ?>
            <option value="<?= e($v['id']) ?>"><?= e($v['productName']) ?> — <?= e($v['name']) ?> (<?= (int) $v['stock'] ?> on hand)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label" for="wi-qty">Quantity sold</label>
        <input id="wi-qty" name="qty" type="number" class="input tabular" min="1" value="1" required>
      </div>
      <button type="submit" class="btn btn-accent">Record sale</button>
    </form>
  </div>

  <form id="morning-count-form" class="card stack-sm">
    <div class="row-between">
      <div>
        <h2 class="display section-title">Morning count</h2>
        <p class="small muted" style="margin:6px 0 0;">Type what you actually counted on the shelf. Only the rows that differ get a "Morning count" correction.</p>
      </div>
      <button type="submit" class="btn btn-primary">Save count</button>
    </div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Product</th><th>Pack</th><th>SKU</th><th class="num">On record</th><th style="width:130px;">Counted</th></tr></thead>
        <tbody>
          <?php foreach ($variants as $v): ?>
            <tr>
              <td><strong><?= e(split_name($v['productName'])['local']) ?></strong></td>
              <td><?= e($v['name']) ?></td>
              <td class="mono small muted"><?= e($v['sku']) ?></td>
              <td class="num tabular"><?= (int) $v['stock'] ?></td>
              <td>
                <input type="number" min="0" class="input tabular count-input" aria-label="Counted" data-vid="<?= e($v['id']) ?>" value="<?= (int) $v['stock'] ?>">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>

<script>
document.getElementById('adj-reason')?.addEventListener('change', ev => {
  const opt = ev.target.selectedOptions[0];
  const sign = Number(opt?.dataset.sign || 0);
  const qtyEl = document.getElementById('adj-qty');
  const val = Math.abs(Number(qtyEl.value || 1));
  if (sign !== 0) qtyEl.value = String(sign * val);
});

document.getElementById('adjust-form')?.addEventListener('submit', async ev => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  try {
    const r = await BB.post('/api/admin/stock/adjust', {
      variantId: fd.get('variantId'),
      reason: fd.get('reason'),
      quantity: Number(fd.get('quantity')),
      note: fd.get('note') || '',
    });
    BB.toast(`Recorded. Stock went from ${r.before} to ${r.after}.`, 'ok');
    location.reload();
  } catch (e) { BB.toast(e.message, 'err'); }
});

document.getElementById('walkin-form')?.addEventListener('submit', async ev => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  try {
    const r = await BB.post('/api/admin/stock/walkin', {
      variantId: fd.get('variantId'),
      qty: Number(fd.get('qty')),
    });
    BB.toast(`Sale recorded. ${r.after} left.`, 'ok');
    location.reload();
  } catch (e) { BB.toast(e.message, 'err'); }
});

document.getElementById('morning-count-form')?.addEventListener('submit', async ev => {
  ev.preventDefault();
  const counts = [...document.querySelectorAll('.count-input')].map(i => ({
    variantId: i.dataset.vid,
    counted: Number(i.value),
  }));
  try {
    const r = await BB.post('/api/admin/stock/count', { counts });
    BB.toast(`Checked ${r.checked} rows and recorded ${r.adjusted} correction(s).`, 'ok');
    location.reload();
  } catch (e) { BB.toast(e.message, 'err'); }
});
</script>
