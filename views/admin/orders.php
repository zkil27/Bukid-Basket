<?php
declare(strict_types=1);
/** @var string $statusFilter @var array $orders @var array $counts */
$tabs = ['ALL', 'PENDING', 'CONFIRMED', 'READY', 'COMPLETED', 'CANCELLED'];
$canBulkAdvance = in_array($statusFilter, OFFICE_ADVANCE, true);
?>
<div class="stack">
  <div class="admin-head">
    <div>
      <h1 class="display page-title">Orders</h1>
      <p class="admin-sub">Move each order along: confirm it, send it out for delivery, then mark it delivered. Cash on delivery counts as collected when it's delivered.</p>
    </div>
    <div style="min-width:220px;flex:0 1 280px;">
      <label for="orders-search" class="sr-only">Filter orders</label>
      <input type="search" id="orders-search" class="input" placeholder="Filter by code, name, barangay">
    </div>
  </div>

  <nav class="filter-pills" aria-label="Order status">
    <?php foreach ($tabs as $t):
      $lbl = $t === 'ALL' ? 'All' : STATUS_META[$t]['label'];
    ?>
      <a href="/admin/orders?status=<?= e($t) ?>" class="pill <?= $statusFilter === $t ? 'active' : '' ?>"<?= $statusFilter === $t ? ' aria-current="page"' : '' ?>>
        <?= e($lbl) ?> <span class="count"><?= (int) ($counts[$t] ?? 0) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="bulk-bar">
    <div class="row">
      <label class="check-line small"><input type="checkbox" id="orders-select-all"> <strong>Select all shown</strong></label>
      <span id="orders-sel-count" class="small muted" aria-live="polite">0 selected</span>
    </div>
    <div class="row">
      <?php if ($canBulkAdvance): ?>
        <button type="button" class="btn btn-primary btn-sm" id="bulk-advance-btn" data-from="<?= e($statusFilter) ?>">
          <?= e(STATUS_META[$statusFilter]['nextLabel']) ?> selected
        </button>
      <?php else: ?>
        <span class="small muted">Open a status tab to move several orders at once.</span>
      <?php endif; ?>
      <button type="button" class="btn btn-danger btn-sm" id="bulk-cancel-btn">Cancel selected</button>
    </div>
  </div>

  <div class="card table-wrap" style="padding:0;">
    <table class="data">
      <thead>
        <tr>
          <th style="width:36px;"><span class="sr-only">Select</span></th>
          <th>Order</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Deliver to</th>
          <th class="num">Total</th>
          <th class="num">Next step</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$orders): ?>
          <tr><td colspan="7"><div class="empty-note" style="margin:8px 0;">No orders here right now.</div></td></tr>
        <?php else: foreach ($orders as $o):
          $sm = STATUS_META[$o['status']];
          $pm = payment_status_of($o['payment'], $o['status']);
          $search = strtolower($o['code'] . ' ' . $o['customer'] . ' ' . $o['delivery']['name'] . ' ' . $o['delivery']['barangay']);
        ?>
          <tr data-order-row data-search="<?= e($search) ?>">
            <td><input type="checkbox" class="order-chk" value="<?= e($o['id']) ?>" aria-label="Select <?= e($o['code']) ?>"></td>
            <td>
              <button type="button" class="btn btn-ghost btn-sm tabular" style="padding:2px 6px;font-weight:900;" data-open-order="<?= e($o['id']) ?>" data-code="<?= e($o['code']) ?>" data-status="<?= e($o['status']) ?>">
                <?= e($o['code']) ?>
              </button>
              <div class="tiny muted"><?= e(fmt_datetime($o['createdAt'])) ?></div>
            </td>
            <td>
              <?= tone_chip($sm['label'], $sm['tone']) ?>
              <?php if ($o['stale']): ?><div style="margin-top:4px;"><span class="chip chip-red">Unpaid <?= STALE_GCASH_HOURS ?>h+</span></div><?php endif; ?>
            </td>
            <td><?= tone_chip($pm['label'], $pm['tone']) ?></td>
            <td class="small">
              <strong><?= e($o['delivery']['name']) ?></strong> · <?= e($o['delivery']['barangay']) ?><br>
              <span class="muted tabular"><?= e($o['delivery']['day'] ? fmt_day($o['delivery']['day']) : '—') ?> · <?= e($o['delivery']['slot']) ?></span>
            </td>
            <td class="num tabular"><strong><?= e(peso((int) $o['total'])) ?></strong></td>
            <td class="num">
              <?php if (in_array($o['status'], OFFICE_ADVANCE, true)): ?>
                <button type="button" class="btn btn-primary btn-sm" data-adv-id="<?= e($o['id']) ?>" data-adv-from="<?= e($o['status']) ?>"><?= e($sm['nextLabel']) ?></button>
              <?php else: ?>
                <span class="small muted">Done</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="order-detail-modal" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="order-detail-code">
  <div class="modal-card stack" id="order-detail-body"></div>
</div>

<script>
const STATUS_LABEL = <?= json_encode(array_map(static fn($m) => $m['done'], STATUS_META)) ?>;
const searchEl = document.getElementById('orders-search');
searchEl?.addEventListener('input', () => {
  const q = searchEl.value.trim().toLowerCase();
  document.querySelectorAll('[data-order-row]').forEach(tr => tr.classList.toggle('hidden', !!q && !tr.dataset.search.includes(q)));
});

const selected = () => [...document.querySelectorAll('.order-chk:checked')].map(c => c.value);
function updateSelCount() {
  document.getElementById('orders-sel-count').textContent = `${selected().length} selected`;
}
document.getElementById('orders-select-all')?.addEventListener('change', ev => {
  document.querySelectorAll('[data-order-row]:not(.hidden) .order-chk').forEach(c => { c.checked = ev.target.checked; });
  updateSelCount();
});
document.addEventListener('change', ev => { if (ev.target.classList.contains('order-chk')) updateSelCount(); });

document.getElementById('bulk-advance-btn')?.addEventListener('click', async ev => {
  const ids = selected();
  if (!ids.length) return BB.toast('Select at least one order first.', 'err');
  ev.target.disabled = true;
  try {
    const r = await BB.post('/api/admin/orders/advance-bulk', { orderIds: ids, from: ev.target.dataset.from });
    if (ev.target.dataset.from === 'PENDING') await BB.stampConfirm('Confirmed');
    BB.toast(`Updated ${r.count} order(s).`, 'ok');
    location.reload();
  } catch (e) { BB.toast(e.message, 'err'); ev.target.disabled = false; }
});

document.getElementById('bulk-cancel-btn')?.addEventListener('click', async () => {
  const ids = selected();
  if (!ids.length) return BB.toast('Select at least one order first.', 'err');
  const reason = prompt(`Why are you cancelling ${ids.length} order(s)? Their items go back on the shelf.`, 'Cancelled by admin');
  if (!reason) return;
  try {
    const r = await BB.post('/api/admin/orders/cancel-bulk', { orderIds: ids, reason });
    BB.toast(`Cancelled ${r.count} order(s).`, 'ok');
    location.reload();
  } catch (e) { BB.toast(e.message, 'err'); }
});

async function adminOrderAction(url, payload) {
  try { await BB.post(url, payload); location.reload(); }
  catch (e) { BB.toast(e.message, 'err'); }
}

document.addEventListener('click', async ev => {
  const adv = ev.target.closest('[data-adv-id]');
  if (adv) {
    adv.disabled = true;
    try {
      await BB.post('/api/admin/orders/advance', { orderId: adv.dataset.advId, from: adv.dataset.advFrom });
      if (adv.dataset.advFrom === 'PENDING') await BB.stampConfirm('Confirmed');
      location.reload();
    } catch (e) { BB.toast(e.message, 'err'); adv.disabled = false; }
    return;
  }

  const act = ev.target.closest('[data-order-action]');
  if (act) {
    const { orderAction, id, from, to } = act.dataset;
    if (orderAction === 'revert') return adminOrderAction('/api/admin/orders/revert', { orderId: id, from, to });
    if (orderAction === 'cancel') {
      const reason = prompt('Why are you cancelling this order? Its items go back on the shelf.');
      if (reason) adminOrderAction('/api/admin/orders/cancel', { orderId: id, reason });
    }
    return;
  }

  if (ev.target.closest('[data-close-modal]') || ev.target.id === 'order-detail-modal') {
    document.getElementById('order-detail-modal').classList.add('hidden');
    return;
  }

  const openBtn = ev.target.closest('[data-open-order]');
  if (!openBtn) return;
  const { openOrder: id, code, status: st } = openBtn.dataset;
  const modal = document.getElementById('order-detail-modal');
  const body = document.getElementById('order-detail-body');
  modal.classList.remove('hidden');
  body.innerHTML = '<p class="muted">Loading order…</p>';
  try {
    const d = await BB.post('/api/admin/orders/detail', { orderId: id });
    const undo = { CONFIRMED: ['PENDING', 'Undo confirm'], READY: ['CONFIRMED', 'Undo send out'], COMPLETED: ['READY', 'Undo delivered'] }[st];
    let controls = '';
    if (undo) controls += `<button class="btn btn-sm" data-order-action="revert" data-id="${BB.esc(id)}" data-from="${st}" data-to="${undo[0]}">${undo[1]}</button>`;
    if (st !== 'COMPLETED' && st !== 'CANCELLED') controls += `<button class="btn btn-danger btn-sm" data-order-action="cancel" data-id="${BB.esc(id)}">Cancel order</button>`;
    body.innerHTML = `
      <div class="row-between">
        <h2 class="code-big" id="order-detail-code">${BB.esc(code)}</h2>
        <button class="btn btn-sm" data-close-modal>Close</button>
      </div>
      <dl class="small stack-sm" style="margin:0;">
        <div><dt class="label">Phone</dt><dd style="margin:0;">${BB.esc(d.phone)}</dd></div>
        <div><dt class="label">Address</dt><dd style="margin:0;">${BB.esc(d.address)}</dd></div>
        <div><dt class="label">Account</dt><dd style="margin:0;">${BB.esc(d.email)}</dd></div>
      </dl>
      <div class="stack-sm">
        <h3 class="caps" style="margin:0;">History</h3>
        <div class="timeline">
          ${d.events.slice().reverse().map(e => `
            <div class="timeline-item">
              <span><strong>${BB.esc(STATUS_LABEL[e.to] || e.to)}</strong> · ${BB.esc(e.actor)}${e.note ? ` <span class="muted">· ${BB.esc(e.note)}</span>` : ''}</span>
              <span class="muted tabular" style="white-space:nowrap;">${BB.esc(e.at)}</span>
            </div>`).join('')}
        </div>
      </div>
      ${controls ? `<div class="row">${controls}</div>` : ''}
    `;
  } catch (e) { body.innerHTML = `<div class="banner banner-err">${BB.esc(e.message)}</div><button class="btn btn-sm" data-close-modal>Close</button>`; }
});
document.addEventListener('keydown', ev => {
  if (ev.key === 'Escape') document.getElementById('order-detail-modal')?.classList.add('hidden');
});
</script>
