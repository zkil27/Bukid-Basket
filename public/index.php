<?php
declare(strict_types=1);

/**
 * Bukid Basket — Front Controller & Router
 */

if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $candidate = __DIR__ . $reqPath;
    if ($reqPath !== '/' && is_file($candidate)) {
        return false;
    }
}

require_once __DIR__ . '/../src/env.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/meta.php';
require_once __DIR__ . '/../src/http.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/services/stock.php';
require_once __DIR__ . '/../src/services/catalog.php';
require_once __DIR__ . '/../src/services/orders.php';
require_once __DIR__ . '/../src/services/dashboard.php';
require_once __DIR__ . '/../views/partials/brand.php';
require_once __DIR__ . '/../views/partials/sack-label.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $path !== '/' ? rtrim($path, '/') : '/';

// ── Role Gate for /admin ───────────────────────────────────────────
if (is_gated($path)) {
    $u = require_login($path);
    if (!can_open($u['role'], $path)) {
        redirect('/login?denied=1');
    }
}

// ── POST API & Form Endpoints ─────────────────────────────────────────────────
if ($method === 'POST') {
    verify_csrf();
    $isApi = str_starts_with($path, '/api/');
    try {
        // Auth & Account (form posts with redirects)
        if ($path === '/api/auth/login') {
            $next = (string) ($_POST['next'] ?? '/');
            try {
                $u = login_user((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
                $dest = ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//')) ? $next : home_for($u['role']);
                if ($dest === '/' && is_staff($u['role'])) {
                    $dest = home_for($u['role']);
                }
                redirect($dest);
            } catch (UserError $ex) {
                flash_set('err', $ex->getMessage());
                redirect('/login' . ($next !== '/' ? '?next=' . rawurlencode($next) : ''));
            }
        }
        if ($path === '/api/auth/register') {
            $next = (string) ($_POST['next'] ?? '/');
            try {
                register_user((string) ($_POST['name'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
                $dest = ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//')) ? $next : '/';
                redirect($dest);
            } catch (UserError $ex) {
                flash_set('err', $ex->getMessage());
                redirect('/register' . ($next !== '/' ? '?next=' . rawurlencode($next) : ''));
            }
        }
        if ($path === '/api/auth/logout') {
            logout_user();
            redirect('/');
        }
        if ($path === '/api/account/delivery') {
            $u = require_login('/account');
            try {
                save_delivery_details($u['id'], $_POST);
                flash_set('ok', 'Delivery details saved.');
            } catch (UserError $ex) {
                flash_set('err', $ex->getMessage());
            }
            redirect('/account');
        }
        if ($path === '/api/track/verify') {
            $codeInput = (string) ($_POST['code'] ?? '');
            $last4 = (string) ($_POST['last4'] ?? '');
            $num = parse_order_code($codeInput);
            if (!$num) {
                flash_set('err', 'Enter a valid order code like BB-0005.');
                redirect('/track');
            }
            $st = db()->prepare('SELECT delivery_phone FROM orders WHERE order_number = ? LIMIT 1');
            $st->execute([$num]);
            $phone = $st->fetchColumn();
            if (!$phone || !same_last4((string) $phone, $last4)) {
                flash_set('err', 'Those 4 digits did not match that order.');
                redirect('/track?code=' . rawurlencode(order_code($num)));
            }
            $tok = track_token($num);
            redirect('/track?code=' . rawurlencode(order_code($num)) . '&t=' . rawurlencode($tok));
        }
        if ($path === '/api/orders/cancel-customer') {
            $u = require_login('/track');
            $code = (string) ($_POST['code'] ?? '');
            try {
                cancel_customer_order($u, $code, (string) ($_POST['reason'] ?? ''));
                flash_set('ok', "Order {$code} cancelled and stock returned to the ledger.");
            } catch (UserError $ex) {
                flash_set('err', $ex->getMessage());
            }
            redirect('/track?code=' . rawurlencode($code));
        }

        // JSON APIs
        $body = input_json();

        if ($path === '/api/checkout') {
            $u = require_login('/checkout');
            json_ok(place_order($u['id'], $body));
        }

        // Admin Order APIs
        if ($path === '/api/admin/orders/detail') {
            require_admin();
            $d = get_order_detail((string) ($body['orderId'] ?? ''));
            if (!$d) {
                json_err('Order not found.', 404);
            }
            json_ok($d);
        }
        if ($path === '/api/admin/orders/advance') {
            $admin = require_admin();
            json_ok(advance_order($admin['id'], (string) ($body['orderId'] ?? ''), (string) ($body['from'] ?? '')));
        }
        if ($path === '/api/admin/orders/advance-bulk') {
            $admin = require_admin();
            json_ok(advance_orders($admin['id'], (array) ($body['orderIds'] ?? []), (string) ($body['from'] ?? '')));
        }
        if ($path === '/api/admin/orders/revert') {
            $admin = require_admin();
            revert_order($admin['id'], (string) ($body['orderId'] ?? ''), (string) ($body['from'] ?? ''), (string) ($body['to'] ?? ''));
            json_ok();
        }
        if ($path === '/api/admin/orders/cancel') {
            $admin = require_admin();
            cancel_order_admin($admin['id'], (string) ($body['orderId'] ?? ''), (string) ($body['reason'] ?? ''));
            json_ok();
        }
        if ($path === '/api/admin/orders/cancel-bulk') {
            $admin = require_admin();
            json_ok(cancel_orders_bulk($admin['id'], (array) ($body['orderIds'] ?? []), (string) ($body['reason'] ?? '')));
        }

        // Admin Stock & Ledger APIs
        if ($path === '/api/admin/stock/adjust') {
            $admin = require_admin();
            json_ok(adjust_stock($admin['id'], (string) ($body['variantId'] ?? ''), (int) ($body['quantity'] ?? 0), (string) ($body['reason'] ?? ''), (string) ($body['note'] ?? '')));
        }
        if ($path === '/api/admin/stock/counter') {
            $admin = require_admin();
            json_ok(counter_movement($admin['id'], (string) ($body['movementId'] ?? ''), (string) ($body['label'] ?? 'Undo')));
        }
        if ($path === '/api/admin/stock/walkin') {
            $admin = require_admin();
            json_ok(walk_in_sale($admin['id'], (string) ($body['variantId'] ?? ''), (int) ($body['qty'] ?? 0)));
        }
        if ($path === '/api/admin/stock/count') {
            $admin = require_admin();
            json_ok(save_morning_count($admin['id'], (array) ($body['counts'] ?? [])));
        }

        // Admin Catalog & Prices APIs
        if ($path === '/api/admin/prices') {
            require_admin();
            $parsed = [];
            foreach ((array) ($body['changes'] ?? []) as $c) {
                $cents = parse_peso_input((string) ($c['priceInput'] ?? ''));
                if ($cents === null) {
                    throw new UserError('Enter valid peso amounts (e.g. 180 or 175.50).');
                }
                $parsed[] = ['variantId' => (string) ($c['variantId'] ?? ''), 'price' => $cents];
            }
            json_ok(save_prices($parsed));
        }
        if ($path === '/api/admin/products/save') {
            $admin = require_admin();
            $variants = [];
            foreach ((array) ($body['variants'] ?? []) as $v) {
                $cents = parse_peso_input((string) ($v['priceInput'] ?? ''));
                if ($cents === null) {
                    throw new UserError('Enter valid peso prices for every variant.');
                }
                $variants[] = [
                    'id' => $v['id'] ?? null,
                    'name' => $v['name'] ?? '',
                    'sku' => $v['sku'] ?? '',
                    'price' => $cents,
                    'opening' => $v['opening'] ?? 0,
                    'active' => !isset($v['active']) || (bool) $v['active'],
                ];
            }
            $body['variants'] = $variants;
            json_ok(save_product($admin['id'], $body));
        }
        if ($path === '/api/admin/products/active') {
            require_admin();
            set_product_active((string) ($body['productId'] ?? ''), (bool) ($body['active'] ?? false));
            json_ok();
        }
        if ($path === '/api/admin/categories/create') {
            require_admin();
            json_ok(create_category((string) ($body['name'] ?? '')));
        }
        if ($path === '/api/admin/categories/rename') {
            require_admin();
            rename_category((string) ($body['id'] ?? ''), (string) ($body['name'] ?? ''));
            json_ok();
        }

        if ($isApi) {
            json_err('Unknown endpoint.', 404);
        }
    } catch (UserError $ex) {
        if ($isApi && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            json_err($ex->getMessage(), 400);
        }
        flash_set('err', $ex->getMessage());
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    } catch (Throwable $ex) {
        $msg = env('APP_ENV', 'development') === 'production'
            ? 'Database error. Please try again.'
            : $ex->getMessage();
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            json_err($msg, 500);
        }
        flash_set('err', $msg);
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}

// ── GET Routes ────────────────────────────────────────────────────────────────
$user = current_user();

// Storefront routes share one catalog load per request
$shopCatalog = static function (): array {
    static $c = null;
    return $c ??= load_shop_catalog();
};

if ($path === '/') {
    $cat = $shopCatalog();
    render('shop', 'shop/home', ['title' => "Today's Harvest", 'user' => $user, 'catalog' => $cat]);
    exit;
}
if (preg_match('#^/p/([^/]+)$#', $path, $m)) {
    $res = load_shop_product($m[1]);
    if (!$res['product']) {
        http_response_code(404);
        render('shop', 'shop/static', ['title' => 'Not Found', 'user' => $user, 'catalog' => $shopCatalog(), 'page' => 'notfound']);
        exit;
    }
    $cat = $shopCatalog();
    $related = array_values(array_filter(
        $cat['products'],
        static fn($p) => $p['category']['slug'] === $res['product']['category']['slug'] && $p['id'] !== $res['product']['id']
    ));
    render('shop', 'shop/product', [
        'title' => split_name($res['product']['name'])['local'],
        'user' => $user,
        'catalog' => $cat,
        'product' => $res['product'],
        'offline' => $res['offline'],
        'related' => array_slice($related, 0, 3),
    ]);
    exit;
}
if ($path === '/checkout') {
    render('shop', 'shop/checkout', [
        'title' => 'Checkout',
        'user' => $user,
        'catalog' => $shopCatalog(),
        'slots' => $user ? slot_availability() : null,
    ]);
    exit;
}
if ($path === '/track') {
    $codeInput = trim((string) ($_GET['code'] ?? ''));
    $token = isset($_GET['t']) ? (string) $_GET['t'] : null;
    $placed = !empty($_GET['placed']);
    $lookup = null;
    if ($codeInput !== '') {
        $num = parse_order_code($codeInput);
        $lookup = $num ? lookup_order($num, $user, $token) : ['kind' => 'missing'];
    }
    render('shop', 'shop/track', [
        'title' => 'Track Order',
        'user' => $user,
        'catalog' => $shopCatalog(),
        'codeInput' => $codeInput,
        'token' => $token,
        'lookup' => $lookup,
        'placed' => $placed,
    ]);
    exit;
}
if (preg_match('#^/receipt/([^/]+)$#', $path, $m)) {
    $code = $m[1];
    $num = parse_order_code($code);
    $token = isset($_GET['t']) ? (string) $_GET['t'] : null;
    $lookup = $num ? lookup_order($num, $user, $token) : ['kind' => 'missing'];
    render('shop', 'shop/receipt', [
        'title' => 'Receipt ' . $code,
        'user' => $user,
        'catalog' => $shopCatalog(),
        'code' => $code,
        'lookup' => $lookup,
    ]);
    exit;
}
if ($path === '/account') {
    $u = require_login('/account');
    render('shop', 'shop/account', [
        'title' => 'My Account',
        'user' => $u,
        'catalog' => $shopCatalog(),
        'orders' => list_my_orders($u['id']),
    ]);
    exit;
}
if ($path === '/login' || $path === '/register') {
    if ($user) {
        redirect(home_for($user['role']));
    }
    render('shop', 'shop/auth', [
        'title' => $path === '/register' ? 'Create Account' : 'Sign In',
        'user' => null,
        'catalog' => $shopCatalog(),
        'mode' => $path === '/register' ? 'register' : 'login',
        'next' => (string) ($_GET['next'] ?? '/'),
        'denied' => !empty($_GET['denied']),
    ]);
    exit;
}
if ($path === '/about' || $path === '/contact') {
    render('shop', 'shop/static', [
        'title' => $path === '/about' ? 'About' : 'Contact',
        'user' => $user,
        'catalog' => $shopCatalog(),
        'page' => ltrim($path, '/'),
    ]);
    exit;
}

// ── Admin GET Routes ──────────────────────────────────────────────────────────
if (str_starts_with($path, '/admin')) {
    $admin = require_admin($path);
    $counts = order_counts();

    if ($path === '/admin') {
        render('admin', 'admin/dashboard', ['title' => 'Today', 'user' => $admin, 'counts' => $counts, 'dash' => get_dashboard()]);
        exit;
    }
    if ($path === '/admin/orders') {
        $st = strtoupper((string) ($_GET['status'] ?? 'ALL'));
        if ($st !== 'ALL' && !isset(STATUS_META[$st])) {
            $st = 'ALL';
        }
        render('admin', 'admin/orders', ['title' => 'Orders', 'user' => $admin, 'counts' => $counts, 'statusFilter' => $st, 'orders' => list_admin_orders($st)]);
        exit;
    }
    if ($path === '/admin/stock') {
        render('admin', 'admin/stock', ['title' => 'Stock Desk', 'user' => $admin, 'counts' => $counts, 'variants' => sellable_variants(load_inventory())]);
        exit;
    }
    if ($path === '/admin/ledger') {
        $vid = trim((string) ($_GET['variant'] ?? ''));
        $days = isset($_GET['days']) ? max(1, (int) $_GET['days']) : 7;
        $kind = trim((string) ($_GET['kind'] ?? 'all'));
        render('admin', 'admin/ledger', [
            'title' => 'Stock Ledger',
            'user' => $admin,
            'counts' => $counts,
            'ledger' => load_ledger($vid !== '' ? $vid : null, $days, $kind),
            'variants' => ledger_variants(),
            'variantId' => $vid,
            'days' => $days,
            'kind' => $kind,
        ]);
        exit;
    }
    if ($path === '/admin/prices') {
        render('admin', 'admin/prices', ['title' => "Today's Prices", 'user' => $admin, 'counts' => $counts, 'variants' => sellable_variants(load_inventory())]);
        exit;
    }
    if ($path === '/admin/products') {
        render('admin', 'admin/products', ['title' => 'Products', 'user' => $admin, 'counts' => $counts, 'products' => load_inventory()]);
        exit;
    }
    if ($path === '/admin/products/new') {
        render('admin', 'admin/product-form', ['title' => 'New Product', 'user' => $admin, 'counts' => $counts, 'product' => null, 'categories' => list_categories(), 'images' => product_images()]);
        exit;
    }
    if (preg_match('#^/admin/products/([^/]+)$#', $path, $m)) {
        $prod = load_product_for_admin($m[1]);
        if (!$prod) {
            redirect('/admin/products');
        }
        render('admin', 'admin/product-form', ['title' => 'Edit ' . $prod['name'], 'user' => $admin, 'counts' => $counts, 'product' => $prod, 'categories' => list_categories(), 'images' => product_images()]);
        exit;
    }
    if ($path === '/admin/categories') {
        render('admin', 'admin/categories', ['title' => 'Categories', 'user' => $admin, 'counts' => $counts, 'categories' => list_categories()]);
        exit;
    }
}

http_response_code(404);
render('shop', 'shop/static', ['title' => 'Not Found', 'user' => $user, 'catalog' => $shopCatalog(), 'page' => 'notfound']);
