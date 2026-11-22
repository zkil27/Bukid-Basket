<?php
declare(strict_types=1);
/** @var string $mode @var string $next @var bool $denied */
$isRegister = $mode === 'register';
$isDev = env('APP_ENV', 'development') !== 'production';
$nextQs = $next !== '/' ? '?next=' . rawurlencode($next) : '';
?>
<div class="stack" style="max-width:480px;margin:12px auto 0;">
  <?php if ($denied): ?>
    <div class="banner banner-err" role="alert">That page is for Bukid Basket admins only. Sign in with an admin account to open it.</div>
  <?php endif; ?>

  <div class="print-panel stack">
    <div>
      <h1 class="display page-title"><?= $isRegister ? 'Create an account' : 'Sign in' ?></h1>
      <p class="muted small" style="margin:8px 0 0;"><?= $isRegister ? 'One account for ordering, tracking and saved delivery details.' : 'Welcome back. Your basket is still here.' ?></p>
    </div>

    <form method="post" action="<?= $isRegister ? '/api/auth/register' : '/api/auth/login' ?>" class="stack-sm">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <?php if ($isRegister): ?>
        <div>
          <label class="label" for="auth-name">Full name</label>
          <input id="auth-name" name="name" class="input" required autocomplete="name" placeholder="Maria Santos">
        </div>
      <?php endif; ?>
      <div>
        <label class="label" for="auth-email">Email</label>
        <input id="auth-email" type="email" name="email" class="input" required autocomplete="email" placeholder="you@example.com">
      </div>
      <div>
        <label class="label" for="auth-pass">Password</label>
        <input id="auth-pass" type="password" name="password" class="input" required minlength="6" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" placeholder="At least 6 characters">
      </div>
      <button type="submit" class="btn btn-accent btn-lg btn-block" style="margin-top:6px;">
        <?= $isRegister ? 'Create account' : 'Sign in' ?>
      </button>
    </form>

    <div class="small ink-2">
      <?php if ($isRegister): ?>
        Already have an account? <a href="/login<?= e($nextQs) ?>">Sign in</a>
      <?php else: ?>
        New here? <a href="/register<?= e($nextQs) ?>">Create an account</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isDev): ?>
    <div class="card stack-sm small">
      <strong class="caps">Demo accounts (local only)</strong>
      <div>Admin: <span class="mono">ej@bukidbasket.ph</span> or <span class="mono">kiel@bukidbasket.ph</span> / <span class="mono">admin123</span></div>
      <div>Customer: <span class="mono">maria@example.com</span> / <span class="mono">customer123</span></div>
    </div>
  <?php endif; ?>
</div>
