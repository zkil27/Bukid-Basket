<?php
declare(strict_types=1);

/**
 * Read-only MySQL database & ledger integrity checks.
 * Run with: php scripts/check-db.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/../src/env.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/meta.php';
require_once __DIR__ . '/../src/services/stock.php';

$pdo = db();
$errors = 0;

// 1. Table row counts
$tables = ['users', 'categories', 'products', 'product_variants', 'orders', 'order_items', 'stock_movements', 'order_status_events'];
echo "Table counts:\n";
foreach ($tables as $t) {
    $cnt = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    echo sprintf("  %-22s %d\n", $t, $cnt);
}

// 2. Negative stock check
$neg = $pdo->query('SELECT v.sku, SUM(sm.quantity) AS stock FROM stock_movements sm JOIN product_variants v ON v.id = sm.variant_id GROUP BY sm.variant_id, v.sku HAVING stock < 0')->fetchAll();
if ($neg) {
    foreach ($neg as $n) {
        echo "[FAIL] Negative stock on {$n['sku']}: {$n['stock']}\n";
        $errors++;
    }
} else {
    echo "[OK] Zero variants with negative stock.\n";
}

// 3. Cross-check SQL oldest_inflow_by_variant() against pure PHP FIFO replay
$sqlOldest = oldest_inflow_by_variant($pdo);
$allMoves = $pdo->query('SELECT id, variant_id, quantity, created_at FROM stock_movements ORDER BY created_at ASC, id ASC')->fetchAll();
$byVar = [];
foreach ($allMoves as $m) {
    $byVar[$m['variant_id']][] = $m;
}
$phpOldest = [];
foreach ($byVar as $vid => $moves) {
    $lots = [];
    foreach ($moves as $m) {
        $q = (int) $m['quantity'];
        if ($q > 0) {
            $lots[] = ['qty' => $q, 'at' => (string) $m['created_at']];
        } else {
            $need = -$q;
            while ($need > 0 && $lots) {
                if ($lots[0]['qty'] <= $need) {
                    $need -= $lots[0]['qty'];
                    array_shift($lots);
                } else {
                    $lots[0]['qty'] -= $need;
                    $need = 0;
                }
            }
        }
    }
    if ($lots) {
        $phpOldest[$vid] = $lots[0]['at'];
    }
}
$fifoMismatch = 0;
foreach ($phpOldest as $vid => $at) {
    if (($sqlOldest[$vid] ?? null) !== $at) {
        echo "[FAIL] FIFO mismatch on {$vid}: PHP={$at}, SQL=" . ($sqlOldest[$vid] ?? 'NULL') . "\n";
        $fifoMismatch++;
        $errors++;
    }
}
foreach ($sqlOldest as $vid => $at) {
    if (!isset($phpOldest[$vid])) {
        echo "[FAIL] FIFO extra variant in SQL: {$vid}\n";
        $fifoMismatch++;
        $errors++;
    }
}
if ($fifoMismatch === 0) {
    echo '[OK] SQL FIFO oldest_inflow_by_variant matches PHP replay across ' . count($phpOldest) . " variants.\n";
}

// 4. Order totals == SUM(quantity * price_at_time) + delivery_fee_centavos
$badTotals = $pdo->query('
    SELECT o.order_number, o.total_centavos, o.delivery_fee_centavos, COALESCE(SUM(oi.quantity * oi.price_at_time), 0) AS items_sum
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    GROUP BY o.id, o.order_number, o.total_centavos, o.delivery_fee_centavos
    HAVING o.total_centavos <> (items_sum + o.delivery_fee_centavos)
')->fetchAll();
if ($badTotals) {
    foreach ($badTotals as $b) {
        echo "[FAIL] Order total mismatch on BB-{$b['order_number']}\n";
        $errors++;
    }
} else {
    echo "[OK] All order totals match items + delivery fee.\n";
}

// 5. Cancelled orders hold net 0 stock
$badCancel = $pdo->query("
    SELECT o.order_number, sm.variant_id, SUM(sm.quantity) AS net_qty
    FROM orders o
    JOIN stock_movements sm ON sm.order_id = o.id
    WHERE o.status = 'CANCELLED'
    GROUP BY o.id, o.order_number, sm.variant_id
    HAVING net_qty <> 0
")->fetchAll();
if ($badCancel) {
    foreach ($badCancel as $c) {
        echo "[FAIL] Cancelled order BB-{$c['order_number']} still holds net {$c['net_qty']}\n";
        $errors++;
    }
} else {
    echo "[OK] All cancelled orders hold net 0 stock.\n";
}

exit($errors > 0 ? 1 : 0);
