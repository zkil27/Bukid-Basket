<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../meta.php';

/**
 * Append-only stock ledger queries & mutations.
 * Stock is NEVER stored as a mutable count; it is always SUM(quantity).
 */

/** @param ?list<string> $ids @return array<string, int> */
function stock_by_variant(PDO $pdo, ?array $ids = null): array
{
    if ($ids !== null && count($ids) === 0) {
        return [];
    }
    if ($ids === null) {
        $stmt = $pdo->query('SELECT variant_id, SUM(quantity) AS stock FROM stock_movements GROUP BY variant_id');
    } else {
        $ph = in_placeholders(count($ids));
        $stmt = $pdo->prepare("SELECT variant_id, SUM(quantity) AS stock FROM stock_movements WHERE variant_id IN ({$ph}) GROUP BY variant_id");
        $stmt->execute(array_values($ids));
    }
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[$r['variant_id']] = (int) $r['stock'];
    }
    return $out;
}

/**
 * FIFO oldest stock still on hand per variant (MariaDB 10.4+ / MySQL 8+ CTE).
 * @param ?list<string> $ids
 * @return array<string, string> variantId => UTC datetime string
 */
function oldest_inflow_by_variant(PDO $pdo, ?array $ids = null): array
{
    if ($ids !== null && count($ids) === 0) {
        return [];
    }
    $scope = '';
    $params = [];
    if ($ids !== null) {
        $ph = in_placeholders(count($ids));
        $scope = "AND variant_id IN ({$ph})";
        $params = array_merge(array_values($ids), array_values($ids));
    }
    $sql = "
        WITH bal AS (
            SELECT variant_id, SUM(quantity) AS stock
            FROM stock_movements
            WHERE 1=1 {$scope}
            GROUP BY variant_id
        ),
        inflow AS (
            SELECT variant_id, created_at, quantity,
                SUM(quantity) OVER (
                    PARTITION BY variant_id ORDER BY created_at DESC, id DESC
                ) AS cum
            FROM stock_movements
            WHERE quantity > 0 {$scope}
              AND variant_id IN (SELECT variant_id FROM bal WHERE stock > 0)
        )
        SELECT i.variant_id, MIN(i.created_at) AS oldest
        FROM inflow i
        JOIN bal b ON b.variant_id = i.variant_id
        WHERE b.stock > 0 AND (i.cum - i.quantity) < b.stock
        GROUP BY i.variant_id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        if ($r['oldest'] !== null) {
            $out[$r['variant_id']] = (string) $r['oldest'];
        }
    }
    return $out;
}

function variant_stock(PDO $pdo, string $variantId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE variant_id = ?');
    $stmt->execute([$variantId]);
    return (int) $stmt->fetchColumn();
}

function adjust_stock(string $actorId, string $variantId, int $quantity, string $reason, string $note = ''): array
{
    $reason = trim($reason);
    $note = trim($note);
    if ($variantId === '') {
        throw new UserError('Pick a product variant.');
    }
    if ($quantity === 0) {
        throw new UserError('Enter a quantity other than zero.');
    }
    if (mb_strlen($reason) < 2) {
        throw new UserError('Pick a reason.');
    }
    if (mb_strlen($note) > 140) {
        throw new UserError('Note must be 140 characters or less.');
    }

    return tx(static function (PDO $pdo) use ($actorId, $variantId, $quantity, $reason, $note): array {
        $chk = $pdo->prepare('SELECT id FROM product_variants WHERE id = ? LIMIT 1');
        $chk->execute([$variantId]);
        if (!$chk->fetchColumn()) {
            throw new UserError('Variant not found.');
        }

        $before = variant_stock($pdo, $variantId);
        if ($before + $quantity < 0) {
            throw new UserError("Only {$before} on hand. You can't take out " . (-$quantity) . '.');
        }
        $id = new_id();
        $fullReason = $note !== '' ? "{$reason} — {$note}" : $reason;
        $ins = $pdo->prepare('INSERT INTO stock_movements (id, variant_id, quantity, reason, actor_id, created_at) VALUES (?, ?, ?, ?, ?, NOW(3))');
        $ins->execute([$id, $variantId, $quantity, $fullReason, $actorId]);
        return ['movementId' => $id, 'before' => $before, 'after' => $before + $quantity];
    });
}

function counter_movement(string $actorId, string $movementId, string $label = 'Undo'): array
{
    return tx(static function (PDO $pdo) use ($actorId, $movementId, $label): array {
        $stmt = $pdo->prepare('SELECT id, variant_id, quantity FROM stock_movements WHERE id = ? LIMIT 1');
        $stmt->execute([$movementId]);
        $m = $stmt->fetch();
        if (!$m) {
            throw new UserError('Movement not found.');
        }
        $qty = (int) $m['quantity'];
        $before = variant_stock($pdo, $m['variant_id']);
        if ($before - $qty < 0) {
            throw new UserError('Not enough stock on hand to reverse that movement.');
        }
        $cid = new_id();
        $ins = $pdo->prepare('INSERT INTO stock_movements (id, variant_id, quantity, reason, actor_id, created_at) VALUES (?, ?, ?, ?, ?, NOW(3))');
        $ins->execute([$cid, $m['variant_id'], -$qty, "{$label} of " . movement_ref($m['id']), $actorId]);
        return ['movementId' => $cid];
    });
}

function walk_in_sale(string $actorId, string $variantId, int $qty): array
{
    if ($qty < 1) {
        throw new UserError('Enter how many were sold.');
    }
    return tx(static function (PDO $pdo) use ($actorId, $variantId, $qty): array {
        $before = variant_stock($pdo, $variantId);
        if ($qty > $before) {
            throw new UserError($before <= 0 ? 'That item is sold out.' : "Only {$before} on hand.");
        }
        $id = new_id();
        $ins = $pdo->prepare('INSERT INTO stock_movements (id, variant_id, quantity, reason, actor_id, created_at) VALUES (?, ?, ?, ?, ?, NOW(3))');
        $ins->execute([$id, $variantId, -$qty, 'Walk-in sale', $actorId]);
        return ['movementId' => $id, 'after' => $before - $qty];
    });
}

/** @param list<array{variantId: string, counted: int}> $counts */
function save_morning_count(string $actorId, array $counts): array
{
    $valid = [];
    foreach ($counts as $c) {
        $vid = (string) ($c['variantId'] ?? '');
        $cnt = filter_var($c['counted'] ?? null, FILTER_VALIDATE_INT);
        if ($vid !== '' && $cnt !== false && $cnt >= 0) {
            $valid[$vid] = (int) $cnt;
        }
    }
    if (!$valid) {
        throw new UserError('Enter at least one count.');
    }

    $adjusted = tx(static function (PDO $pdo) use ($actorId, $valid): int {
        $ids = array_keys($valid);
        $system = stock_by_variant($pdo, $ids);
        $rows = [];
        foreach ($valid as $vid => $counted) {
            $sys = $system[$vid] ?? 0;
            if ($counted !== $sys) {
                $rows[] = [
                    'id' => new_id(),
                    'variant_id' => $vid,
                    'quantity' => $counted - $sys,
                    'reason' => "Morning count (counted {$counted}, system had {$sys})",
                ];
            }
        }
        if ($rows) {
            $ins = $pdo->prepare('INSERT INTO stock_movements (id, variant_id, quantity, reason, actor_id, created_at) VALUES (?, ?, ?, ?, ?, NOW(3))');
            foreach ($rows as $r) {
                $ins->execute([$r['id'], $r['variant_id'], $r['quantity'], $r['reason'], $actorId]);
            }
        }
        return count($rows);
    });

    return ['adjusted' => $adjusted, 'checked' => count($valid)];
}

function ledger_kind_of(string $reason, int $qty): string
{
    $r = strtolower($reason);
    if (str_starts_with($r, 'sale') || str_starts_with($r, 'walk-in')) {
        return 'sale';
    }
    if (str_starts_with($r, 'cancelled')) {
        return 'cancel';
    }
    if (str_starts_with($r, 'morning count')) {
        return 'count';
    }
    if (str_starts_with($r, 'undo') || str_starts_with($r, 'correction') || str_starts_with($r, 'miscount')) {
        return 'correction';
    }
    if (str_starts_with($r, 'spoil') || str_starts_with($r, 'damaged')) {
        return 'loss';
    }
    return $qty > 0 ? 'restock' : 'other';
}

const LEDGER_LIMIT = 300;

function load_ledger(?string $variantId = null, ?int $sinceDays = null, ?string $kindFilter = null): array
{
    $pdo = db();
    $conds = ['1=1'];
    $params = [];
    if ($variantId !== null && $variantId !== '') {
        $conds[] = 'sm.variant_id = ?';
        $params[] = $variantId;
    }
    if ($sinceDays !== null && $sinceDays >= 0) {
        $conds[] = 'sm.created_at >= ?';
        $params[] = dt_sql(days_ago($sinceDays));
    }
    $where = implode(' AND ', $conds);
    $limit = LEDGER_LIMIT + 1;

    $sql = "
        WITH m AS (
            SELECT sm.*,
                COALESCE(SUM(sm.quantity) OVER (
                    PARTITION BY sm.variant_id ORDER BY sm.created_at DESC, sm.id DESC
                    ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                ), 0) AS later
            FROM stock_movements sm
            WHERE {$where}
        ),
        s AS (
            SELECT variant_id, SUM(quantity) AS stock
            FROM stock_movements
            WHERE variant_id IN (SELECT DISTINCT variant_id FROM m)
            GROUP BY variant_id
        )
        SELECT m.id, m.variant_id, m.quantity, m.reason, m.order_id, m.created_at,
               (s.stock - m.later) AS balance,
               v.name AS variant_name, v.sku, p.name AS product_name,
               o.order_number, a.name AS actor_name
        FROM m
        JOIN s ON s.variant_id = m.variant_id
        JOIN product_variants v ON v.id = m.variant_id
        JOIN products p ON p.id = v.product_id
        LEFT JOIN orders o ON o.id = m.order_id
        LEFT JOIN users a ON a.id = m.actor_id
        ORDER BY m.created_at DESC, m.id DESC
        LIMIT {$limit}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw = $stmt->fetchAll();
    $truncated = count($raw) > LEDGER_LIMIT;
    $slice = array_slice($raw, 0, LEDGER_LIMIT);

    $rows = [];
    foreach ($slice as $m) {
        $qty = (int) $m['quantity'];
        $kind = ledger_kind_of((string) $m['reason'], $qty);
        if ($kindFilter !== null && $kindFilter !== '' && $kindFilter !== 'all' && $kind !== $kindFilter) {
            continue;
        }
        $rows[] = [
            'id' => $m['id'],
            'ref' => movement_ref($m['id']),
            'variantId' => $m['variant_id'],
            'product' => $m['product_name'],
            'variant' => $m['variant_name'],
            'sku' => $m['sku'],
            'quantity' => $qty,
            'reason' => $m['reason'],
            'kind' => $kind,
            'orderCode' => $m['order_number'] !== null ? order_code((int) $m['order_number']) : null,
            'actor' => $m['actor_name'] ?? ($m['order_id'] ? 'Checkout' : 'System'),
            'at' => $m['created_at'],
            'balance' => (int) $m['balance'],
        ];
    }

    return ['rows' => $rows, 'truncated' => $truncated];
}

function ledger_variants(): array
{
    $pdo = db();
    $rows = $pdo->query("
        SELECT v.id, v.name, v.active AS v_active, p.name AS product_name, p.active AS p_active
        FROM product_variants v
        JOIN products p ON p.id = v.product_id
        JOIN categories c ON c.id = p.category_id
        ORDER BY c.name ASC, p.name ASC, v.price_centavos DESC
    ")->fetchAll();
    $stock = stock_by_variant($pdo);
    return array_map(static fn(array $v) => [
        'id' => $v['id'],
        'productName' => $v['product_name'],
        'name' => $v['name'],
        'archived' => !((bool) $v['p_active'] && (bool) $v['v_active']),
        'stock' => $stock[$v['id']] ?? 0,
    ], $rows);
}
