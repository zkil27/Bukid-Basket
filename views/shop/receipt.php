<?php
declare(strict_types=1);
/** @var array $lookup @var string $code */
?>
<div class="stack" style="max-width:580px;margin:0 auto;">
  <div class="row-between no-print">
    <a href="/track?code=<?= rawurlencode($code) ?>" class="btn btn-sm">Back to tracking</a>
    <?php if ($lookup['kind'] === 'ok'): ?>
      <button type="button" class="btn btn-primary btn-sm" data-print>Print receipt</button>
    <?php endif; ?>
  </div>

  <?php if ($lookup['kind'] !== 'ok'): ?>
    <div class="banner banner-warn">
      To see this receipt, first open the order on <a href="/track?code=<?= rawurlencode($code) ?>">Track your order</a>.
    </div>
  <?php else:
    $o = $lookup['order'];
  ?>
    <article class="print-panel print-area stack">
      <header class="stack-sm" style="align-items:center;text-align:center;padding-bottom:16px;border-bottom:2px dashed var(--ink);">
        <?= brand_badge() ?>
        <div class="display" style="font-size:34px;">Bukid <span style="color:var(--red);">Basket</span></div>
        <div class="small muted">Farm goods · Dasmariñas City, Cavite</div>
        <div class="code-big" style="margin-top:6px;"><?= e($o['code']) ?></div>
        <div class="small tabular"><?= e(fmt_datetime($o['createdAt'])) ?></div>
      </header>

      <div class="row-between small">
        <span>Status: <strong><?= e(STATUS_META[$o['status']]['shopLabel']) ?></strong></span>
        <span>Delivery: <strong><?= e($o['day'] ? fmt_day($o['day']) : '—') ?> · <?= e($o['slot']) ?></strong></span>
      </div>

      <table class="data">
        <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($o['items'] as $it): ?>
            <tr>
              <td><?= e($it['name']) ?> <span class="muted">· <?= e($it['variant']) ?></span></td>
              <td class="num tabular"><?= (int) $it['qty'] ?></td>
              <td class="num tabular"><?= e(peso((int) $it['price'] * (int) $it['qty'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="stack-sm" style="border-top:2px dashed var(--ink);padding-top:12px;">
        <div class="row-between"><span>Payment</span><strong><?= e(PAYMENT_LABEL[$o['payment']] ?? $o['payment']) ?></strong></div>
        <div class="row-between"><span>Delivery fee</span><span class="tabular"><?= (int) $o['fee'] === 0 ? 'Free' : e(peso((int) $o['fee'])) ?></span></div>
        <div class="row-between" style="font-size:22px;"><strong>Total</strong><strong class="tabular"><?= e(peso((int) $o['total'])) ?></strong></div>
      </div>

      <p class="small muted" style="text-align:center;margin:0;">Made by EJ &amp; Kiel · Academic capstone · fictional demo data, not an official receipt</p>
    </article>
  <?php endif; ?>
</div>
