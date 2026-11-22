<?php
declare(strict_types=1);
/** @var array $catalog */
$products = $catalog['products'] ?? [];
$categories = $catalog['categories'] ?? [];
$offline = !empty($catalog['offline']);

// The net-weight stamp shows the biggest sack we sell, at its live price.
$stamp = null;
foreach ($products as $p) {
    foreach ($p['variants'] as $v) {
        if (preg_match('/(\d+)\s*kg/i', $v['name'], $m) && (int) $m[1] >= 25 && (int) $v['stock'] > 0) {
            $stamp = ['product' => $p, 'variant' => $v, 'kg' => (int) $m[1]];
            break 2;
        }
    }
}

// Three featured labels: the first in-stock product of the first three categories.
$featured = [];
foreach ($categories as $c) {
    foreach ($products as $p) {
        if ($p['category']['slug'] === $c['slug'] && (int) ($p['variants'][0]['stock'] ?? 0) > 0) {
            $featured[] = $p;
            break;
        }
    }
    if (count($featured) === 3) {
        break;
    }
}

$byCat = [];
foreach ($products as $p) {
    $byCat[$p['category']['slug']][] = $p;
}
?>
<div class="stack-lg">
  <?php if ($offline): ?>
    <div class="banner banner-warn" role="status">
      <strong>We can't reach the stock ledger right now.</strong> You can browse, but ordering is paused until it's back.
    </div>
  <?php endif; ?>

  <section class="print-panel harvest" aria-labelledby="harvest-title">
    <div style="position:relative;z-index:1;">
      <h1 id="harvest-title" class="display display-red harvest-title">Today's<br>harvest</h1>
      <p class="harvest-lede">Greens cut at dawn, fruit, meat, eggs and rice from farms around Cavite. Every price and stock count on this page comes straight from our stock ledger.</p>
      <a href="#catalog" class="btn btn-accent btn-lg">Shop all <?= count($products) ?> items</a>
    </div>
    <?php if ($stamp):
      $sp = split_name($stamp['product']['name']);
    ?>
      <a class="net-stamp" href="/p/<?= e(rawurlencode($stamp['product']['id'])) ?>">
        <span class="caps">Net weight</span>
        <span class="net-weight tabular"><?= (int) $stamp['kg'] ?><small> kg</small></span>
        <span class="net-name"><?= e($sp['local']) ?> · sack</span>
        <span class="net-price"><?= e(peso((int) $stamp['variant']['price'])) ?></span>
        <span class="caps" style="margin-top:10px;"><?= (int) $stamp['variant']['stock'] ?> sacks left · delivered tomorrow</span>
      </a>
    <?php endif; ?>
  </section>

  <?php if ($featured): ?>
    <section aria-labelledby="picks-title" class="stack-sm">
      <h2 id="picks-title" class="display section-title">Picked this morning</h2>
      <div class="label-row">
        <?php foreach ($featured as $fp): ?>
          <?= sack_label($fp, $offline, 'h3', true) ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section id="catalog" aria-labelledby="catalog-title" class="stack">
    <h2 id="catalog-title" class="display page-title">Everything on the shelf</h2>
    <div class="catalog-bar">
      <div class="row">
        <label for="catalog-search" class="sr-only">Search products</label>
        <input type="search" id="catalog-search" class="input search" placeholder="Search pechay, liempo, dinorado…" autocomplete="off">
        <div class="scroll-x" aria-label="Jump to category">
          <?php foreach ($categories as $c): ?>
            <a href="#cat-<?= e($c['slug']) ?>" class="pill"><?= e($c['name']) ?> <span class="count"><?= (int) $c['count'] ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="scroll-x" role="group" aria-label="Filter by dish">
        <button type="button" class="pill active" data-dish-filter="all" aria-pressed="true">Any dish</button>
        <?php foreach (DISHES as $d): ?>
          <button type="button" class="pill" data-dish-filter="<?= e($d['id']) ?>" aria-pressed="false">For <?= e($d['label']) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <p id="catalog-empty" class="empty-note hidden">Nothing matches that search. Try another name, or clear the dish filter.</p>

    <?php foreach ($categories as $cat):
      $list = $byCat[$cat['slug']] ?? [];
      if (!$list) continue;
      $hue = category_hue($cat['slug']);
    ?>
      <div id="cat-<?= e($cat['slug']) ?>" class="cat-section" data-cat-section>
        <div class="cat-head">
          <h3 class="display section-title" style="color:<?= e($hue['ink']) ?>;"><?= e($cat['name']) ?></h3>
          <span class="small muted"><?= count($list) ?> item<?= count($list) === 1 ? '' : 's' ?></span>
        </div>
        <div class="product-grid">
          <?php foreach ($list as $p): ?>
            <?= sack_label($p, $offline) ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
</div>
