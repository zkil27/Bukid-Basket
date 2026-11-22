<?php
declare(strict_types=1);
/** @var array $variants */
?>
<div class="stack">
  <div class="admin-head">
    <div>
      <h1 class="display page-title">Today's prices</h1>
      <p class="admin-sub">Type prices in pesos, like 180 or 175.50. Only the rows you change are saved, and the shop shows "Price updated this morning" on them.</p>
    </div>
    <button type="button" class="btn btn-primary btn-lg" id="save-prices-btn">Save changed prices</button>
  </div>

  <div class="card table-wrap" style="padding:0;">
    <table class="data">
      <thead>
        <tr>
          <th>Category</th>
          <th>Product</th>
          <th>Pack</th>
          <th>SKU</th>
          <th class="num">Stock</th>
          <th style="width:160px;">Price (₱)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($variants as $v): ?>
          <tr>
            <td class="small muted"><?= e($v['categoryName']) ?></td>
            <td><strong><?= e(split_name($v['productName'])['local']) ?></strong></td>
            <td>
              <?= e($v['name']) ?>
              <?php if ($v['priceChangedToday']): ?> <span class="chip chip-yellow">Changed today</span><?php endif; ?>
            </td>
            <td class="mono small muted"><?= e($v['sku']) ?></td>
            <td class="num tabular"><?= (int) $v['stock'] ?></td>
            <td>
              <input
                type="text"
                class="input tabular price-input" inputmode="decimal" aria-label="Price in pesos"
                data-vid="<?= e($v['id']) ?>"
                data-orig="<?= e(peso_number((int) $v['price'])) ?>"
                value="<?= e(peso_number((int) $v['price'])) ?>"
              >
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.getElementById('save-prices-btn')?.addEventListener('click', async () => {
  const changes = [];
  document.querySelectorAll('.price-input').forEach(inp => {
    const val = inp.value.trim();
    if (val !== inp.dataset.orig) {
      changes.push({ variantId: inp.dataset.vid, priceInput: val });
    }
  });
  if (!changes.length) return BB.toast("You haven't changed any prices.");
  try {
    const r = await BB.post('/api/admin/prices', { changes });
    BB.toast(`Saved ${r.changed.length} new price(s).`, 'ok');
    location.reload();
  } catch (e) { BB.toast(e.message, 'err'); }
});
</script>
