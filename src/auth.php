<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/meta.php';
require_once __DIR__ . '/http.php';

function ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
}

/**
 * Current signed-in user fetched fresh from the database once per request.
 * Never trusts stale session copies for role or saved delivery profile.
 */
function current_user(): ?array
{
    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }
    $cached = true;
    ensure_session();
    $uid = $_SESSION['user_id'] ?? null;
    if (!is_string($uid) || $uid === '') {
        return null;
    }
    try {
        $stmt = db()->prepare(
            'SELECT id, name, email, role, phone, address, barangay FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!$row) {
            unset($_SESSION['user_id']);
            return null;
        }
        $row['role'] = as_role($row['role']);
        $user = $row;
        return $user;
    } catch (Throwable) {
        return null;
    }
}

function app_secret(): string
{
    $s = env('APP_SECRET', '');
    if ($s !== null && $s !== '') {
        return $s;
    }
    if (env('APP_ENV', 'development') === 'production') {
        throw new RuntimeException('APP_SECRET is not set');
    }
    return 'dev-only-tracking-secret';
}

function csrf_token(): string
{
    ensure_session();
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $expected = csrf_token();
    $got = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($got) || $got === '' || !hash_equals($expected, $got)) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_err('Session expired. Reload the page and try again.', 403);
        }
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

function require_login(string $next = '/'): array
{
    $u = current_user();
    if ($u) {
        return $u;
    }
    if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        json_err('Please sign in first.', 401);
    }
    redirect('/login?next=' . rawurlencode($next));
}

/** @param list<string> $roles */
function require_role(array $roles, string $next = '/'): array
{
    $u = require_login($next);
    if (!in_array($u['role'], $roles, true)) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_err('Access denied.', 403);
        }
        redirect('/login?denied=1');
    }
    return $u;
}

function require_admin(string $next = '/admin'): array
{
    return require_role(['ADMIN'], $next);
}

function login_user(string $email, string $password): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        throw new UserError('Enter your email and password.');
    }
    $stmt = db()->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, (string) $row['password_hash'])) {
        throw new UserError('Wrong email or password.');
    }
    ensure_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $row['id'];
    return [
        'id' => $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => as_role($row['role']),
    ];
}

function register_user(string $name, string $email, string $password): array
{
    $cleanName = trim($name);
    $cleanEmail = strtolower(trim($email));
    if (mb_strlen($cleanName) < 2) {
        throw new UserError('Enter your name.');
    }
    if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
        throw new UserError('Enter a valid email address.');
    }
    if (strlen($password) < 6) {
        throw new UserError('Password must be at least 6 characters.');
    }

    $pdo = db();
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->execute([$cleanEmail]);
    if ($chk->fetchColumn()) {
        throw new UserError('That email already has an account.');
    }

    $id = new_id();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $ins = $pdo->prepare('INSERT INTO users (id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$id, $cleanName, $cleanEmail, $hash, 'CUSTOMER']);

    ensure_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    return ['id' => $id, 'name' => $cleanName, 'email' => $cleanEmail, 'role' => 'CUSTOMER'];
}

function logout_user(): void
{
    ensure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

/** Validate Philippine delivery contact details. @return array{phone: string, address: string, barangay: string} */
function validate_contact(array $input): array
{
    $phone = trim((string) ($input['phone'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));
    $barangay = trim((string) ($input['barangay'] ?? ''));

    if (!preg_match(PHONE_RE, $phone)) {
        throw new UserError('Enter a mobile number like 0917 123 4567.');
    }
    if (mb_strlen($address) < 5) {
        throw new UserError('Enter a street address or house number.');
    }
    if (mb_strlen($barangay) < 2) {
        throw new UserError('Enter your barangay.');
    }
    return ['phone' => $phone, 'address' => $address, 'barangay' => $barangay];
}

function save_delivery_details(string $userId, array $input): array
{
    $contact = validate_contact($input);
    $stmt = db()->prepare('UPDATE users SET phone = ?, address = ?, barangay = ?, updated_at = NOW(3) WHERE id = ?');
    $stmt->execute([$contact['phone'], $contact['address'], $contact['barangay'], $userId]);
    return $contact;
}

// ── Order tracking access tokens (HMAC + last 4 phone digits) ─────────────────
function track_token(int $orderNumber): string
{
    $raw = hash_hmac('sha256', "track:{$orderNumber}", app_secret(), true);
    $b64 = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    return substr($b64, 0, 22);
}

function valid_track_token(int $orderNumber, ?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }
    return hash_equals(track_token($orderNumber), $token);
}

function phone_last4(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    return substr($digits, -4);
}

function same_last4(string $phone, string $typed): bool
{
    $a = phone_last4($phone);
    $b = preg_replace('/\D/', '', $typed) ?? '';
    return strlen($a) === 4 && strlen($b) === 4 && hash_equals($a, $b);
}
