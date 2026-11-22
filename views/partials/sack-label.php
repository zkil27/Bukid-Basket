<?php
declare(strict_types=1);

/** "Only 4 left" / "12 in stock" / "Sold out", plus the lot-tag tone. */
function stock_label(int $stock): array
{
    if ($stock <= 0) {
        return ['Sold out', 'chip-gray'];
    }
    if ($stock <= LOW_STOCK_AT) {
        return ["Only {$stock} left", 'chip-yellow'];
    }
    return ["{$stock} in stock", 'chip-green'];
}

/**
 * One product printed as a sack label: category band, photo, name, weight
 * tabs, live price and stock, and the Add button that stamps a lot number.
 */
function sack_label(array $p, bool $offline, string $headingTag = 'h3', bool $eager = false): string
{
    $v0 = $p['variants'][0] ?? null;
    if (!$v0) {
        return '';
    }
    $sn = split_name($p['name']);
    $hue = category_hue($p['category']['slug']);
    $st0 = (int) $v0['stock'];
    [$stLbl, $stCls] = stock_label($st0);
    $search = strtolower($p['name'] . ' ' . ($p['description'] ?? '') . ' ' . $p['category']['name']);
    $url = '/p/' . rawurlencode($p['id']);
    $tag = in_array($headingTag, ['h2', 'h3'], true) ? $headingTag : 'h3';

    ob_start();
    ?>
    <article class="sack-label" data-product-card data-search="<?= e($search) ?>" data-dishes="<?= e(implode(',', $p['dishes'] ?? [])) ?>" style="--band:<?= e($hue['bg']) ?>;">
      <div class="sack-band"><span><?= e($p['category']['name']) ?></span></div>
      <a href="<?= e($url) ?>" class="sack-photo" tabindex="-1" aria-hidden="true">
        <?php if (!empty($p['image'])): ?>
          <img src="<?= e($p['image']) ?>" alt="" <?= $eager ? '' : 'loading="lazy"' ?> width="400" height="300">
        <?php endif; ?>
      </a>
      <div class="sack-body">
        <div>
          <<?= $tag ?> class="sack-name"><a href="<?= e($url) ?>"><?= e($sn['local']) ?></a></<?= $tag ?>>
          <?php if ($sn['english']): ?><div class="sack-en"><?= e($sn['english']) ?></div><?php endif; ?>
        </div>
        <?php if (!empty($p['description'])): ?>
          <p class="sack-desc"><?= e($p['description']) ?></p>
        <?php endif; ?>
        <div class="sack-foot">
          <?php if (count($p['variants']) > 1): ?>
            <div class="variant-row" role="group" aria-label="Pack size for <?= e($sn['local']) ?>">
              <?php foreach ($p['variants'] as $vi => $v): ?>
                <button type="button" class="variant-btn <?= $vi === 0 ? 'active' : '' ?>" aria-pressed="<?= $vi === 0 ? 'true' : 'false' ?>"
                  data-variant-pick data-vid="<?= e($v['id']) ?>" data-vname="<?= e($v['name']) ?>"
                  data-price="<?= (int) $v['price'] ?>" data-stock="<?= (int) $v['stock'] ?>"><?= e($v['name']) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="row-between" style="align-items:flex-end;">
            <div class="sack-price">
              <strong data-price-display><?= e(peso((int) $v0['price'])) ?></strong>
              <span data-unit-display>/ <?= e($v0['name']) ?></span>
            </div>
            <span class="chip <?= $stCls ?>" data-stock-display><?= e($stLbl) ?></span>
          </div>
          <?php if (!empty($v0['priceChangedToday'])): ?>
            <span class="tiny muted">Price updated this morning</span>
          <?php endif; ?>
          <button type="button" class="btn btn-accent btn-block" data-add-basket
            data-vid="<?= e($v0['id']) ?>" data-pid="<?= e($p['id']) ?>" data-pname="<?= e($sn['local']) ?>"
            data-variant="<?= e($v0['name']) ?>" data-price="<?= (int) $v0['price'] ?>"
            data-image="<?= e((string) ($p['image'] ?? '')) ?>" data-stock="<?= $st0 ?>"
            data-offline="<?= $offline ? '1' : '0' ?>" <?= ($st0 <= 0 || $offline) ? 'disabled' : '' ?>
          ><?= $offline ? 'Ordering paused' : ($st0 <= 0 ? 'Sold out' : 'Add to basket') ?></button>
        </div>
      </div>
    </article>
    <?php
    return (string) ob_get_clean();
}
