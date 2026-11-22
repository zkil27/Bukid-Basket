<?php
declare(strict_types=1);

/**
 * Consolidated domain rules: money (integer centavos), Asia/Manila time,
 * order statuses, delivery slots, Dasmariñas barangays, and role gates.
 */

// ── Money (integer centavos only; never float) ────────────────────────────────
const FREE_DELIVERY_MIN = 50000; // ₱500.00
const DELIVERY_FEE = 5000;       // ₱50.00

function format_thousands(int $whole): string
{
    $sign = $whole < 0 ? '-' : '';
    $digits = (string) abs($whole);
    return $sign . preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits);
}

/** "₱1,234" when whole pesos, "₱1,234.50" otherwise. */
function peso(int $centavos): string
{
    $sign = $centavos < 0 ? '-' : '';
    $abs = abs($centavos);
    $whole = intdiv($abs, 100);
    $cents = $abs % 100;
    if ($cents === 0) {
        return $sign . '₱' . format_thousands($whole);
    }
    return $sign . '₱' . format_thousands($whole) . '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
}

/** Bare number for printed sack-label prices: "180" or "180.50". */
function peso_number(int $centavos): string
{
    $abs = abs($centavos);
    $whole = format_thousands(intdiv($abs, 100));
    $cents = $abs % 100;
    return $cents === 0 ? $whole : $whole . '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
}

/**
 * Parse a peso string ("180", "180.5", "1,250.00") into integer centavos
 * without ever going through a float. Returns null when invalid.
 */
function parse_peso_input(string $input): ?int
{
    $clean = preg_replace('/[₱,\s]/u', '', trim($input));
    if ($clean === null || !preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $clean, $m)) {
        return null;
    }
    $whole = (int) $m[1];
    $frac = isset($m[2]) ? (int) str_pad($m[2], 2, '0', STR_PAD_RIGHT) : 0;
    return $whole * 100 + $frac;
}

function delivery_fee_for(int $subtotalCentavos): int
{
    return $subtotalCentavos >= FREE_DELIVERY_MIN ? 0 : DELIVERY_FEE;
}

// ── Time (Asia/Manila, UTC+8) ─────────────────────────────────────────────────
function tz_utc(): DateTimeZone
{
    static $tz = null;
    return $tz ??= new DateTimeZone('UTC');
}

function tz_manila(): DateTimeZone
{
    static $tz = null;
    return $tz ??= new DateTimeZone('Asia/Manila');
}

function now_utc(): DateTimeImmutable
{
    return new DateTimeImmutable('now', tz_utc());
}

/** Parse a DB DATETIME(3)/DATE string (stored in UTC) or pass through DateTimeImmutable. */
function parse_utc(DateTimeInterface|string $d): DateTimeImmutable
{
    if ($d instanceof DateTimeImmutable) {
        return $d->setTimezone(tz_utc());
    }
    if ($d instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($d)->setTimezone(tz_utc());
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return new DateTimeImmutable($d . ' 00:00:00', tz_utc());
    }
    return new DateTimeImmutable($d, tz_utc());
}

function dt_sql(DateTimeInterface $d): string
{
    return parse_utc($d)->format('Y-m-d H:i:s.v');
}

/** Start of the Manila calendar day containing $at, as a UTC DateTimeImmutable. */
function start_of_shop_day(?DateTimeInterface $at = null): DateTimeImmutable
{
    $manila = parse_utc($at ?? now_utc())->setTimezone(tz_manila());
    return $manila->setTime(0, 0, 0)->setTimezone(tz_utc());
}

function days_ago(int $n, ?DateTimeInterface $at = null): DateTimeImmutable
{
    return start_of_shop_day($at)->modify("-{$n} days");
}

function fmt_date(DateTimeInterface|string $d): string
{
    return parse_utc($d)->setTimezone(tz_manila())->format('M j');
}

function fmt_datetime(DateTimeInterface|string $d): string
{
    return parse_utc($d)->setTimezone(tz_manila())->format('M j, g:i A');
}

function fmt_time(DateTimeInterface|string $d): string
{
    return parse_utc($d)->setTimezone(tz_manila())->format('g:i A');
}

function fmt_weekday(DateTimeInterface|string $d): string
{
    return parse_utc($d)->setTimezone(tz_manila())->format('D');
}

function ago(DateTimeInterface|string $d, ?DateTimeInterface $now = null): string
{
    $nowDt = parse_utc($now ?? now_utc());
    $sec = max(0, $nowDt->getTimestamp() - parse_utc($d)->getTimestamp());
    if ($sec < 60) {
        return 'just now';
    }
    $min = (int) round($sec / 60);
    if ($min < 60) {
        return "{$min} min ago";
    }
    $hrs = (int) round($min / 60);
    if ($hrs < 24) {
        return "{$hrs} h ago";
    }
    return ((int) round($hrs / 24)) . ' d ago';
}

function shop_is_open(?DateTimeInterface $at = null): bool
{
    $m = parse_utc($at ?? now_utc())->setTimezone(tz_manila());
    $dow = (int) $m->format('w'); // 0 = Sunday
    $hr = (int) $m->format('G');
    return $dow !== 0 && $hr >= 6 && $hr < 18;
}

const ORDER_CUTOFF_HOUR = 21;

/** Manila calendar day containing $at, as a UTC-midnight DateTimeImmutable. */
function shop_date(?DateTimeInterface $at = null): DateTimeImmutable
{
    $m = parse_utc($at ?? now_utc())->setTimezone(tz_manila());
    return new DateTimeImmutable($m->format('Y-m-d') . ' 00:00:00', tz_utc());
}

/** Morning delivery date: next day if placed before 9 PM Manila, else day after; skips Sunday. */
function delivery_date_for(?DateTimeInterface $placedAt = null): DateTimeImmutable
{
    $at = parse_utc($placedAt ?? now_utc());
    $hr = (int) $at->setTimezone(tz_manila())->format('G');
    $day = shop_date($at)->modify($hr >= ORDER_CUTOFF_HOUR ? '+2 days' : '+1 day');
    if ((int) $day->format('w') === 0) {
        $day = $day->modify('+1 day');
    }
    return $day;
}

function day_key(DateTimeInterface|string $day): string
{
    if (is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        return $day;
    }
    return parse_utc($day)->format('Y-m-d');
}

function parse_day_key(string $key): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $key, tz_utc());
    return ($dt && $dt->format('Y-m-d') === $key) ? $dt : null;
}

function fmt_day(DateTimeInterface|string $day): string
{
    return parse_utc($day)->format('D, M j');
}

function fmt_long_day(DateTimeInterface|string $day): string
{
    return parse_utc($day)->format('l, F j');
}

function fmt_day_relative(DateTimeInterface|string $day, ?DateTimeInterface $now = null): string
{
    $k = day_key($day);
    $today = shop_date($now);
    if ($k === day_key($today)) {
        return 'Today';
    }
    if ($k === day_key($today->modify('+1 day'))) {
        return 'Tomorrow';
    }
    return fmt_day($day);
}

// ── Category & display helpers ────────────────────────────────────────────────
// Two flexo inks plus key black: categories alternate green, red and ink.
const CATEGORY_HUES = [
    'vegetables' => ['bg' => '#1F6B3A', 'ink' => '#144B28', 'soft' => '#DCEBDD'],
    'fruits' => ['bg' => '#C8202F', 'ink' => '#9C1622', 'soft' => '#F6DCD9'],
    'meat' => ['bg' => '#161512', 'ink' => '#161512', 'soft' => '#EBE6D6'],
    'eggs' => ['bg' => '#C8202F', 'ink' => '#9C1622', 'soft' => '#F6DCD9'],
    'rice' => ['bg' => '#1F6B3A', 'ink' => '#144B28', 'soft' => '#DCEBDD'],
];

const EXTRA_HUES = [
    ['bg' => '#1F6B3A', 'ink' => '#144B28', 'soft' => '#DCEBDD'],
    ['bg' => '#C8202F', 'ink' => '#9C1622', 'soft' => '#F6DCD9'],
    ['bg' => '#161512', 'ink' => '#161512', 'soft' => '#EBE6D6'],
];

/** @return array{bg: string, ink: string, soft: string} */
function category_hue(string $slug): array
{
    if (isset(CATEGORY_HUES[$slug])) {
        return CATEGORY_HUES[$slug];
    }
    $h = 0;
    $len = strlen($slug);
    for ($i = 0; $i < $len; $i++) {
        $h = ($h * 31 + ord($slug[$i])) & 0x7FFFFFFF;
    }
    return EXTRA_HUES[$h % count(EXTRA_HUES)];
}

/** Deterministic tag tilt angle in degrees as string (not used for money). */
function tilt_deg(string $id, int $range = 4): string
{
    $h = 0;
    $len = strlen($id);
    for ($i = 0; $i < $len; $i++) {
        $h = ($h * 33 + ord($id[$i])) & 0x7FFFFFFF;
    }
    $milli = ($h % 1000) * ($range * 2) - ($range * 1000);
    return sprintf('%.2f', $milli / 1000); // footgun-ok: CSS angle degrees, not money
}

/** @return array{local: string, english: ?string} */
function split_name(string $name): array
{
    if (preg_match('/^(.*?)\s*\((.+)\)\s*$/u', $name, $m)) {
        return ['local' => $m[1], 'english' => $m[2]];
    }
    return ['local' => $name, 'english' => null];
}

function unit_for(string $variantName): string
{
    $n = strtolower($variantName);
    if (preg_match('/1\.5\s*kg/', $n)) {
        return '1.5kg';
    }
    if (preg_match('/\b1\s*kg|\bkg/', $n)) {
        return 'kg';
    }
    if (preg_match('/(\d+)\s*g\b/', $n, $m)) {
        return $m[1] . 'g';
    }
    if (str_contains($n, 'doz')) {
        return 'doz';
    }
    if (str_contains($n, 'tray')) {
        return 'tray';
    }
    if (str_contains($n, 'bundle') || str_contains($n, 'tali')) {
        return 'bundle';
    }
    if (str_contains($n, 'piece') || str_contains($n, 'whole')) {
        return 'pc';
    }
    if (str_contains($n, 'pack') || str_contains($n, 'pc')) {
        return 'pack';
    }
    if (str_contains($n, 'tub')) {
        return 'tub';
    }
    return $variantName;
}

// ── Orders metadata ───────────────────────────────────────────────────
const STATUS_META = [
    'PENDING' => [
        'label' => 'To confirm',
        'done' => 'Order placed',
        'shopLabel' => 'Pending',
        'customer' => 'Order placed',
        'tone' => 'yellow',
        'next' => 'CONFIRMED',
        'nextLabel' => 'Confirm',
    ],
    'CONFIRMED' => [
        'label' => 'To send out',
        'done' => 'Confirmed',
        'shopLabel' => 'Confirmed',
        'customer' => 'Confirmed, being prepared',
        'tone' => 'teal',
        'next' => 'READY',
        'nextLabel' => 'Send out',
    ],
    'READY' => [
        'label' => 'Out for delivery',
        'done' => 'Sent out for delivery',
        'shopLabel' => 'Out for delivery',
        'customer' => 'Out for delivery to you',
        'tone' => 'violet',
        'next' => 'COMPLETED',
        'nextLabel' => 'Mark delivered',
    ],
    'COMPLETED' => [
        'label' => 'Delivered',
        'done' => 'Delivered',
        'shopLabel' => 'Delivered',
        'customer' => 'Delivered',
        'tone' => 'green',
        'next' => null,
        'nextLabel' => null,
    ],
    'CANCELLED' => [
        'label' => 'Cancelled',
        'done' => 'Cancelled',
        'shopLabel' => 'Cancelled',
        'customer' => 'Cancelled',
        'tone' => 'gray',
        'next' => null,
        'nextLabel' => null,
    ],
];

const STATUS_FLOW = ['PENDING', 'CONFIRMED', 'READY', 'COMPLETED'];
const OPEN_STATUSES = ['PENDING', 'CONFIRMED', 'READY'];
const OFFICE_ADVANCE = ['PENDING', 'CONFIRMED', 'READY'];

const STALE_GCASH_HOURS = 2;
const SLOT_CAPACITY = 12;
const LOW_STOCK_AT = 10;
const TO_SALE_AFTER_DAYS = 7;

const DELIVERY_SLOTS = ['6:00–8:00 AM', '8:00–10:00 AM', '10:00 AM–12:00 NN'];
const PAYMENT_LABEL = ['GCASH' => 'GCash', 'COD' => 'Cash on delivery'];

const BARANGAYS = [
    'Burol', 'Datu Esmael', 'Emmanuel Bergado', 'Fatima', 'Langkaan', 'Luzviminda', 'Paliparan',
    'Sabang', 'Salawag', 'Salitran', 'Sampaloc', 'San Agustin', 'San Jose', 'San Miguel',
    'Santa Cruz', 'Santa Fe', 'Victoria Reyes', 'Zone I', 'Zone II', 'Zone III', 'Zone IV',
];

const PHONE_RE = '/^(\+?63|0)9\d{2}[\s-]?\d{3}[\s-]?\d{4}$/';

const ADJUST_REASONS = [
    ['id' => 'Restock', 'sign' => 1, 'label' => 'Restock'],
    ['id' => 'Return', 'sign' => 1, 'label' => 'Customer return'],
    ['id' => 'Spoilage', 'sign' => -1, 'label' => 'Spoilage'],
    ['id' => 'Damaged', 'sign' => -1, 'label' => 'Bruised or damaged'],
    ['id' => 'Miscount', 'sign' => 0, 'label' => 'Miscount'],
];

function order_code(int $orderNumber): string
{
    return 'BB-' . str_pad((string) $orderNumber, 4, '0', STR_PAD_LEFT);
}

function parse_order_code(string $input): ?int
{
    if (!preg_match('/(\d{1,7})\s*$/', trim($input), $m)) {
        return null;
    }
    $n = (int) $m[1];
    return $n > 0 ? $n : null;
}

/** @return array{label: string, tone: string} */
function payment_status_of(string $payment, string $status): array
{
    if ($status === 'CANCELLED') {
        return ['label' => $payment === 'GCASH' ? 'GCash' : 'COD', 'tone' => 'gray'];
    }
    if ($payment === 'GCASH') {
        return $status === 'PENDING'
            ? ['label' => 'GCash · On hold', 'tone' => 'yellow']
            : ['label' => 'GCash · Paid', 'tone' => 'green'];
    }
    return $status === 'COMPLETED'
        ? ['label' => 'COD · Collected', 'tone' => 'green']
        : ['label' => 'COD · On delivery', 'tone' => 'teal'];
}

function slot_rank(string $slot): int
{
    $idx = array_search($slot, DELIVERY_SLOTS, true);
    return $idx === false ? count(DELIVERY_SLOTS) : $idx;
}

function short_slot(string $slot): string
{
    $s = str_replace(':00', '', $slot);
    $s = preg_replace('/ AM–(\d+) AM/u', '–$1 AM', $s);
    return str_replace('12 NN', '12 noon', (string) $s);
}

function first_name(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name));
    return ($parts && $parts[0] !== '') ? $parts[0] : 'Customer';
}

function stock_state(int $stock): string
{
    if ($stock <= 0) {
        return 'out';
    }
    if ($stock <= LOW_STOCK_AT) {
        return 'low';
    }
    return 'in';
}

function movement_ref(string $id): string
{
    return 'MV-' . strtoupper(substr($id, -6));
}

// ── Roles & Access Control ────────────────────────────────────────────────────
const STAFF_ROLES = ['ADMIN'];
const ROLE_LABEL = [
    'ADMIN' => 'Admin',
    'CUSTOMER' => 'Customer',
];

function as_role(mixed $v): string
{
    return $v === 'ADMIN' ? 'ADMIN' : 'CUSTOMER';
}

function is_staff(string $role): bool
{
    return $role !== 'CUSTOMER';
}

function home_for(string $role): string
{
    return match ($role) {
        'ADMIN' => '/admin',
        default => '/',
    };
}

const GATED_AREAS = [
    ['prefix' => '/admin', 'roles' => ['ADMIN']],
];

function is_gated(string $path): bool
{
    foreach (GATED_AREAS as $a) {
        if ($path === $a['prefix'] || str_starts_with($path, $a['prefix'] . '/')) {
            return true;
        }
    }
    return false;
}

function can_open(string $role, string $path): bool
{
    foreach (GATED_AREAS as $a) {
        if ($path === $a['prefix'] || str_starts_with($path, $a['prefix'] . '/')) {
            return in_array($role, $a['roles'], true);
        }
    }
    return true;
}
