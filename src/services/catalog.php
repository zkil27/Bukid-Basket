<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../meta.php';
require_once __DIR__ . '/stock.php';
require_once dirname(__DIR__, 2) . '/database/catalog-data.php';

function category_rank(string $slug): int
{
    foreach (CATALOG_SEED as $i => $c) {
        if ($c['slug'] === $slug) {
            return $i;
        }
    }
    return 99;
}

/** Storefront catalog: active products with >= 1 active variant + ledger stock. */
function load_shop_catalog(): array
{
    try {
        $pdo = db();
        $rows = $pdo->query("
            SELECT p.id AS p_id, p.name AS p_name, p.description, p.image_url,
                   c.name AS c_name, c.slug AS c_slug,
                   v.id AS v_id, v.name AS v_name, v.sku, v.price_centavos, v.price_updated_at
            FROM products p
            JOIN categories c ON c.id = p.category_id
            JOIN product_variants v ON v.product_id = p.id AND v.active = 1
            WHERE p.active = 1
            ORDER BY c.name ASC, p.name ASC, v.price_centavos DESC
        ")->fetchAll();

        $stockRows = $pdo->query("
            SELECT sm.variant_id, SUM(sm.quantity) AS stock
            FROM stock_movements sm
            JOIN product_variants v ON v.id = sm.variant_id AND v.active = 1
            JOIN products p ON p.id = v.product_id AND p.active = 1
            GROUP BY sm.variant_id
        ")->fetchAll();
        $stock = [];
        foreach ($stockRows as $s) {
            $stock[$s['variant_id']] = (int) $s['stock'];
        }

        $todayStart = start_of_shop_day();
        $byProduct = [];
        foreach ($rows as $r) {
            $pid = $r['p_id'];
            if (!isset($byProduct[$pid])) {
                $byProduct[$pid] = [
                    'id' => $pid,
                    'name' => $r['p_name'],
                    'description' => $r['description'],
                    'image' => $r['image_url'] ?: image_for_name($r['p_name']),
                    'category' => ['name' => $r['c_name'], 'slug' => $r['c_slug']],
                    'dishes' => dishes_for_name($r['p_name']),
                    'variants' => [],
                ];
            }
            $updatedToday = $r['price_updated_at'] !== null && parse_utc($r['price_updated_at']) >= $todayStart;
            $byProduct[$pid]['variants'][] = [
                'id' => $r['v_id'],
                'name' => $r['v_name'],
                'sku' => $r['sku'],
                'price' => (int) $r['price_centavos'],
                'stock' => $stock[$r['v_id']] ?? 0,
                'priceChangedToday' => $updatedToday,
            ];
        }

        $products = array_values($byProduct);
        usort($products, static fn($a, $b) => (category_rank($a['category']['slug']) <=> category_rank($b['category']['slug'])) ?: strcmp($a['name'], $b['name']));

        $catMap = [];
        foreach ($products as $p) {
            $slug = $p['category']['slug'];
            if (!isset($catMap[$slug])) {
                $catMap[$slug] = ['name' => $p['category']['name'], 'slug' => $slug, 'count' => 0];
            }
            $catMap[$slug]['count']++;
        }

        return ['products' => $products, 'categories' => array_values($catMap), 'offline' => false];
    } catch (Throwable) {
        return fallback_catalog();
    }
}

function fallback_catalog(): array
{
    $products = [];
    $cats = [];
    foreach (CATALOG_SEED as $ci => $c) {
        $cats[] = ['name' => $c['name'], 'slug' => $c['slug'], 'count' => count($c['products'])];
        foreach ($c['products'] as $pi => $p) {
            $vars = [];
            foreach ($p['variants'] as $vi => $v) {
                $vars[] = [
                    'id' => "static-{$ci}-{$pi}-{$vi}",
                    'name' => $v['name'],
                    'sku' => $v['sku'],
                    'price' => $v['price_centavos'],
                    'stock' => $v['opening'],
                    'priceChangedToday' => false,
                ];
            }
            $products[] = [
                'id' => "static-p-{$ci}-{$pi}",
                'name' => $p['name'],
                'description' => $p['description'],
                'image' => $p['image'],
                'category' => ['name' => $c['name'], 'slug' => $c['slug']],
                'dishes' => $p['dishes'],
                'variants' => $vars,
            ];
        }
    }
    return ['products' => $products, 'categories' => $cats, 'offline' => true];
}

function load_shop_product(string $id): array
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT p.id AS p_id, p.name AS p_name, p.description, p.image_url,
                   c.name AS c_name, c.slug AS c_slug,
                   v.id AS v_id, v.name AS v_name, v.sku, v.price_centavos, v.price_updated_at
            FROM products p
            JOIN categories c ON c.id = p.category_id
            JOIN product_variants v ON v.product_id = p.id AND v.active = 1
            WHERE p.id = ? AND p.active = 1
            ORDER BY v.price_centavos DESC
        ");
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return ['product' => null, 'offline' => false];
        }
        $vids = array_map(static fn($r) => $r['v_id'], $rows);
        $stock = stock_by_variant($pdo, $vids);
        $todayStart = start_of_shop_day();
        $first = $rows[0];
        $product = [
            'id' => $first['p_id'],
            'name' => $first['p_name'],
            'description' => $first['description'],
            'image' => $first['image_url'] ?: image_for_name($first['p_name']),
            'category' => ['name' => $first['c_name'], 'slug' => $first['c_slug']],
            'dishes' => dishes_for_name($first['p_name']),
            'variants' => array_map(static fn($r) => [
                'id' => $r['v_id'],
                'name' => $r['v_name'],
                'sku' => $r['sku'],
                'price' => (int) $r['price_centavos'],
                'stock' => $stock[$r['v_id']] ?? 0,
                'priceChangedToday' => $r['price_updated_at'] !== null && parse_utc($r['price_updated_at']) >= $todayStart,
            ], $rows),
        ];
        return ['product' => $product, 'offline' => false];
    } catch (Throwable) {
        $fb = fallback_catalog();
        foreach ($fb['products'] as $p) {
            if ($p['id'] === $id) {
                return ['product' => $p, 'offline' => true];
            }
        }
        return ['product' => null, 'offline' => true];
    }
}

/** Full admin inventory (all active + archived products/variants with stock + FIFO age). */
function load_inventory(): array
{
    $pdo = db();
    $rows = $pdo->query("
        SELECT p.id AS p_id, p.name AS p_name, p.description, p.image_url, p.active AS p_active,
               c.id AS c_id, c.name AS c_name, c.slug AS c_slug,
               v.id AS v_id, v.name AS v_name, v.sku, v.price_centavos, v.price_updated_at, v.active AS v_active
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN product_variants v ON v.product_id = p.id
        ORDER BY c.name ASC, p.name ASC, v.price_centavos DESC
    ")->fetchAll();

    $stock = stock_by_variant($pdo);
    $oldest = oldest_inflow_by_variant($pdo);
    $todayStart = start_of_shop_day();
    $now = now_utc();

    $byProd = [];
    foreach ($rows as $r) {
        $pid = $r['p_id'];
        if (!isset($byProd[$pid])) {
            $byProd[$pid] = [
                'id' => $pid,
                'name' => $r['p_name'],
                'description' => $r['description'],
                'image' => $r['image_url'] ?: image_for_name($r['p_name']),
                'active' => (bool) $r['p_active'],
                'category' => ['id' => $r['c_id'], 'name' => $r['c_name'], 'slug' => $r['c_slug']],
                'variants' => [],
            ];
        }
        if ($r['v_id'] !== null) {
            $vid = $r['v_id'];
            $qty = $stock[$vid] ?? 0;
            $oldestDays = null;
            if ($qty > 0 && isset($oldest[$vid])) {
                $diffSec = $now->getTimestamp() - parse_utc($oldest[$vid])->getTimestamp();
                $oldestDays = max(0, intdiv($diffSec, 86400));
            }
            $toSale = $qty > 0 && $oldestDays !== null && $oldestDays >= TO_SALE_AFTER_DAYS;
            $byProd[$pid]['variants'][] = [
                'id' => $vid,
                'productId' => $pid,
                'productName' => $r['p_name'],
                'categoryName' => $r['c_name'],
                'categorySlug' => $r['c_slug'],
                'name' => $r['v_name'],
                'sku' => $r['sku'],
                'price' => (int) $r['price_centavos'],
                'priceChangedToday' => $r['price_updated_at'] !== null && parse_utc($r['price_updated_at']) >= $todayStart,
                'active' => (bool) $r['v_active'],
                'stock' => $qty,
                'oldestDays' => $oldestDays,
                'toSale' => $toSale,
            ];
        }
    }
    $list = array_values($byProd);
    usort($list, static fn($a, $b) => (category_rank($a['category']['slug']) <=> category_rank($b['category']['slug'])) ?: strcmp($a['name'], $b['name']));
    return $list;
}

function sellable_variants(array $inv): array
{
    $out = [];
    foreach ($inv as $p) {
        if (!$p['active']) {
            continue;
        }
        foreach ($p['variants'] as $v) {
            if ($v['active']) {
                $out[] = $v;
            }
        }
    }
    return $out;
}

function load_product_for_admin(string $id): ?array
{
    foreach (load_inventory() as $p) {
        if ($p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

function list_categories(): array
{
    return db()->query("
        SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id, c.name, c.slug
        ORDER BY c.name ASC
    ")->fetchAll();
}

function slugify(string $name): string
{
    $s = strtolower($name);
    if (function_exists('iconv')) {
        $tr = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($tr !== false) {
            $s = $tr;
        }
    }
    $s = str_replace('&', 'and', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

function save_product(string $adminId, array $input): array
{
    $id = isset($input['id']) && $input['id'] !== '' ? (string) $input['id'] : null;
    $name = trim((string) ($input['name'] ?? ''));
    $categoryId = trim((string) ($input['categoryId'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $imageUrl = trim((string) ($input['imageUrl'] ?? ''));
    $variantsInput = $input['variants'] ?? [];

    if (mb_strlen($name) < 2) {
        throw new UserError('Enter a product name.');
    }
    if ($categoryId === '') {
        throw new UserError('Pick a category.');
    }
    if (mb_strlen($description) > 300) {
        throw new UserError('Description must be 300 characters or less.');
    }
    if ($imageUrl !== '' && !str_starts_with($imageUrl, '/')) {
        throw new UserError('Image path must start with /.');
    }
    if (!is_array($variantsInput) || count($variantsInput) < 1) {
        throw new UserError('Add at least one variant.');
    }

    $variants = [];
    $skus = [];
    foreach ($variantsInput as $v) {
        $vName = trim((string) ($v['name'] ?? ''));
        $sku = strtoupper(trim((string) ($v['sku'] ?? '')));
        $price = filter_var($v['price'] ?? null, FILTER_VALIDATE_INT);
        $opening = isset($v['opening']) && $v['opening'] !== '' ? filter_var($v['opening'], FILTER_VALIDATE_INT) : 0;
        if ($vName === '') {
            throw new UserError('Every variant needs a name (e.g. 500g, Whole 1kg).');
        }
        if (!preg_match('/^[A-Z0-9-]{3,40}$/', $sku)) {
            throw new UserError('SKUs use letters, numbers and dashes only (3–40 chars).');
        }
        if ($price === false || $price < 100 || $price > 10_000_000) {
            throw new UserError('Price must be at least ₱1.');
        }
        if ($opening === false || $opening < 0 || $opening > 100_000) {
            throw new UserError('Opening stock must be 0 or higher.');
        }
        if (isset($skus[$sku])) {
            throw new UserError('Two variants share the same SKU.');
        }
        $skus[$sku] = true;
        $variants[] = [
            'id' => isset($v['id']) && $v['id'] !== '' ? (string) $v['id'] : null,
            'name' => $vName,
            'sku' => $sku,
            'price' => (int) $price,
            'opening' => (int) $opening,
            'active' => !isset($v['active']) || (bool) $v['active'],
        ];
    }

    $productId = tx(static function (PDO $pdo) use ($adminId, $id, $name, $categoryId, $description, $imageUrl, $variants, $skus): string {
        $skuList = array_keys($skus);
        $existingIds = array_values(array_filter(array_map(static fn($v) => $v['id'], $variants)));
        $phSku = in_placeholders(count($skuList));
        if ($existingIds) {
            $phIds = in_placeholders(count($existingIds));
            $chk = $pdo->prepare("SELECT sku FROM product_variants WHERE sku IN ({$phSku}) AND id NOT IN ({$phIds}) LIMIT 1");
            $chk->execute(array_merge($skuList, $existingIds));
        } else {
            $chk = $pdo->prepare("SELECT sku FROM product_variants WHERE sku IN ({$phSku}) LIMIT 1");
            $chk->execute($skuList);
        }
        $clash = $chk->fetchColumn();
        if ($clash) {
            throw new UserError("SKU {$clash} is already used by another product.");
        }

        $descVal = $description !== '' ? $description : null;
        $imgVal = $imageUrl !== '' ? $imageUrl : null;
        if ($id !== null) {
            $upd = $pdo->prepare('UPDATE products SET name = ?, category_id = ?, description = ?, image_url = ?, updated_at = NOW(3) WHERE id = ?');
            $upd->execute([$name, $categoryId, $descVal, $imgVal, $id]);
            $prodId = $id;
        } else {
            $prodId = new_id();
            $ins = $pdo->prepare('INSERT INTO products (id, name, category_id, description, image_url, active) VALUES (?, ?, ?, ?, ?, 1)');
            $ins->execute([$prodId, $name, $categoryId, $descVal, $imgVal]);
        }

        $existing = [];
        if ($existingIds) {
            $phIds = in_placeholders(count($existingIds));
            $st = $pdo->prepare("SELECT id, product_id, price_centavos FROM product_variants WHERE id IN ({$phIds})");
            $st->execute($existingIds);
            foreach ($st->fetchAll() as $row) {
                $existing[$row['id']] = $row;
            }
        }

        $updVar = $pdo->prepare('UPDATE product_variants SET name = ?, sku = ?, active = ?, price_centavos = ?, price_updated_at = IF(price_centavos <> ?, NOW(3), price_updated_at), updated_at = NOW(3) WHERE id = ?');
        $insVar = $pdo->prepare('INSERT INTO product_variants (id, product_id, name, sku, price_centavos, active) VALUES (?, ?, ?, ?, ?, ?)');
        $insMove = $pdo->prepare('INSERT INTO stock_movements (id, variant_id, quantity, reason, actor_id, created_at) VALUES (?, ?, ?, ?, ?, NOW(3))');

        foreach ($variants as $v) {
            if ($v['id'] !== null) {
                $cur = $existing[$v['id']] ?? null;
                if (!$cur || $cur['product_id'] !== $prodId) {
                    throw new UserError('A variant no longer exists.');
                }
                $updVar->execute([$v['name'], $v['sku'], $v['active'] ? 1 : 0, $v['price'], $v['price'], $v['id']]);
            } else {
                $vid = new_id();
                $insVar->execute([$vid, $prodId, $v['name'], $v['sku'], $v['price'], $v['active'] ? 1 : 0]);
                if ($v['opening'] > 0) {
                    $insMove->execute([new_id(), $vid, $v['opening'], 'Opening stock', $adminId]);
                }
            }
        }
        return $prodId;
    });

    return ['id' => $productId];
}

function set_product_active(string $productId, bool $active): void
{
    $stmt = db()->prepare('UPDATE products SET active = ?, updated_at = NOW(3) WHERE id = ?');
    $stmt->execute([$active ? 1 : 0, $productId]);
}

/** @param list<array{variantId: string, price: int}> $changes */
function save_prices(array $changes): array
{
    $clean = [];
    foreach ($changes as $c) {
        $vid = (string) ($c['variantId'] ?? '');
        $price = filter_var($c['price'] ?? null, FILTER_VALIDATE_INT);
        if ($vid === '' || $price === false || $price < 100 || $price > 10_000_000) {
            throw new UserError('Prices must be at least ₱1.');
        }
        $clean[$vid] = (int) $price;
    }
    if (!$clean) {
        return ['changed' => []];
    }

    $changed = tx(static function (PDO $pdo) use ($clean): array {
        $ids = array_keys($clean);
        $ph = in_placeholders(count($ids));
        $stmt = $pdo->prepare("SELECT id, price_centavos FROM product_variants WHERE id IN ({$ph})");
        $stmt->execute($ids);
        $was = [];
        foreach ($stmt->fetchAll() as $r) {
            $was[$r['id']] = (int) $r['price_centavos'];
        }
        $out = [];
        foreach ($clean as $vid => $to) {
            if (!isset($was[$vid]) || $was[$vid] === $to) {
                continue;
            }
            $out[] = ['variantId' => $vid, 'from' => $was[$vid], 'to' => $to];
        }
        if ($out) {
            $caseSql = 'CASE id ';
            $params = [];
            $updIds = [];
            foreach ($out as $c) {
                $caseSql .= 'WHEN ? THEN ? ';
                $params[] = $c['variantId'];
                $params[] = $c['to'];
                $updIds[] = $c['variantId'];
            }
            $caseSql .= 'END';
            $inPh = in_placeholders(count($updIds));
            $sql = "UPDATE product_variants SET price_centavos = {$caseSql}, price_updated_at = NOW(3), updated_at = NOW(3) WHERE id IN ({$inPh})";
            $pdo->prepare($sql)->execute(array_merge($params, $updIds));
        }
        return $out;
    });

    return ['changed' => $changed];
}

function create_category(string $name): array
{
    $clean = trim($name);
    if (mb_strlen($clean) < 2) {
        throw new UserError('Enter a category name.');
    }
    $slug = slugify($clean);
    if ($slug === '') {
        throw new UserError('Use letters or numbers in the name.');
    }
    $pdo = db();
    $chk = $pdo->prepare('SELECT name FROM categories WHERE slug = ? LIMIT 1');
    $chk->execute([$slug]);
    $existing = $chk->fetchColumn();
    if ($existing) {
        throw new UserError("\"{$existing}\" already exists.");
    }
    $id = new_id();
    $pdo->prepare('INSERT INTO categories (id, name, slug) VALUES (?, ?, ?)')->execute([$id, $clean, $slug]);
    return ['id' => $id];
}

function rename_category(string $id, string $name): void
{
    $clean = trim($name);
    if (mb_strlen($clean) < 2) {
        throw new UserError('Enter a category name.');
    }
    db()->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$clean, $id]);
}
