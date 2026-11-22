<?php
declare(strict_types=1);

/**
 * Verification gate for Bukid Basket (PHP 8.2 + MySQL).
 * Runs `php -l` syntax lint + static footgun checks across all PHP files.
 * Opt-out an explicit exception line with: // footgun-ok: <reason>
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$scanDirs = ['public', 'src', 'views', 'database', 'scripts'];
$files = [];
foreach ($scanDirs as $dir) {
    $full = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($full)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
            $files[] = $f->getPathname();
        }
    }
}
sort($files);

$errors = 0;
$phpBin = escapeshellarg(PHP_BINARY);

echo 'Checking ' . count($files) . " PHP files...\n";
foreach ($files as $file) {
    $cmd = $phpBin . ' -l ' . escapeshellarg($file) . ' 2>&1';
    exec($cmd, $out, $rc);
    if ($rc !== 0) {
        echo '[SYNTAX ERROR] ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file) . "\n  " . implode("\n  ", $out) . "\n";
        $errors++;
    }
    $out = [];
}

// Static footgun rules
foreach ($files as $file) {
    $rel = str_replace('\\', '/', str_replace($root . DIRECTORY_SEPARATOR, '', $file));
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $idx => $line) {
        $ln = $idx + 1;
        if (str_contains($line, 'footgun-ok:')) {
            continue;
        }
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
            continue;
        }

        // 1. Stray PDO instance outside src/db.php
        if ($rel !== 'src/db.php' && $rel !== 'scripts/verify.php' && preg_match('/\bnew\s+PDO\s*\(/i', $line)) {
            echo "[FOOTGUN] {$rel}:{$ln}: stray `new PDO(` outside src/db.php\n";
            $errors++;
        }

        // 2. Float casts / floatval in PHP (centavos must stay integer)
        if ($rel !== 'scripts/verify.php' && preg_match('/(\(\s*(float|double|real)\s*\)|\bfloatval\s*\(|\bdoubleval\s*\()/i', $line)) {
            echo "[FOOTGUN] {$rel}:{$ln}: float cast/conversion forbidden (money must stay integer centavos)\n";
            $errors++;
        }

        // 3. Ledger mutation (UPDATE/DELETE on stock_movements) outside seed.php
        if ($rel !== 'database/seed.php' && $rel !== 'scripts/verify.php') {
            if (preg_match('/\b(UPDATE|DELETE\s+FROM)\s+`?stock_movements`?\b/i', $line)) {
                echo "[FOOTGUN] {$rel}:{$ln}: stock_movements is append-only (no UPDATE/DELETE)\n";
                $errors++;
            }
            if (preg_match('/\bDELETE\s+FROM\s+`?(products|product_variants)`?\b/i', $line)) {
                echo "[FOOTGUN] {$rel}:{$ln}: products/product_variants must be soft-deleted (active=0)\n";
                $errors++;
            }
        }

        // 4. Removed roles stay removed: Bukid Basket is customer + admin only
        if ($rel !== 'scripts/verify.php' && preg_match('/\b(handoff_pin|trip_id|PACKER|DRIVER)\b|\bFROM\s+`?trips`?\b|\bJOIN\s+`?trips`?\b/', $line)) {
            echo "[FOOTGUN] {$rel}:{$ln}: packer/driver/trip/handoff-PIN code was removed; do not bring it back\n";
            $errors++;
        }
    }
}

if ($errors > 0) {
    echo "\nVerify FAILED with {$errors} issue(s).\n";
    exit(1);
}

echo "Verify PASSED (0 syntax errors, 0 footguns).\n";
exit(0);
