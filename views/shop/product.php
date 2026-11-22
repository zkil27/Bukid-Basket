<?php
declare(strict_types=1);
/** @var array $product @var bool $offline @var array $related */
$sn = split_name($product['name']);
$hue = category_hue($product['category']['slug']);
$v0 = $product['variants'][0];
$st0 = (int) $v0['stock'];
[$stLbl, $stCls] = stock_label($st0);
$dishLabels = array_column(DISHES, 'label', 'id');
?>
<div class="stack-lg">
  <nav class="small" aria-label="Breadcrumb">
    <a href="/">Shop</a> <span class="muted">/</span>
    <a href="/#cat-<?= e($product['category']['slug']) ?>"><?= e($product['category']['name']) ?></a> <span class="muted">/</span>
    <span class="muted"><?= e($sn['local']) ?></span>
  </nav>

  <div class="product-hero" data-product-card>
    <div class="product-photo sack-photo">
      <?php if ($product['image']): ?>
        <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" width="800" height="600">
      <?php endif; ?>
    </div>

    <div class="stack">
      <div class="stack-sm">
        <div class="row">
          <span class="chip" style="color:<?= e($hue['ink']) ?>;background:<?= e($hue['soft']) ?>;"><?= e($product['category']['name']) ?></span>
          <?php foreach ($product['dishes'] ?? [] as $d): ?>
            <span class="chip chip-gray">For <?= e($dishLabels[$d] ?? ucfirst($d)) ?></span>
          <?php endforeach; ?>
        </div>
        <h1 class="display product-name"><?= e($sn['local']) ?></h1>
        <?php if ($sn['english']): ?>
          <div class="ink-2" style="font-size:19px;font-weight:600;"><?= e($sn['english']) ?></div>
        <?php endif; ?>
      </div>

      <?php if ($product['description']): ?>
        <p class="measure ink-2" style="margin:0;font-size:17px;"><?= e($product['description']) ?></p>
      <?php endif; ?>

      <div class="stack-sm">
        <span class="label">Pack size</span>
        <div class="variant-row" role="group" aria-label="Pack size">
          <?php foreach ($product['variants'] as $vi => $v): ?>
            <button type="button" class="variant-btn <?= $vi === 0 ? 'active' : '' ?>" aria-pressed="<?= $vi === 0 ? 'true' : 'false' ?>"
              data-variant-pick data-vid="<?= e($v['id']) ?>" data-vname="<?= e($v['name']) ?>"
              data-price="<?= (int) $v['price'] ?>" data-stock="<?= (int) $v['stock'] ?>"
            ><?= e($v['name']) ?> · <?= e(peso((int) $v['price'])) ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="row-between" style="align-items:flex-end;padding:14px 0;border-top:2px solid var(--ink);border-bottom:1px solid var(--sack-3);">
        <div class="sack-price">
          <strong data-price-display style="font-size:44px;"><?= e(peso((int) $v0['price'])) ?></strong>
          <span data-unit-display>/ <?= e($v0['name']) ?></span>
        </div>
        <span class="chip <?= $stCls ?>" data-stock-display><?= e($stLbl) ?></span>
      </div>

      <div class="buy-row">
        <div>
          <label for="product-qty" class="label">Qty</label>
          <input id="product-qty" type="number" class="input tabular qty-input" min="1" max="50" value="1" inputmode="numeric">
        </div>
        <button type="button" class="btn btn-accent btn-lg" data-add-basket
          data-vid="<?= e($v0['id']) ?>" data-pid="<?= e($product['id']) ?>" data-pname="<?= e($sn['local']) ?>"
          data-variant="<?= e($v0['name']) ?>" data-price="<?= (int) $v0['price'] ?>"
          data-image="<?= e((string) ($product['image'] ?? '')) ?>" data-stock="<?= $st0 ?>"
          data-offline="<?= $offline ? '1' : '0' ?>" <?= ($st0 <= 0 || $offline) ? 'disabled' : '' ?>
        ><?= $offline ? 'Ordering paused' : ($st0 <= 0 ? 'Sold out' : 'Add to basket') ?></button>
      </div>
      <p class="small muted" style="margin:0;">Delivered the next morning anywhere in Dasmariñas City. Free delivery on orders ₱500 and up.</p>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="stack" aria-labelledby="related-title">
      <div class="cat-head" style="margin-bottom:0;">
        <h2 id="related-title" class="display section-title">More <?= e($product['category']['name']) ?></h2>
        <a href="/#cat-<?= e($product['category']['slug']) ?>" class="small">See all</a>
      </div>
      <div class="product-grid">
        <?php foreach ($related as $rp): ?>
          <?= sack_label($rp, $offline) ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
