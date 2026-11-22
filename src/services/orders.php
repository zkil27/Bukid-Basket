<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../meta.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/stock.php';
require_once dirname(__DIR__, 2) . '/database/catalog-data.php';

function slot_availability(): ?array
{
    $day = delivery_date_for();
    $dayKey = day_key($day);
    try {
        $stmt = db()->prepare("SELECT delivery_slot, COUNT(*) AS cnt FROM orders WHERE delivery_date = ? AND status <> 'CANCELLED' GROUP BY delivery_slot");
        $stmt->execute([$dayKey]);
        $taken = [];
        foreach ($stmt->fetchAll() as $r) {
            $taken[$r['delivery_slot']] = (int) $r['cnt'];
        }
        $slots = [];
        foreach (DELIVERY_SLOTS as $s) {
            $slots[] = ['slot' => $s, 'left' => max(0, SLOT_CAPACITY - ($taken[$s] ?? 0))];
        }
        return ['day' => $dayKey, 'slots' => $slots];
    } catch (Throwable) {
        return null;
    }
}

function create_order_tx(PDO $pdo, string $customerId, array $input, array $opts = []): array
{
    $itemsIn = $input['items'] ?? [];
    $delIn = $input['delivery'] ?? [];
    $payment = (string) ($input['payment'] ?? '');

    if (!is_array($itemsIn) || count($itemsIn) < 1) {
        throw new UserError('Your basket is empty.');
    }
    $contact = validate_contact($delIn);
    $recName = trim((string) ($delIn['name'] ?? ''));
    $slot = (string) ($delIn['slot'] ?? '');
    if (mb_strlen($recName) < 2) {
        throw new UserError('Enter the name of the person receiving the order.');
    }
    if (!in_array($slot, DELIVERY_SLOTS, true)) {
        throw new UserError('Pick a delivery slot.');
    }
    if (!in_array($payment, ['GCASH', 'COD'], true)) {
        throw new UserError('Pick a payment method.');
    }

    $qtyById = [];
    foreach ($itemsIn as $row) {
        $vid = trim((string) ($row['variantId'] ?? ''));
        $qty = filter_var($row['qty'] ?? null, FILTER_VALIDATE_INT);
        if ($vid === '' || $qty === false || $qty < 1 || $qty > 50) {
            throw new UserError('Invalid basket quantity (1–50 per line).');
        }
        $qtyById[$vid] = ($qtyById[$vid] ?? 0) + (int) $qty;
    }
    $ids = array_keys($qtyById);

    $deliveryDate = delivery_date_for();
    $deliveryDay = day_key($deliveryDate);
    if (empty($opts['skipCapacity'])) {
        $capStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE delivery_date = ? AND delivery_slot = ? AND status <> 'CANCELLED'");
        $capStmt->execute([$deliveryDay, $slot]);
        if ((int) $capStmt->fetchColumn() >= SLOT_CAPACITY) {
            throw new UserError("The {$slot} slot on " . fmt_day($deliveryDate) . ' just filled up. Pick another slot.');
        }
    }

    $ph = in_placeholders(count($ids));
    $vStmt = $pdo->prepare("
        SELECT v.id, v.name, v.price_centavos, p.name AS product_name
        FROM product_variants v
        JOIN products p ON p.id = v.product_id
        WHERE v.id IN ({$ph}) AND v.active = 1 AND p.active = 1
    ");
    $vStmt->execute($ids);
    $variants = $vStmt->fetchAll();
    if (count($variants) !== count($ids)) {
        throw new UserError('Something in your basket is no longer sold. Remove it and try again.');
    }

    $stock = stock_by_variant($pdo, $ids);
    $subtotal = 0;
    foreach ($variants as $v) {
        $want = $qtyById[$v['id']];
        $have = $stock[$v['id']] ?? 0;
        if ($want > $have) {
            throw new UserError(
                $have <= 0
                    ? "{$v['product_name']} ({$v['name']}) just sold out. Remove it to continue."
                    : "Only {$have} left of {$v['product_name']} ({$v['name']}). Lower the quantity to continue."
            );
        }
        $subtotal += (int) $v['price_centavos'] * $want;
    }

    $fee = delivery_fee_for($subtotal);
    $total = $subtotal + $fee;
    $autoConfirm = $payment === 'COD';
    $initialStatus = $autoConfirm ? 'CONFIRMED' : 'PENDING';
    $placedAt = now_utc();
    $placedSql = dt_sql($placedAt);
    $orderId = new_id();

    $insOrder = $pdo->prepare('
        INSERT INTO orders (
            id, customer_id, status, payment_method, delivery_name, delivery_phone,
            delivery_address, delivery_barangay, delivery_slot, delivery_date,
            delivery_fee_centavos, total_centavos, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $insOrder->execute([
        $orderId,
        $customerId,
        $initialStatus,
        $payment,
        $recName,
        $contact['phone'],
        $contact['address'],
        $contact['barangay'],
        $slot,
        $deliveryDay,
        $fee,
        $total,
        $placedSql,
        $placedSql,
    ]);
    $orderNum = (int) $pdo->query("SELECT order_number FROM orders WHERE id = " . $pdo->quote($orderId))->fetchColumn();
    $code = order_code($orderNum);

    $insItem = $pdo->prepare('INSERT INTO order_items (id, order_id, variant_id, quantity, price_at_time) VALUES (?, ?, ?, ?, ?)');
    $insMove = $pdo->prepare('INSERT INTO stock_movements (id, variant_id, quantity, reason, order_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $suffix = (string) ($opts['reasonSuffix'] ?? '');
    foreach ($variants as $v) {
        $q = $qtyById[$v['id']];
        $insItem->execute([new_id(), $orderId, $v['id'], $q, (int) $v['price_centavos']]);
        $insMove->execute([new_id(), $v['id'], -$q, "Sale — {$code}{$suffix}", $orderId, $placedSql]);
    }

    $note = (string) ($opts['note'] ?? 'Placed online');
    $insEv = $pdo->prepare('INSERT INTO order_status_events (id, order_id, from_status, to_status, note, actor_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insEv->execute([new_id(), $orderId, null, 'PENDING', $note, $customerId, $placedSql]);
    if ($autoConfirm) {
        $confSql = dt_sql(new DateTimeImmutable('@' . ($placedAt->getTimestamp() + 1)));
        $insEv->execute([new_id(), $orderId, 'PENDING', 'CONFIRMED', 'Auto-confirmed (Cash on delivery)', null, $confSql]);
    }

    return [
        'orderId' => $orderId,
        'orderNumber' => $orderNum,
        'code' => $code,
        'total' => $total,
        'deliveryDay' => $deliveryDay,
        'status' => $initialStatus,
    ];
}

function place_order(string $customerId, array $input): array
{
    return tx(static function (PDO $pdo) use ($customerId, $input): array {
        $placed = create_order_tx($pdo, $customerId, $input);
        if (!empty($input['saveDetails'])) {
            $d = $input['delivery'];
            $pdo->prepare('UPDATE users SET phone = ?, address = ?, barangay = ?, updated_at = NOW(3) WHERE id = ?')
                ->execute([trim((string) $d['phone']), trim((string) $d['address']), trim((string) $d['barangay']), $customerId]);
        }
        return $placed;
    });
}

function cancel_order_tx(PDO $pdo, array $order, string $actorId, string $note, string $label): void
{
    $code = order_code((int) $order['order_number']);
    $mvStmt = $pdo->prepare('SELECT variant_id, quantity FROM stock_movements WHERE order_id = ?');
    $mvStmt->execute([$order['id']]);
    $held = [];
    foreach ($mvStmt->fetchAll() as $m) {
        $held[$m['variant_id']] = ($held[$m['variant_id']] ?? 0) - (int) $m['quantity'];
    }
    $insMove = $pdo->prepare('INSERT INTO stock_movements (id, variant_id, quantity, reason, order_id, actor_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW(3))');
    foreach ($held as $vid => $q) {
        if ($q > 0) {
            $insMove->execute([new_id(), $vid, $q, "{$label} — {$code} (back to stock)", $order['id'], $actorId]);
        }
    }
    $pdo->prepare("UPDATE orders SET status = 'CANCELLED', updated_at = NOW(3) WHERE id = ?")
        ->execute([$order['id']]);
    $pdo->prepare("INSERT INTO order_status_events (id, order_id, from_status, to_status, note, actor_id, created_at) VALUES (?, ?, ?, 'CANCELLED', ?, ?, NOW(3))")
        ->execute([new_id(), $order['id'], $order['status'], $note, $actorId]);
}

function cancel_customer_order(array $user, string $orderCodeInput, ?string $reason = null): void
{
    $num = parse_order_code($orderCodeInput);
    if (!$num) {
        throw new UserError('Invalid order code.');
    }
    $note = ($reason !== null && mb_strlen(trim($reason)) >= 2) ? trim($reason) : 'Cancelled by customer';

    tx(static function (PDO $pdo) use ($user, $num, $note): void {
        $stmt = $pdo->prepare('
            SELECT o.id, o.order_number, o.customer_id, o.status
            FROM orders o
            WHERE o.order_number = ?
            LIMIT 1
        ');
        $stmt->execute([$num]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new UserError('Order not found.');
        }
        if ($user['role'] !== 'ADMIN' && $order['customer_id'] !== $user['id']) {
            throw new UserError("You don't have permission to cancel this order.");
        }
        if ($order['status'] === 'READY' || $order['status'] === 'COMPLETED') {
            throw new UserError("This order is already out for delivery or delivered, so it can't be cancelled.");
        }
        if ($order['status'] === 'CANCELLED') {
            throw new UserError('This order is already cancelled.');
        }
        cancel_order_tx($pdo, $order, $user['id'], $note, 'Cancelled by customer');
    });
}

/**
 * Privacy-gated order lookup for /track and /receipt: the signed-in owner,
 * an admin, or a valid last-4-digits token.
 */
function lookup_order(int $orderNumber, ?array $viewer, ?string $token): array
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT o.*
            FROM orders o
            WHERE o.order_number = ?
            LIMIT 1
        ");
        $stmt->execute([$orderNumber]);
        $row = $stmt->fetch();
    } catch (Throwable) {
        return ['kind' => 'offline'];
    }

    $staff = $viewer && is_staff($viewer['role']);
    if (!$row) {
        return $staff ? ['kind' => 'missing'] : ['kind' => 'locked'];
    }

    $owner = $viewer && $viewer['id'] === $row['customer_id'];
    if (!$owner && !$staff && !valid_track_token($orderNumber, $token)) {
        return ['kind' => 'locked'];
    }

    $itemsStmt = $pdo->prepare("
        SELECT oi.quantity, oi.price_at_time, v.name AS variant_name, p.name AS product_name, p.image_url
        FROM order_items oi
        JOIN product_variants v ON v.id = oi.variant_id
        JOIN products p ON p.id = v.product_id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $itemsStmt->execute([$row['id']]);
    $items = array_map(static fn($i) => [
        'name' => $i['product_name'],
        'variant' => $i['variant_name'],
        'qty' => (int) $i['quantity'],
        'price' => (int) $i['price_at_time'],
        'image' => $i['image_url'] ?: image_for_name($i['product_name']),
    ], $itemsStmt->fetchAll());

    $evStmt = $pdo->prepare('SELECT to_status, created_at, note FROM order_status_events WHERE order_id = ? ORDER BY created_at ASC');
    $evStmt->execute([$row['id']]);
    $events = array_map(static fn($ev) => [
        'to' => $ev['to_status'],
        'at' => $ev['created_at'],
        'note' => $ev['to_status'] === 'CANCELLED' ? $ev['note'] : null,
    ], $evStmt->fetchAll());

    $open = in_array($row['status'], ['PENDING', 'CONFIRMED'], true);
    return [
        'kind' => 'ok',
        'owner' => $owner,
        'token' => track_token($orderNumber),
        'canCancel' => $open && ($owner || ($viewer && $viewer['role'] === 'ADMIN')),
        'order' => [
            'code' => order_code((int) $row['order_number']),
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
            'slot' => $row['delivery_slot'],
            'day' => $row['delivery_date'] ? day_key($row['delivery_date']) : null,
            'payment' => $row['payment_method'],
            'total' => (int) $row['total_centavos'],
            'fee' => (int) $row['delivery_fee_centavos'],
            'items' => $items,
            'events' => $events,
        ],
    ];
}

function list_my_orders(string $customerId): array|string
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare('
            SELECT id, order_number, status, created_at, delivery_slot, delivery_date,
                   payment_method, total_centavos, delivery_fee_centavos
            FROM orders
            WHERE customer_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ');
        $stmt->execute([$customerId]);
        $orders = $stmt->fetchAll();
        if (!$orders) {
            return [];
        }
        $ids = array_column($orders, 'id');
        $ph = in_placeholders(count($ids));
        $iStmt = $pdo->prepare("
            SELECT oi.order_id, oi.quantity, oi.price_at_time, v.name AS variant_name, p.name AS product_name, p.image_url
            FROM order_items oi
            JOIN product_variants v ON v.id = oi.variant_id
            JOIN products p ON p.id = v.product_id
            WHERE oi.order_id IN ({$ph})
            ORDER BY oi.id ASC
        ");
        $iStmt->execute($ids);
        $byOrder = [];
        foreach ($iStmt->fetchAll() as $i) {
            $byOrder[$i['order_id']][] = [
                'name' => $i['product_name'],
                'variant' => $i['variant_name'],
                'qty' => (int) $i['quantity'],
                'price' => (int) $i['price_at_time'],
                'image' => $i['image_url'] ?: image_for_name($i['product_name']),
            ];
        }
        return array_map(static fn($o) => [
            'code' => order_code((int) $o['order_number']),
            'status' => $o['status'],
            'createdAt' => $o['created_at'],
            'slot' => $o['delivery_slot'],
            'day' => $o['delivery_date'] ? day_key($o['delivery_date']) : null,
            'payment' => $o['payment_method'],
            'total' => (int) $o['total_centavos'],
            'fee' => (int) $o['delivery_fee_centavos'],
            'items' => $byOrder[$o['id']] ?? [],
        ], $orders);
    } catch (Throwable) {
        return 'offline';
    }
}

// ── Admin order reads & mutations ─────────────────────────────────────────────
function order_counts(): array
{
    $out = ['ALL' => 0, 'PENDING' => 0, 'CONFIRMED' => 0, 'READY' => 0, 'COMPLETED' => 0, 'CANCELLED' => 0];
    $rows = db()->query('SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status')->fetchAll();
    foreach ($rows as $r) {
        $st = $r['status'];
        if (isset($out[$st])) {
            $out[$st] = (int) $r['cnt'];
            $out['ALL'] += (int) $r['cnt'];
        }
    }
    return $out;
}

function list_admin_orders(string $status = 'ALL'): array
{
    $pdo = db();
    $where = ($status !== 'ALL' && isset(STATUS_META[$status])) ? 'WHERE o.status = ?' : '';
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.status, o.created_at, o.delivery_name, o.delivery_barangay,
               o.delivery_slot, o.delivery_date, o.payment_method, o.delivery_fee_centavos,
               o.total_centavos,
               c.name AS customer_name, c.email AS customer_email
        FROM orders o
        JOIN users c ON c.id = o.customer_id
        {$where}
        ORDER BY o.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($where !== '' ? [$status] : []);
    $orders = $stmt->fetchAll();
    if (!$orders) {
        return [];
    }
    $ids = array_column($orders, 'id');
    $ph = in_placeholders(count($ids));
    $iStmt = $pdo->prepare("
        SELECT oi.order_id, oi.quantity, oi.price_at_time, v.name AS variant_name, p.name AS product_name
        FROM order_items oi
        JOIN product_variants v ON v.id = oi.variant_id
        JOIN products p ON p.id = v.product_id
        WHERE oi.order_id IN ({$ph})
        ORDER BY oi.id ASC
    ");
    $iStmt->execute($ids);
    $itemsByOrder = [];
    foreach ($iStmt->fetchAll() as $i) {
        $itemsByOrder[$i['order_id']][] = [
            'name' => $i['product_name'],
            'variant' => $i['variant_name'],
            'qty' => (int) $i['quantity'],
            'price' => (int) $i['price_at_time'],
        ];
    }

    $staleCutoff = now_utc()->modify('-' . STALE_GCASH_HOURS . ' hours');
    return array_map(static function (array $o) use ($itemsByOrder, $staleCutoff): array {
        $items = $itemsByOrder[$o['id']] ?? [];
        $day = $o['delivery_date'] ? day_key($o['delivery_date']) : null;
        return [
            'id' => $o['id'],
            'code' => order_code((int) $o['order_number']),
            'status' => $o['status'],
            'createdAt' => $o['created_at'],
            'stale' => $o['payment_method'] === 'GCASH' && $o['status'] === 'PENDING' && parse_utc($o['created_at']) <= $staleCutoff,
            'customer' => $o['customer_name'] ?: $o['customer_email'],
            'delivery' => [
                'name' => $o['delivery_name'],
                'barangay' => $o['delivery_barangay'],
                'slot' => $o['delivery_slot'],
                'day' => $day,
            ],
            'payment' => $o['payment_method'],
            'fee' => (int) $o['delivery_fee_centavos'],
            'total' => (int) $o['total_centavos'],
            'items' => $items,
        ];
    }, $orders);
}

function get_order_detail(string $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT o.id, o.delivery_phone, o.delivery_address, c.email
        FROM orders o
        JOIN users c ON c.id = o.customer_id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $o = $stmt->fetch();
    if (!$o) {
        return null;
    }
    $iStmt = $pdo->prepare("
        SELECT v.sku, p.name AS product_name, p.image_url
        FROM order_items oi
        JOIN product_variants v ON v.id = oi.variant_id
        JOIN products p ON p.id = v.product_id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $iStmt->execute([$id]);
    $items = array_map(static fn($i) => [
        'sku' => $i['sku'],
        'image' => $i['image_url'] ?: image_for_name($i['product_name']),
    ], $iStmt->fetchAll());

    $evStmt = $pdo->prepare("
        SELECT e.id, e.from_status, e.to_status, e.created_at, e.note, a.name AS actor_name, a.role AS actor_role
        FROM order_status_events e
        LEFT JOIN users a ON a.id = e.actor_id
        WHERE e.order_id = ?
        ORDER BY e.created_at ASC
    ");
    $evStmt->execute([$id]);
    $events = array_map(static fn($e) => [
        'id' => $e['id'],
        'from' => $e['from_status'],
        'to' => $e['to_status'],
        'at' => fmt_datetime($e['created_at']),
        'note' => $e['note'],
        'actor' => $e['actor_role'] ? ($e['actor_role'] === 'CUSTOMER' ? 'Customer' : ($e['actor_name'] ?: 'Staff')) : 'System',
    ], $evStmt->fetchAll());

    return [
        'phone' => $o['delivery_phone'],
        'address' => $o['delivery_address'],
        'email' => $o['email'],
        'items' => $items,
        'events' => $events,
    ];
}

function advance_order(string $adminId, string $orderId, string $from): array
{
    $to = STATUS_META[$from]['next'] ?? null;
    if (!$to) {
        throw new UserError('This order is already finished.');
    }
    if (!in_array($from, OFFICE_ADVANCE, true)) {
        throw new UserError("This order can't move forward from here.");
    }
    tx(static function (PDO $pdo) use ($adminId, $orderId, $from, $to): void {
        $upd = $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW(3) WHERE id = ? AND status = ?');
        $upd->execute([$to, $orderId, $from]);
        if ($upd->rowCount() !== 1) {
            throw new UserError('Someone already updated this order. Showing the latest.');
        }
        $pdo->prepare('INSERT INTO order_status_events (id, order_id, from_status, to_status, actor_id, created_at) VALUES (?, ?, ?, ?, ?, NOW(3))')
            ->execute([new_id(), $orderId, $from, $to, $adminId]);
    });
    return ['to' => $to];
}

function revert_order(string $adminId, string $orderId, string $from, string $to): void
{
    if ((STATUS_META[$to]['next'] ?? null) !== $from || !in_array($to, OFFICE_ADVANCE, true)) {
        throw new UserError("That change can't be undone.");
    }
    tx(static function (PDO $pdo) use ($adminId, $orderId, $from, $to): void {
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        if ($stmt->fetchColumn() !== $from) {
            throw new UserError('The order changed since; nothing was undone.');
        }
        $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW(3) WHERE id = ?')
            ->execute([$to, $orderId]);
        $pdo->prepare("INSERT INTO order_status_events (id, order_id, from_status, to_status, note, actor_id, created_at) VALUES (?, ?, ?, ?, 'Undo', ?, NOW(3))")
            ->execute([new_id(), $orderId, $from, $to, $adminId]);
    });
}

function advance_orders(string $adminId, array $orderIds, string $from): array
{
    $to = STATUS_META[$from]['next'] ?? null;
    $ids = array_values(array_unique(array_filter(array_map('strval', $orderIds))));
    if (!$to || !$ids || !in_array($from, OFFICE_ADVANCE, true)) {
        throw new UserError('Nothing to update.');
    }

    $movedIds = tx(static function (PDO $pdo) use ($adminId, $ids, $from, $to): array {
        $ph = in_placeholders(count($ids));
        $sel = $pdo->prepare("SELECT id FROM orders WHERE id IN ({$ph}) AND status = ? FOR UPDATE");
        $sel->execute(array_merge($ids, [$from]));
        $moved = array_column($sel->fetchAll(), 'id');
        if (!$moved) {
            return [];
        }
        $phM = in_placeholders(count($moved));
        $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW(3) WHERE id IN ({$phM}) AND status = ?")
            ->execute(array_merge([$to], $moved, [$from]));
        $insEv = $pdo->prepare('INSERT INTO order_status_events (id, order_id, from_status, to_status, actor_id, created_at) VALUES (?, ?, ?, ?, ?, NOW(3))');
        foreach ($moved as $oid) {
            $insEv->execute([new_id(), $oid, $from, $to, $adminId]);
        }
        return $moved;
    });

    return ['count' => count($movedIds), 'ids' => $movedIds];
}

function cancel_order_admin(string $adminId, string $orderId, string $reason): void
{
    $note = trim($reason);
    if (mb_strlen($note) < 3) {
        throw new UserError('Add a short reason for cancelling.');
    }
    tx(static function (PDO $pdo) use ($adminId, $orderId, $note): void {
        $st = $pdo->prepare('
            SELECT o.id, o.order_number, o.status
            FROM orders o
            WHERE o.id = ? LIMIT 1
        ');
        $st->execute([$orderId]);
        $order = $st->fetch();
        if (!$order) {
            throw new UserError('Order not found.');
        }
        if ($order['status'] === 'CANCELLED' || $order['status'] === 'COMPLETED') {
            throw new UserError('A ' . strtolower(STATUS_META[$order['status']]['label']) . " order can't be cancelled.");
        }
        cancel_order_tx($pdo, $order, $adminId, $note, 'Cancelled');
    });
}

function cancel_orders_bulk(string $adminId, array $orderIds, string $reason): array
{
    $note = trim($reason);
    if (mb_strlen($note) < 3) {
        throw new UserError('Add a short reason for cancelling.');
    }
    $ids = array_slice(array_values(array_unique(array_filter(array_map('strval', $orderIds)))), 0, 50);
    if (!$ids) {
        throw new UserError('Pick at least one order to cancel.');
    }

    return tx(static function (PDO $pdo) use ($adminId, $ids, $note): array {
        $ph = in_placeholders(count($ids));
        $st = $pdo->prepare("
            SELECT o.id, o.order_number, o.status
            FROM orders o
            WHERE o.id IN ({$ph}) AND o.status NOT IN ('CANCELLED', 'COMPLETED')
        ");
        $st->execute($ids);
        $orders = $st->fetchAll();
        if (!$orders) {
            return ['count' => 0, 'skipped' => count($ids)];
        }
        foreach ($orders as $o) {
            cancel_order_tx($pdo, $o, $adminId, $note, 'Cancelled');
        }
        return ['count' => count($orders), 'skipped' => count($ids) - count($orders)];
    });
}
