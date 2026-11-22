<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../meta.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/orders.php';

/**
 * Admin dashboard reads (MariaDB 10.4 / MySQL 8 compatible).
 * Collected revenue counts GCash on CONFIRMED and COD on COMPLETED;
 * pending GCash = on_hold, active COD = on_delivery.
 */
function get_dashboard(): array
{
    $pdo = db();
    $todaySql = dt_sql(start_of_shop_day());
    $weekStart = days_ago(6);
    $weekStartSql = dt_sql($weekStart);
    $staleSql = dt_sql(now_utc()->modify('-' . STALE_GCASH_HOURS . ' hours'));

    $sumSt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN created_at >= ? AND status <> 'CANCELLED' THEN 1 ELSE 0 END) AS today_count,
            COALESCE(SUM(CASE WHEN payment_method = 'GCASH' AND status = 'PENDING' THEN total_centavos ELSE 0 END), 0) AS on_hold,
            COALESCE(SUM(CASE WHEN payment_method = 'COD' AND status IN ('PENDING','CONFIRMED','READY') THEN total_centavos ELSE 0 END), 0) AS on_delivery
        FROM orders
    ");
    $sumSt->execute([$todaySql]);
    $summary = $sumSt->fetch() ?: ['today_count' => 0, 'on_hold' => 0, 'on_delivery' => 0];

    $weekSt = $pdo->prepare("
        WITH collected AS (
            SELECT
                o.id,
                o.total_centavos,
                COALESCE(
                    (
                        SELECT MAX(e.created_at)
                        FROM order_status_events e
                        WHERE e.order_id = o.id
                          AND e.to_status = CASE WHEN o.payment_method = 'GCASH' THEN 'CONFIRMED' ELSE 'COMPLETED' END
                    ),
                    o.created_at
                ) AS collected_at
            FROM orders o
            WHERE (o.payment_method = 'GCASH' AND o.status IN ('CONFIRMED','READY','COMPLETED'))
               OR (o.payment_method = 'COD' AND o.status = 'COMPLETED')
        )
        SELECT TIMESTAMPDIFF(DAY, ?, collected_at) AS idx,
               COUNT(*) AS orders,
               COALESCE(SUM(total_centavos), 0) AS revenue
        FROM collected
        WHERE collected_at >= ?
        GROUP BY idx
    ");
    $weekSt->execute([$weekStartSql, $weekStartSql]);
    $weekRows = $weekSt->fetchAll();

    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = ['start' => $weekStart->modify("+{$i} days"), 'revenue' => 0, 'orders' => 0];
    }
    foreach ($weekRows as $b) {
        $idx = (int) $b['idx'];
        if (isset($days[$idx])) {
            $days[$idx]['revenue'] += (int) $b['revenue'];
            $days[$idx]['orders'] += (int) $b['orders'];
        }
    }

    $selSt = $pdo->prepare("
        SELECT p.name, SUM(oi.quantity) AS qty, SUM(oi.quantity * oi.price_at_time) AS revenue
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN product_variants v ON v.id = oi.variant_id
        JOIN products p ON p.id = v.product_id
        WHERE o.created_at >= ?
          AND (
            (o.payment_method = 'GCASH' AND o.status IN ('CONFIRMED','READY','COMPLETED'))
            OR (o.payment_method = 'COD' AND o.status = 'COMPLETED')
          )
        GROUP BY p.name
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $selSt->execute([$weekStartSql]);
    $topSellers = array_map(static fn($r) => [
        'name' => $r['name'],
        'qty' => (int) $r['qty'],
        'revenue' => (int) $r['revenue'],
    ], $selSt->fetchAll());

    $pipeline = order_counts();

    $pendRows = $pdo->query("
        SELECT o.id, o.order_number, o.delivery_name, o.delivery_slot, o.delivery_date,
               o.delivery_barangay, o.payment_method, o.total_centavos, o.created_at,
               c.name AS customer_name
        FROM orders o
        JOIN users c ON c.id = o.customer_id
        WHERE o.status = 'PENDING'
        ORDER BY o.created_at ASC
        LIMIT 8
    ")->fetchAll();

    $pendingOrders = [];
    if ($pendRows) {
        $pids = array_column($pendRows, 'id');
        $ph = in_placeholders(count($pids));
        $piStmt = $pdo->prepare("
            SELECT oi.order_id, oi.quantity, v.name AS variant_name, p.name AS product_name
            FROM order_items oi
            JOIN product_variants v ON v.id = oi.variant_id
            JOIN products p ON p.id = v.product_id
            WHERE oi.order_id IN ({$ph})
            ORDER BY oi.id ASC
        ");
        $piStmt->execute($pids);
        $pItems = [];
        foreach ($piStmt->fetchAll() as $i) {
            $pItems[$i['order_id']][] = [
                'name' => split_name($i['product_name'])['local'],
                'variant' => $i['variant_name'],
                'qty' => (int) $i['quantity'],
            ];
        }
        $staleCutoffDt = now_utc()->modify('-' . STALE_GCASH_HOURS . ' hours');
        foreach ($pendRows as $o) {
            $items = $pItems[$o['id']] ?? [];
            $custName = $o['delivery_name'] ?: ($o['customer_name'] ?: 'Customer');
            $pendingOrders[] = [
                'id' => $o['id'],
                'code' => order_code((int) $o['order_number']),
                'customer' => $custName,
                'payment' => $o['payment_method'],
                'stale' => $o['payment_method'] === 'GCASH' && parse_utc($o['created_at']) <= $staleCutoffDt,
                'total' => (int) $o['total_centavos'],
                'items' => array_sum(array_column($items, 'qty')),
                'createdAt' => $o['created_at'],
                'slot' => $o['delivery_slot'],
            ];
        }
    }

    $staleSt = $pdo->prepare("SELECT id FROM orders WHERE status = 'PENDING' AND payment_method = 'GCASH' AND created_at <= ? ORDER BY created_at ASC LIMIT 50");
    $staleSt->execute([$staleSql]);
    $staleGcashIds = array_column($staleSt->fetchAll(), 'id');

    $vs = sellable_variants(load_inventory());
    $outOfStock = array_values(array_filter($vs, static fn($v) => $v['stock'] <= 0));
    $lowStock = array_values(array_filter($vs, static fn($v) => $v['stock'] > 0 && $v['stock'] <= LOW_STOCK_AT));
    usort($lowStock, static fn($a, $b) => $a['stock'] <=> $b['stock']);
    $toSale = array_values(array_filter($vs, static fn($v) => $v['toSale']));
    usort($toSale, static fn($a, $b) => ($b['oldestDays'] ?? 0) <=> ($a['oldestDays'] ?? 0));

    return [
        'todayCount' => (int) $summary['today_count'],
        'todayRevenue' => $days[6]['revenue'],
        'onHoldRevenue' => (int) $summary['on_hold'],
        'onDeliveryRevenue' => (int) $summary['on_delivery'],
        'days' => $days,
        'topSellers' => $topSellers,
        'pendingCount' => $pipeline['PENDING'],
        'pipeline' => $pipeline,
        'staleGcashIds' => $staleGcashIds,
        'pendingOrders' => $pendingOrders,
        'outOfStock' => $outOfStock,
        'lowStock' => $lowStock,
        'toSale' => $toSale,
    ];
}
