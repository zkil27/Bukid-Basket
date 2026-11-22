<?php
declare(strict_types=1);

function e(mixed $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url, int $code = 302): never
{
    header("Location: {$url}", true, $code);
    exit;
}

function flash_set(string $type, string $message): void
{
    ensure_session();
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

/** @return ?array{type: string, message: string} */
function flash_pop(): ?array
{
    ensure_session();
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($f) ? $f : null;
}

function json_ok(mixed $data = null, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $error, int $status = 400, mixed $extra = null): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $body = ['ok' => false, 'error' => $error];
    if ($extra !== null) {
        $body['data'] = $extra;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function input_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function render(string $layout, string $view, array $data = []): void
{
    $viewsRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
    $viewFile = $viewsRoot . $view . '.php';
    $layoutFile = $viewsRoot . 'layouts' . DIRECTORY_SEPARATOR . $layout . '.php';

    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = ob_get_clean();

    require $layoutFile;
}

/** Render status/payment tone badge HTML. */
function tone_chip(string $label, string $tone): string
{
    return '<span class="chip chip-' . e($tone) . '">' . e($label) . '</span>';
}
