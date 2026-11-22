<?php
declare(strict_types=1);

/**
 * Lightweight .env / .env.local loader (zero dependencies).
 * Existing OS/web-server environment variables take precedence.
 */
(static function (): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $root = dirname(__DIR__);
    foreach (['.env', '.env.local'] as $file) {
        $path = $root . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $eq = strpos($trimmed, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $eq));
            $val = trim(substr($trimmed, $eq + 1));
            if ($key === '') {
                continue;
            }
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"') && strlen($val) >= 2) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'") && strlen($val) >= 2)
            ) {
                $val = substr($val, 1, -1);
            }
            if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
                $_ENV[$key] = $val;
                putenv("{$key}={$val}");
            }
        }
    }
})();

function env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }
    $v = getenv($key);
    return $v === false ? $default : $v;
}
