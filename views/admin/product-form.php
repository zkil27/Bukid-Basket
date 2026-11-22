<?php
declare(strict_types=1);
/** @var ?array $product @var array $categories @var list<string> $images */
$isNew = $product === null;
$variants = $product['variants'] ?? [['id' => '', 'name' => '1kg', 'sku' => '', 'price' => 10000, 'active' => true, 'stock' => 0]];
?>
<div class="stack" style="max-width:780px;">
  <div class="admin-head">
    <h1 class="display page-title"><?= $isNew ? 'Add a product' : 'Edit product' ?></h1>
    <a href="/admin/products" class="btn btn-sm">Back to products</a>
  </div>

  <form id="product-form" class="card stack">
    <input type="hidden" name="id" value="<?= e($product['id'] ?? '') ?>">
    <div class="grid-2">
      <div>
        <label class="label" for="pf-name">Name (Filipino, then English in brackets)</label>
        <input id="pf-name" name="name" class="input" required value="<?= e($product['name'] ?? '') ?>" placeholder="Pechay (Bok Choy)">
      </div>
      <div>
        <label class="label" for="pf-cat">Category</label>
        <select id="pf-cat" name="categoryId" class="select" required>
          <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= ($product['category']['id'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div>
      <label class="label" for="pf-desc">Description</label>
      <textarea id="pf-desc" name="description" class="textarea" rows="2" maxlength="300"><?= e($product['description'] ?? '') ?></textarea>
    </div>
    <div>
      <label class="label" for="pf-img">Photo</label>
      <select id="pf-img" name="imageUrl" class="select">
        <option value="">No image</option>
        <?php foreach ($images as $img): ?>
          <option value="<?= e($img) ?>" <?= ($product['image'] ?? '') === $img ? 'selected' : '' ?>><?= e($img) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <hr>
    <div class="row-between">
      <h2 class="display section-title">Packs</h2>
      <button type="button" class="btn btn-sm" id="add-var-btn">Add a pack</button>
    </div>

    <div id="variants-list" class="stack-sm">
      <?php foreach ($variants as $v): ?>
        <div class="var-row" data-vid="<?= e($v['id'] ?? '') ?>">
          <div>
            <label class="label">Pack name</label>
            <input type="text" class="input v-name" required value="<?= e($v['name']) ?>" placeholder="500g, Bundle, Tray of 12">
          </div>
          <div>
            <label class="label">SKU</label>
            <input type="text" class="input mono v-sku" required value="<?= e($v['sku']) ?>" placeholder="VEG-PECHAY-500">
          </div>
          <div>
            <label class="label">Price (₱)</label>
            <input type="text" class="input tabular v-price" inputmode="decimal" required value="<?= e(peso_number((int) $v['price'])) ?>">
          </div>
          <?php if (empty($v['id'])): ?>
            <div>
              <label class="label">Starting stock</label>
              <input type="number" min="0" class="input tabular v-opening" value="0">
            </div>
          <?php endif; ?>
          <label class="check-line small" style="flex:0 0 auto;min-height:44px;">
            <input type="checkbox" class="v-active" <?= !empty($v['active']) ? 'checked' : '' ?>> On sale
          </label>
        </div>
      <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">Save product</button>
  </form>
</div>

<script>
document.getElementById('add-var-btn')?.addEventListener('click', () => {
  const row = document.createElement('div');
  row.className = 'var-row';
  row.dataset.vid = '';
  row.innerHTML = `
    <div><label class="label">Pack name</label><input type="text" class="input v-name" required placeholder="500g"></div>
    <div><label class="label">SKU</label><input type="text" class="input mono v-sku" required placeholder="VEG-NEW-500"></div>
    <div><label class="label">Price (₱)</label><input type="text" class="input tabular v-price" inputmode="decimal" required value="100"></div>
    <div><label class="label">Starting stock</label><input type="number" min="0" class="input tabular v-opening" value="0"></div>
    <label class="check-line small" style="flex:0 0 auto;min-height:44px;"><input type="checkbox" class="v-active" checked> On sale</label>
  `;
  document.getElementById('variants-list').appendChild(row);
});

document.getElementById('product-form')?.addEventListener('submit', async ev => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const variants = [...document.querySelectorAll('.var-row')].map(r => ({
    id: r.dataset.vid || undefined,
    name: r.querySelector('.v-name').value,
    sku: r.querySelector('.v-sku').value,
    priceInput: r.querySelector('.v-price').value,
    opening: r.querySelector('.v-opening') ? Number(r.querySelector('.v-opening').value || 0) : 0,
    active: r.querySelector('.v-active').checked,
  }));
  try {
    await BB.post('/api/admin/products/save', {
      id: fd.get('id') || undefined,
      name: fd.get('name'),
      categoryId: fd.get('categoryId'),
      description: fd.get('description'),
      imageUrl: fd.get('imageUrl'),
      variants,
    });
    window.location.href = '/admin/products';
  } catch (e) { BB.toast(e.message, 'err'); }
});
</script>
