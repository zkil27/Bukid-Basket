<?php
declare(strict_types=1);
/** @var string $codeInput @var ?string $token @var ?array $lookup @var bool $placed */
$routeSteps = [
    'PENDING' => 'Placed',
    'CONFIRMED' => 'Confirmed',
    'READY' => 'Out for delivery',
    'COMPLETED' => 'Delivered',
];
?>
<div class="stack" style="max-width:800px;margin:0 auto;">
  <div>
    <h1 class="display page-title">Track your order</h1>
    <p class="muted" style="margin:8px 0 0;">Enter your order code, for example <strong class="tabular">BB-0021</strong>, to see where it is.</p>
  </div>

  <?php if ($placed): ?>
    <div class="banner banner-ok" role="status"><strong>Order placed. Salamat!</strong> Keep this page or the receipt below to follow your order.</div>
  <?php endif; ?>

  <form method="get" action="/track" class="row">
    <label for="track-code" class="sr-only">Order code</label>
    <input id="track-code" type="text" name="code" class="input tabular" placeholder="BB-0021" value="<?= e($codeInput) ?>" required style="flex:1;min-width:180px;font-weight:800;">
    <button type="submit" class="btn btn-primary">Find order</button>
  </form>

  <?php if ($lookup !== null): ?>
    <?php if ($lookup['kind'] === 'offline'): ?>
      <div class="banner banner-err">We can't reach our order records right now. Please try again in a few minutes.</div>
    <?php elseif ($lookup['kind'] === 'missing'): ?>
      <div class="banner banner-warn">There's no order with the code <strong><?= e($codeInput) ?></strong>. Check the code on your receipt.</div>
    <?php elseif ($lookup['kind'] === 'locked'): ?>
      <div class="card stack-sm">
        <h2 class="display section-title">Is this your order?</h2>
        <p class="ink-2" style="margin:0;">Enter the last 4 digits of the mobile number used for <strong><?= e($codeInput) ?></strong>.</p>
        <form method="post" action="/api/track/verify" class="row">
          <?= csrf_field() ?>
          <input type="hidden" name="code" value="<?= e($codeInput) ?>">
          <label for="last4" class="sr-only">Last 4 digits</label>
          <input id="last4" type="text" name="last4" class="input tabular" maxlength="4" pattern="\d{4}" inputmode="numeric" placeholder="1001" required style="width:120px;font-weight:800;letter-spacing:0.2em;">
          <button type="submit" class="btn btn-accent">Show my order</button>
        </form>
      </div>
    <?php elseif ($lookup['kind'] === 'ok'):
      $o = $lookup['order'];
      $meta = STATUS_META[$o['status']];
      $cancelled = $o['status'] === 'CANCELLED';
      $nowIdx = array_search($o['status'], array_keys($routeSteps), true);
      $shareUrl = '/track?code=' . rawurlencode($o['code']) . '&t=' . rawurlencode($lookup['token']);
      $receiptUrl = '/receipt/' . rawurlencode($o['code']) . '?t=' . rawurlencode($lookup['token']);
    ?>
      <article class="print-panel stack">
        <div class="row-between" style="align-items:flex-start;">
          <div class="stack-sm">
            <span class="code-big"><?= e($o['code']) ?></span>
            <div class="small muted">Placed <?= e(fmt_datetime($o['createdAt'])) ?></div>
          </div>
          <div class="row">
            <a href="<?= e($receiptUrl) ?>" class="btn btn-sm">Receipt</a>
            <button type="button" class="btn btn-sm" data-copy="<?= e($shareUrl) ?>">Copy tracking link</button>
          </div>
        </div>

        <?php if ($cancelled): ?>
          <div class="banner banner-err"><strong>This order was cancelled.</strong> Any stock it held went back on the shelf.</div>
        <?php else: ?>
          <ol class="route" aria-label="Order progress" style="list-style:none;margin:0;padding:0;">
            <?php $i = 0; foreach ($routeSteps as $st => $label):
              $cls = $i < $nowIdx ? 'done' : ($i === $nowIdx ? ($st === 'COMPLETED' ? 'done' : 'now') : '');
            ?>
              <li class="route-step <?= $cls ?>"<?= $i === $nowIdx ? ' aria-current="step"' : '' ?>><?= e($label) ?></li>
            <?php $i++; endforeach; ?>
          </ol>
          <p style="margin:0;font-size:19px;font-weight:800;"><?= e($meta['customer']) ?></p>
          <p class="ink-2" style="margin:0;">Delivery: <strong><?= e($o['day'] ? fmt_day_relative($o['day']) : 'to be scheduled') ?></strong>, <?= e($o['slot']) ?></p>
        <?php endif; ?>

        <?php if ($o['payment'] === 'GCASH' && $o['status'] === 'PENDING'): ?>
          <div class="banner banner-warn">
            <strong>Waiting for your GCash payment.</strong> Send <?= e(peso((int) $o['total'])) ?> to 0917 888 0000 (demo number). Unpaid GCash orders are released after <?= STALE_GCASH_HOURS ?> hours.
          </div>
        <?php endif; ?>

        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Item</th><th class="num">Amount</th></tr></thead>
            <tbody>
              <?php foreach ($o['items'] as $it): ?>
                <tr>
                  <td><strong class="tabular"><?= (int) $it['qty'] ?>×</strong> <?= e($it['name']) ?> <span class="muted">· <?= e($it['variant']) ?></span></td>
                  <td class="num tabular"><?= e(peso((int) $it['price'] * (int) $it['qty'])) ?></td>
                </tr>
              <?php endforeach; ?>
              <tr>
                <td class="muted">Delivery fee</td>
                <td class="num tabular"><?= (int) $o['fee'] === 0 ? 'Free' : e(peso((int) $o['fee'])) ?></td>
              </tr>
              <tr>
                <td><strong>Total · <?= e(PAYMENT_LABEL[$o['payment']] ?? $o['payment']) ?></strong></td>
                <td class="num tabular"><strong style="font-size:18px;"><?= e(peso((int) $o['total'])) ?></strong></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="stack-sm">
          <h2 class="caps" style="margin:0;">History</h2>
          <div class="timeline">
            <?php foreach (array_reverse($o['events']) as $ev):
              $em = STATUS_META[$ev['to']];
            ?>
              <div class="timeline-item">
                <span><strong><?= e($em['done']) ?></strong><?php if ($ev['note']): ?> <span class="muted">· <?= e($ev['note']) ?></span><?php endif; ?></span>
                <span class="muted tabular" style="white-space:nowrap;"><?= e(fmt_datetime($ev['at'])) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($lookup['canCancel']): ?>
          <form method="post" action="/api/orders/cancel-customer" data-confirm="Cancel this order? Its items go back on the shelf." class="row" style="border-top:1px solid var(--sack-3);padding-top:16px;">
            <?= csrf_field() ?>
            <input type="hidden" name="code" value="<?= e($o['code']) ?>">
            <label for="cancel-reason" class="sr-only">Reason for cancelling</label>
            <input id="cancel-reason" type="text" name="reason" class="input" placeholder="Reason for cancelling (optional)" style="flex:1;min-width:200px;">
            <button type="submit" class="btn btn-danger">Cancel order</button>
          </form>
        <?php endif; ?>
      </article>
    <?php endif; ?>
  <?php endif; ?>
</div>
