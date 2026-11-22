<?php
declare(strict_types=1);
/** @var array $categories */
?>
<div class="stack" style="max-width:680px;">
  <div class="admin-head">
    <div>
      <h1 class="display page-title">Categories</h1>
      <p class="admin-sub">Shelves on the shop page. Renaming one changes it everywhere.</p>
    </div>
  </div>

  <form id="create-cat-form" class="bulk-bar">
    <label for="new-cat" class="sr-only">New category name</label><input id="new-cat" type="text" name="name" class="input" required placeholder="New category, e.g. Herbs &amp; Spices" style="flex:1;min-width:200px;">
    <button type="submit" class="btn btn-primary">Add category</button>
  </form>

  <div class="card table-wrap" style="padding:0;">
    <table class="data">
      <thead><tr><th>Name</th><th>Slug</th><th>Products</th><th>Rename</th></tr></thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?></strong></td>
            <td class="mono small muted"><?= e($c['slug']) ?></td>
            <td class="tabular"><?= (int) $c['product_count'] ?></td>
            <td>
              <form class="row rename-cat-form" data-id="<?= e($c['id']) ?>">
                <input type="text" name="name" class="input" value="<?= e($c['name']) ?>" aria-label="New name for <?= e($c['name']) ?>" style="width:200px;">
                <button type="submit" class="btn btn-sm">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.getElementById('create-cat-form')?.addEventListener('submit', async ev => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  try { await BB.post('/api/admin/categories/create', { name: fd.get('name') }); location.reload(); }
  catch (e) { BB.toast(e.message, 'err'); }
});
document.querySelectorAll('.rename-cat-form').forEach(f => {
  f.addEventListener('submit', async ev => {
    ev.preventDefault();
    const fd = new FormData(f);
    try { await BB.post('/api/admin/categories/rename', { id: f.dataset.id, name: fd.get('name') }); BB.toast('Category renamed.', 'ok'); location.reload(); }
    catch (e) { BB.toast(e.message, 'err'); }
  });
});
</script>
