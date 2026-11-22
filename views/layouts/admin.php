<?php
declare(strict_types=1);
/** @var string $title @var string $content @var array $user @var ?array $counts */
$counts ??= [];
$activePath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$f = flash_pop();
$nav = [
    ['/admin', 'Today', null],
    ['/admin/orders', 'Orders', ($counts['PENDING'] ?? 0) ?: null],
    ['/admin/stock', 'Stock desk', null],
    ['/admin/ledger', 'Stock ledger', null],
    ['/admin/prices', "Today's prices", null],
    ['/admin/products', 'Products', null],
    ['/admin/categories', 'Categories', null],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e(($title ?? 'Admin') . ' · Bukid Basket Admin') ?></title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="admin">
  <div class="admin-shell">
    <aside class="admin-side no-print" aria-label="Admin">
      <a href="/admin" class="admin-brand">
        <?= brand_badge() ?>
        <span><b>Bukid Basket</b><small>Admin desk</small></span>
      </a>
      <?php foreach ($nav as [$href, $label, $badge]):
        $isActive = $href === '/admin' ? $activePath === '/admin' : str_starts_with($activePath, $href);
      ?>
        <a href="<?= e($href) ?>" class="admin-nav-link <?= $isActive ? 'active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
          <span><?= e($label) ?></span>
          <?php if ($badge): ?><span class="chip chip-yellow" title="Orders waiting for confirmation"><?= (int) $badge ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <div class="admin-side-foot">
        <a href="/" class="admin-nav-link">View shop</a>
        <span class="small muted who">Signed in as <?= e($user['name'] ?: $user['email']) ?></span>
        <form method="post" action="/api/auth/logout">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-block">Sign out</button>
        </form>
      </div>
    </aside>

    <main class="admin-main" id="main">
      <?php if ($f): ?>
        <div id="flash-data" hidden data-type="<?= e($f['type']) ?>" data-msg="<?= e($f['message']) ?>"></div>
        <noscript>
          <div class="banner <?= $f['type'] === 'err' ? 'banner-err' : ($f['type'] === 'ok' ? 'banner-ok' : 'banner-warn') ?> no-print" role="status" style="margin-bottom:16px;">
            <?= e($f['message']) ?>
          </div>
        </noscript>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
  <script src="/assets/app.js"></script>
</body>
</html>
