<?php
declare(strict_types=1);
/** @var array $products */
?>
<div class="stack">
  <div class="admin-head">
    <div>
      <h1 class="display page-title">Products</h1>
      <p class="admin-sub">Archiving hides a product from the shop but keeps it in past orders and the ledger. Nothing is ever deleted.</p>
    </div>
    <a href="/admin/products/new" class="btn btn-primary">Add a product</a>
  </div>

  <div class="card table-wrap" style="padding:0;">
    <table class="data">
      <thead>
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th>Packs &amp; prices</th>
          <th>Status</th>
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td>
              <div class="row">
                <?php if ($p['image']): ?>
                  <img src="<?= e($p['image']) ?>" alt="" style="width:52px;height:40px;object-fit:cover;border-radius:4px;border:1.5px solid var(--ink);" width="52" height="40" loading="lazy">
                <?php endif; ?>
                <div>
                  <strong><?= e($p['name']) ?></strong>
                  <?php if ($p['description']): ?><div class="small muted"><?= e($p['description']) ?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td class="small"><?= e($p['category']['name']) ?></td>
            <td class="small">
              <?php foreach ($p['variants'] as $v): ?>
                <div>
                  <strong><?= e($v['name']) ?></strong> · <span class="tabular"><?= e(peso((int) $v['price'])) ?></span> <span class="mono muted"><?= e($v['sku']) ?></span>
                  <span class="muted">(<?= (int) $v['stock'] ?> in stock<?= $v['active'] ? '' : ', hidden' ?>)</span>
                </div>
              <?php endforeach; ?>
            </td>
            <td><?= $p['active'] ? tone_chip('Active', 'green') : tone_chip('Archived', 'gray') ?></td>
            <td class="row">
              <a href="/admin/products/<?= e($p['id']) ?>" class="btn btn-sm">Edit</a>
              <button type="button" class="btn btn-sm" data-toggle-active="<?= e($p['id']) ?>" data-active="<?= $p['active'] ? '0' : '1' ?>">
                <?= $p['active'] ? 'Archive' : 'Restore' ?>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('click', async ev => {
  const btn = ev.target.closest('[data-toggle-active]');
  if (!btn) return;
  btn.disabled = true;
  try {
    await BB.post('/api/admin/products/active', { productId: btn.dataset.toggleActive, active: btn.dataset.active === '1' });
    location.reload();
  } catch (e) { BB.toast(e.message, 'err'); btn.disabled = false; }
});
</script>
