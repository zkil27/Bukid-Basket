<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Expected domain exception (sold out, invalid state transition, etc.).
 * Caught by controllers/API endpoints to show a friendly user message.
 */
final class UserError extends RuntimeException
{
}

/**
 * Single PDO connection factory for the whole application.
 * Never instantiate `new PDO(...)` outside this file.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'bukidbasket');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, (string) $user, (string) $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

/** Generate a 24-character hex ID (compatible with CHAR(24) primary keys). */
function new_id(): string
{
    return bin2hex(random_bytes(12));
}

/**
 * Execute $fn inside a database transaction (SERIALIZABLE by default)
 * with up to 3 automatic retries on MySQL/InnoDB deadlock or lock timeout.
 *
 * @template T
 * @param callable(PDO): T $fn
 * @return T
 */
function tx(callable $fn, bool $serializable = true): mixed
{
    $pdo = db();
    $attempts = 0;

    while (true) {
        $attempts++;
        try {
            if ($serializable) {
                $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            }
            $pdo->beginTransaction();
            $result = $fn($pdo);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException) {
                $sqlState = (string) $e->getCode();
                $driverCode = (int) ($e->errorInfo[1] ?? 0);
                $retryable = $sqlState === '40001' || $driverCode === 1213 || $driverCode === 1205;
                if ($retryable && $attempts < 3) {
                    usleep(40_000 * $attempts);
                    continue;
                }
            }
            throw $e;
        }
    }
}

/** Build `?,?,?` placeholder list for non-empty IN (...) clauses. */
function in_placeholders(int $count): string
{
    return implode(',', array_fill(0, $count, '?'));
}
