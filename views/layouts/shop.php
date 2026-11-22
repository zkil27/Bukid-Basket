<?php
declare(strict_types=1);
/** @var string $title @var string $content @var ?array $user @var ?array $catalog @var ?array $flash */
$u = $user ?? current_user();
$activePath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$f = $flash ?? flash_pop();
$isOpen = shop_is_open();
$nav = [
    ['/', 'Shop'],
    ['/track', 'Track order'],
    ['/about', 'About'],
    ['/contact', 'Contact'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="theme-color" content="#c8202f">
  <title><?= e(($title ?? 'Shop') . ' · Bukid Basket') ?></title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="shop">
  <div class="shop-top no-print">
    <div class="container">
      <span><?= $isOpen ? 'Open now · Mon–Sat, 6 AM–6 PM' : 'Closed now · orders go out next morning' ?></span>
      <span>Free delivery in Dasmariñas on orders ₱500 and up</span>
    </div>
    <div class="seam"></div>
  </div>

  <header class="shop-header no-print">
    <div class="container shop-header-inner">
      <a href="/" class="brand-link" aria-label="Bukid Basket home">
        <?= brand_badge() ?>
        <span class="brand-word"><b>Bukid</b><b>Basket</b></span>
      </a>
      <nav class="nav-links" aria-label="Main">
        <?php foreach ($nav as [$href, $label]):
          $on = $href === '/' ? $activePath === '/' || str_starts_with($activePath, '/p/') : str_starts_with($activePath, $href);
        ?>
          <a href="<?= e($href) ?>" class="nav-link <?= $on ? 'active' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($u): ?>
          <?php if ($u['role'] === 'ADMIN'): ?>
            <a href="/admin" class="nav-link">Admin</a>
          <?php endif; ?>
          <a href="/account" class="nav-link <?= $activePath === '/account' ? 'active' : '' ?>"><?= e(first_name($u['name'] ?: $u['email'])) ?></a>
        <?php else: ?>
          <a href="/login" class="nav-link <?= $activePath === '/login' ? 'active' : '' ?>">Sign in</a>
        <?php endif; ?>
      </nav>
      <button type="button" class="basket-btn" data-basket-open aria-label="Open basket">
        <?= sack_icon() ?>
        <span class="basket-label">Basket</span>
        <span class="basket-count-wrap"><span class="basket-count" data-basket-count>0</span></span>
      </button>
    </div>
  </header>

  <main class="container shop-main" id="main">
    <?php if ($f): ?>
      <div class="banner <?= $f['type'] === 'err' ? 'banner-err' : ($f['type'] === 'ok' ? 'banner-ok' : 'banner-warn') ?> no-print" role="status" style="margin-bottom:18px;">
        <?= e($f['message']) ?>
      </div>
    <?php endif; ?>
    <?= $content ?>
  </main>

  <footer class="shop-footer no-print">
    <div class="seam"></div>
    <div class="container">
      <div class="footer-grid">
        <div>
          <div class="footer-word">Bukid<br><span>Basket</span></div>
          <p class="small" style="margin:14px 0 0;max-width:40ch;color:#c9c2b1;">Vegetables, fruit, meat, eggs and rice from farms around Cavite, delivered to your door in Dasmariñas City.</p>
        </div>
        <div>
          <h3>Shop</h3>
          <ul>
            <li><a href="/#catalog">Today's harvest</a></li>
            <li><a href="/track">Track an order</a></li>
            <li><a href="/account">My account</a></li>
          </ul>
        </div>
        <div>
          <h3>Bukid Basket</h3>
          <ul>
            <li><a href="/about">About &amp; makers</a></li>
            <li><a href="/contact">Contact &amp; hours</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-small">
        <span>Made by <span class="makers">EJ &amp; Kiel</span> · Academic capstone with fictional demo data</span>
        <span>Dasmariñas City, Cavite</span>
      </div>
    </div>
  </footer>

  <div id="basket-drawer" class="drawer-backdrop hidden no-print" data-basket-close>
    <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="basket-title" data-drawer-panel>
      <div class="drawer-head">
        <h2 class="display" id="basket-title">Your basket</h2>
        <button type="button" class="btn btn-sm" data-basket-close>Close</button>
      </div>
      <div id="basket-items" class="drawer-body"></div>
      <div id="basket-summary" class="drawer-foot"></div>
    </div>
  </div>

  <script src="/assets/app.js"></script>
</body>
</html>
