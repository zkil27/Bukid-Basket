<?php
declare(strict_types=1);

/**
 * Idempotent CLI seed script for Bukid Basket (MySQL / MariaDB).
 * Run with: php database/seed.php
 */

require_once __DIR__ . '/../src/env.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/meta.php';
require_once __DIR__ . '/catalog-data.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$pdo = db();

function sql_dt(DateTimeImmutable $dt): string
{
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
}

function sql_date(DateTimeImmutable $dt): string
{
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
}

// Deterministic PRNG matching prisma/seed.ts
$rngState = 20261010;
$rand = static function () use (&$rngState): float {
    $rngState = ($rngState * 1103515245 + 12345) % 2147483648;
    return $rngState / 2147483648;
};
$pick = static function (array $xs) use ($rand): mixed {
    return $xs[(int) floor($rand() * count($xs))];
};

$nowUtc = now_utc();
$nowTs = $nowUtc->getTimestamp();
$todayStart = start_of_shop_day($nowUtc);

$at = static function (int $days, float $hour) use ($todayStart): DateTimeImmutable {
    $sec = (int) round((-$days * 86400) + ($hour * 3600));
    return $todayStart->modify(($sec >= 0 ? '+' : '') . $sec . ' seconds');
};
$onDay = static function (DateTimeImmutable $day, float $hour): DateTimeImmutable {
    $sec = (int) round(($hour - 8.0) * 3600);
    return $day->modify(($sec >= 0 ? '+' : '') . $sec . ' seconds');
};
$past = static function (DateTimeImmutable $d, int $slackMin = 1) use ($nowTs): DateTimeImmutable {
    $max = $nowTs - $slackMin * 60;
    return $d->getTimestamp() > $max ? (new DateTimeImmutable('@' . $max)) : $d;
};
$upsertUser = static function (
    PDO $pdo,
    string $email,
    string $name,
    string $role,
    string $password,
    ?array $contact = null
): array {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existingId = $stmt->fetchColumn();
    $phone = $contact['phone'] ?? null;
    $address = $contact['address'] ?? null;
    $barangay = $contact['barangay'] ?? null;
    if ($existingId) {
        $upd = $pdo->prepare(
            'UPDATE users SET role = ?, password_hash = ?, name = ?, phone = ?, address = ?, barangay = ?, updated_at = NOW(3) WHERE id = ?'
        );
        $upd->execute([$role, $hash, $name, $phone, $address, $barangay, $existingId]);
        $id = (string) $existingId;
    } else {
        $id = new_id();
        $ins = $pdo->prepare(
            'INSERT INTO users (id, email, name, role, password_hash, phone, address, barangay) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$id, $email, $name, $role, $hash, $phone, $address, $barangay]);
    }
    return [
        'id' => $id,
        'email' => $email,
        'name' => $name,
        'role' => $role,
        'phone' => $phone,
        'address' => $address,
        'barangay' => $barangay,
    ];
};

echo "Clearing existing catalog / order data...\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('DELETE FROM order_status_events'); // footgun-ok: seed reset, not app code
$pdo->exec('DELETE FROM stock_movements');     // footgun-ok: seed reset, not app code
$pdo->exec('DELETE FROM order_items');
$pdo->exec('DELETE FROM orders');
$pdo->exec('DELETE FROM product_variants');    // footgun-ok: seed reset, not app code
$pdo->exec('DELETE FROM products');            // footgun-ok: seed reset, not app code
$pdo->exec('DELETE FROM categories');
$pdo->exec("DELETE FROM users WHERE role <> 'CUSTOMER'");
$pdo->exec('ALTER TABLE orders AUTO_INCREMENT = 1');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// --- Users -------------------------------------------------------------------
// The two makers of Bukid Basket run the shop's admin side.
$ej = $upsertUser($pdo, 'ej@bukidbasket.ph', 'EJ (Admin)', 'ADMIN', 'admin123');
$kiel = $upsertUser($pdo, 'kiel@bukidbasket.ph', 'Kiel (Admin)', 'ADMIN', 'admin123');
$maria = $upsertUser($pdo, 'maria@example.com', 'Maria Santos', 'CUSTOMER', 'customer123', [
    'phone' => '0917 201 1001',
    'address' => 'Blk 5 Lot 12, Mabini St.',
    'barangay' => 'Salawag',
]);
$others = [
    $upsertUser($pdo, 'jose@example.com', 'Jose Reyes', 'CUSTOMER', 'customer123', [
        'phone' => '0917 202 1002',
        'address' => '14 Rizal Ave.',
        'barangay' => 'Paliparan',
    ]),
    $upsertUser($pdo, 'ana@example.com', 'Ana Cruz', 'CUSTOMER', 'customer123', [
        'phone' => '0917 203 1003',
        'address' => 'Blk 9 Lot 3, Sampaguita St.',
        'barangay' => 'Sampaloc',
    ]),
    $upsertUser($pdo, 'pedro@example.com', 'Pedro Lim', 'CUSTOMER', 'customer123', [
        'phone' => '0917 204 1004',
        'address' => '27 Bonifacio St.',
        'barangay' => 'Burol',
    ]),
    $upsertUser($pdo, 'liza@example.com', 'Liza Mendoza', 'CUSTOMER', 'customer123', [
        'phone' => '0917 205 1005',
        'address' => 'Blk 2 Lot 18, Acacia Rd.',
        'barangay' => 'Langkaan',
    ]),
];
$customers = array_merge([$maria], $others);
$staff = [$ej, $kiel];

// --- Catalog + Opening Stock -------------------------------------------------
// Slow movers get an older opening lot so the 7-day "To Sale" flag has something to show.
$slowMovers = array_flip(['VEG-KALABASA-1', 'RICE-MALAGKIT-1', 'EGG-SALTED-12', 'FRT-PINYA-PC']);
$variants = [];
$bySku = [];
$stock = [];
$productCount = 0;

$insCat = $pdo->prepare('INSERT INTO categories (id, name, slug) VALUES (?, ?, ?)');
$insProd = $pdo->prepare('INSERT INTO products (id, name, description, image_url, category_id, active) VALUES (?, ?, ?, ?, ?, 1)');
$insVar = $pdo->prepare('INSERT INTO product_variants (id, product_id, name, sku, price_centavos, active) VALUES (?, ?, ?, ?, ?, 1)');
$insMove = $pdo->prepare('INSERT INTO stock_movements (id, variant_id, quantity, reason, order_id, actor_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');

foreach (CATALOG_SEED as $cat) {
    $catId = new_id();
    $insCat->execute([$catId, $cat['name'], $cat['slug']]);
    foreach ($cat['products'] as $p) {
        $prodId = new_id();
        $insProd->execute([$prodId, $p['name'], $p['description'], $p['image'], $catId]);
        $productCount++;
        foreach ($p['variants'] as $v) {
            $varId = new_id();
            $insVar->execute([$varId, $prodId, $v['name'], $v['sku'], $v['price_centavos']]);
            $entry = [
                'id' => $varId,
                'sku' => $v['sku'],
                'price' => $v['price_centavos'],
                'productName' => $p['name'],
                'opening' => $v['opening'],
            ];
            $variants[] = $entry;
            $bySku[$v['sku']] = $entry;

            if ($v['opening'] > 0) {
                $isSlow = isset($slowMovers[$v['sku']]);
                $qty = $v['opening'] + ($isSlow ? 3 : 8);
                $insMove->execute([
                    new_id(),
                    $varId,
                    $qty,
                    $isSlow ? 'Opening stock' : 'Restock — morning harvest',
                    null,
                    $kiel['id'],
                    sql_dt($isSlow ? $at(10, 6) : $at(6, 5)),
                ]);
                $stock[$varId] = $qty;
            } else {
                $stock[$varId] = 0;
            }
        }
    }
}

$move = static function (array $v, int $quantity, string $reason, DateTimeImmutable $when, ?string $orderId = null, ?string $actorId = null) use (&$stock, $insMove): string {
    $next = ($stock[$v['id']] ?? 0) + $quantity;
    if ($next < 0) {
        throw new RuntimeException("Seed would oversell {$v['sku']}");
    }
    $stock[$v['id']] = $next;
    $id = new_id();
    $insMove->execute([$id, $v['id'], $quantity, $reason, $orderId, $actorId, sql_dt($when)]);
    return $id;
};

// --- Orders ------------------------------------------------------------------
$sellableSkus = array_values(array_map(
    static fn(array $v) => $v['sku'],
    array_filter($variants, static fn(array $v) => $v['opening'] > 0 && !isset($slowMovers[$v['sku']]))
));
$slotStart = [6, 8, 10];

$plans = [
    ['day' => 6, 'hour' => 7.2, 'status' => 'COMPLETED'],
    ['day' => 6, 'hour' => 9.5, 'status' => 'COMPLETED'],
    ['day' => 5, 'hour' => 8.1, 'status' => 'COMPLETED'],
    ['day' => 5, 'hour' => 15.4, 'status' => 'CANCELLED', 'cancelNote' => 'Customer cancelled'],
    ['day' => 4, 'hour' => 7.6, 'status' => 'COMPLETED'],
    ['day' => 4, 'hour' => 11.3, 'status' => 'COMPLETED'],
    ['day' => 3, 'hour' => 6.8, 'status' => 'COMPLETED'],
    ['day' => 3, 'hour' => 13.9, 'status' => 'COMPLETED'],
    ['day' => 2, 'hour' => 8.4, 'status' => 'COMPLETED'],
    ['day' => 2, 'hour' => 10.2, 'status' => 'COMPLETED'],
    // Yesterday -> this morning's deliveries
    ['day' => 1, 'hour' => 7.1, 'status' => 'COMPLETED', 'slot' => 0, 'cod' => true, 'who' => 1],
    ['day' => 1, 'hour' => 8.6, 'status' => 'COMPLETED', 'slot' => 0, 'who' => 3],
    ['day' => 1, 'hour' => 10.4, 'status' => 'COMPLETED', 'slot' => 0, 'cod' => true, 'who' => 4],
    ['day' => 1, 'hour' => 11.2, 'status' => 'COMPLETED', 'slot' => 1, 'who' => 2],
    ['day' => 1, 'hour' => 12.8, 'status' => 'READY', 'slot' => 1, 'cod' => true, 'who' => 0],
    ['day' => 1, 'hour' => 14.1, 'status' => 'READY', 'slot' => 1, 'who' => 4],
    ['day' => 1, 'hour' => 16.5, 'status' => 'READY', 'slot' => 1, 'cod' => true, 'who' => 3],
    ['day' => 1, 'hour' => 17.3, 'status' => 'CONFIRMED', 'slot' => 2, 'who' => 1],
    ['day' => 1, 'hour' => 19.0, 'status' => 'CONFIRMED', 'slot' => 2, 'cod' => true, 'who' => 0],
    // Today -> tomorrow morning
    ['day' => 0, 'hour' => 6.3, 'status' => 'CONFIRMED', 'slot' => 0, 'cod' => true, 'lines' => [['RICE-SINAN-5', 1], ['VEG-KANGKONG-B', 2]]],
    ['day' => 0, 'hour' => 6.9, 'status' => 'CONFIRMED', 'slot' => 1, 'cod' => false, 'lines' => [['MEAT-NATIVE-W1', 1], ['FRT-PAPAYA-PC', 1]]],
    ['day' => 0, 'hour' => 7.4, 'status' => 'PENDING', 'cod' => false, 'lines' => [['MEAT-LIEMPO-1', 1], ['VEG-KAMATIS-500', 1]]],
    ['day' => 0, 'hour' => 8.2, 'status' => 'PENDING', 'cod' => false, 'lines' => [['EGG-LARGE-30', 1], ['RICE-DINORADO-5', 1]]],
    ['day' => 0, 'hour' => 8.9, 'status' => 'PENDING', 'cod' => false, 'lines' => [['FRT-MANGGA-1', 2], ['VEG-SITAW-250', 1]]],
];

$insOrder = $pdo->prepare(
    'INSERT INTO orders (id, customer_id, status, payment_method, delivery_name, delivery_phone, delivery_address, delivery_barangay, delivery_slot, delivery_date, delivery_fee_centavos, total_centavos, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insItem = $pdo->prepare('INSERT INTO order_items (id, order_id, variant_id, quantity, price_at_time) VALUES (?, ?, ?, ?, ?)');
$insEvent = $pdo->prepare('INSERT INTO order_status_events (id, order_id, from_status, to_status, note, actor_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');

$orderCount = 0;

foreach ($plans as $i => $plan) {
    $placed = $at($plan['day'], $plan['hour']);
    if ($placed->getTimestamp() > $nowTs) {
        continue;
    }
    $customer = isset($plan['who'])
        ? $customers[$plan['who']]
        : (($i < 3 || $plan['status'] === 'PENDING') ? $customers[$i % count($customers)] : $pick($customers));

    $rawLines = $plan['lines'] ?? array_map(
        static fn() => [$pick($sellableSkus), 1 + (int) floor($rand() * 2)],
        range(1, 1 + (int) floor($rand() * 3))
    );
    $merged = [];
    foreach ($rawLines as [$s, $q]) {
        $merged[$s] = ($merged[$s] ?? 0) + $q;
    }
    $items = [];
    foreach ($merged as $s => $q) {
        $v = $bySku[$s];
        $avail = $stock[$v['id']] ?? 0;
        $qty = min($q, $avail);
        if ($qty > 0) {
            $items[] = ['v' => $v, 'q' => $qty];
        }
    }
    if (!$items) {
        continue;
    }

    $subtotal = 0;
    foreach ($items as $x) {
        $subtotal += $x['v']['price'] * $x['q'];
    }
    $fee = delivery_fee_for($subtotal);
    $cod = $plan['cod'] ?? ($i % 3 === 0);
    $slotIdx = $plan['slot'] ?? ($i % count(DELIVERY_SLOTS));
    $day = delivery_date_for($placed);

    $orderId = new_id();
    $placedSql = sql_dt($placed);
    $insOrder->execute([
        $orderId,
        $customer['id'],
        $plan['status'],
        $cod ? 'COD' : 'GCASH',
        $customer['name'] ?? 'Customer',
        $customer['phone'] ?? '0917 200 0000',
        $customer['address'] ?? 'Blk 1 Lot 1',
        $customer['barangay'] ?? 'Zone I',
        DELIVERY_SLOTS[$slotIdx],
        sql_date($day),
        $fee,
        $subtotal + $fee,
        $placedSql,
        $placedSql,
    ]);
    $orderNum = (int) $pdo->query("SELECT order_number FROM orders WHERE id = " . $pdo->quote($orderId))->fetchColumn();
    $orderCount++;
    $code = order_code($orderNum);

    foreach ($items as $x) {
        $insItem->execute([new_id(), $orderId, $x['v']['id'], $x['q'], $x['v']['price']]);
        $move($x['v'], -$x['q'], "Sale — {$code}", $placed, $orderId);
    }

    $insEvent->execute([new_id(), $orderId, null, 'PENDING', 'Placed online', $customer['id'], $placedSql]);

    if ($plan['status'] === 'CANCELLED') {
        $when = $placed->modify('+36 minutes');
        foreach ($items as $x) {
            $move($x['v'], $x['q'], "Cancelled — {$code} (back to stock)", $when, $orderId, $kiel['id']);
        }
        $insEvent->execute([new_id(), $orderId, 'PENDING', 'CANCELLED', $plan['cancelNote'] ?? 'Cancelled', $kiel['id'], sql_dt($when)]);
        continue;
    }

    if ($plan['status'] === 'PENDING') {
        continue;
    }

    $confAt = $cod
        ? (new DateTimeImmutable('@' . ($placed->getTimestamp() + 1)))
        : $past($placed->modify('+' . (int) round((0.3 + $rand()) * 3600) . ' seconds'), 30);
    $insEvent->execute([
        new_id(),
        $orderId,
        'PENDING',
        'CONFIRMED',
        $cod ? 'Auto-confirmed (Cash on delivery)' : null,
        $cod ? null : $pick($staff)['id'],
        sql_dt($confAt),
    ]);

    if ($plan['status'] === 'CONFIRMED') {
        continue;
    }

    // The admin sends the order out at the start of its slot, then marks it delivered.
    $admin = $pick($staff);
    $leave = $past($onDay($day, $slotStart[$slotIdx] - 0.25), 40);
    $insEvent->execute([new_id(), $orderId, 'CONFIRMED', 'READY', null, $admin['id'], sql_dt($leave)]);

    if ($plan['status'] === 'COMPLETED') {
        $doneAt = $past($leave->modify('+' . (int) round((0.4 + $rand()) * 3600) . ' seconds'), 20);
        $note = $cod ? 'COD ' . peso($subtotal + $fee) . ' collected' : null;
        $insEvent->execute([new_id(), $orderId, 'READY', 'COMPLETED', $note, $admin['id'], sql_dt($doneAt)]);
    }
}

// --- Counter Activity --------------------------------------------------------
$walkins = [
    ['VEG-KANGKONG-B', 3, 4, 9.1],
    ['RICE-SINAN-1', 4, 3, 10.4],
    ['EGG-LARGE-12', 2, 2, 8.7],
    ['FRT-CALAMANSI-250', 2, 2, 11.2],
    ['MEAT-LIEMPO-500', 1, 1, 9.6],
    ['VEG-KAMATIS-500', 2, 0, 6.6],
];
foreach ($walkins as [$s, $q, $d, $h]) {
    $when = $at($d, $h);
    if ($when->getTimestamp() < $nowTs) {
        $move($bySku[$s], -$q, 'Walk-in sale', $when, null, $kiel['id']);
    }
}

$move($bySku['VEG-PECHAY-250'], -3, 'Spoilage — leaves wilted, discarded', $at(3, 17.2), null, $kiel['id']);
$dmgAt = $at(2, 7.0);
$damagedId = $move($bySku['EGG-LARGE-12'], -3, 'Bruised or damaged', $dmgAt, null, $kiel['id']);
$move($bySku['EGG-LARGE-12'], 3, 'Undo of ' . movement_ref($damagedId), $dmgAt->modify('+20 minutes'), null, $ej['id']);
$move($bySku['EGG-LARGE-12'], -1, 'Bruised or damaged — 1 cracked tray', $dmgAt->modify('+22 minutes'), null, $ej['id']);

$mango = $bySku['FRT-MANGGA-1'];
$sys = $stock[$mango['id']] ?? 0;
$move($mango, -2, "Morning count (counted " . ($sys - 2) . ", system had {$sys})", $at(1, 5.8), null, $kiel['id']);

foreach ([['VEG-KANGKONG-B', 20], ['VEG-SITAW-250', 10], ['MEAT-THIGH-1', 6]] as [$s, $q]) {
    $when = $at(0, 5.4);
    if ($when->getTimestamp() < $nowTs) {
        $move($bySku[$s], $q, 'Restock — morning harvest', $when, null, $kiel['id']);
    }
}

// --- Two market price changes this morning ----------------------------------
$priceAt = $at(0, 5.5);
$changedAt = $priceAt->getTimestamp() < $nowTs ? $priceAt : (new DateTimeImmutable('@' . ($nowTs - 60)));
$updPrice = $pdo->prepare('UPDATE product_variants SET price_centavos = ?, price_updated_at = ? WHERE id = ?');
$updPrice->execute([7500, sql_dt($changedAt), $bySku['VEG-SIBUYAS-500']['id']]);
$updPrice->execute([19500, sql_dt($changedAt), $bySku['FRT-MANGGA-1']['id']]);

echo "\nSeed complete:\n";
echo '  Categories: ' . count(CATALOG_SEED) . "\n";
echo "  Products:   {$productCount}\n";
echo '  Variants:   ' . count($variants) . "\n";
echo "  Orders:     {$orderCount}\n";
echo "\nDemo accounts:\n";
echo "  ADMIN     ej@bukidbasket.ph / admin123\n";
echo "  ADMIN     kiel@bukidbasket.ph / admin123\n";
echo "  CUSTOMER  maria@example.com / customer123\n";
