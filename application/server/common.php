<?php
declare(strict_types=1);

require_once __DIR__ . '/data.php';

const PRODUCTS = [
    'notebook' => ['name' => 'Everyday notebook', 'priceCents' => 1200],
    'bottle' => ['name' => 'Steel water bottle', 'priceCents' => 2400],
    'bag' => ['name' => 'Canvas tote bag', 'priceCents' => 1800],
    'lamp' => ['name' => 'Desk lamp', 'priceCents' => 4500],
];
const ORDERS_FILE = __DIR__ . '/../../orders/orders.json';

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Strict', 'path' => '/']);
session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}

function requireMethod(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        jsonResponse(['error' => 'This request method is not allowed.'], 405);
    }
}

function requireUser(bool $html = false): void {
    if (!isset($_SESSION['user'])) {
        if ($html) renderPage('Sign in required', '<p>Please sign in to view orders.</p>', 401);
        jsonResponse(['error' => 'Please sign in to continue.'], 401);
    }
}

function verifyToken(): void {
    $token = $_POST['token'] ?? '';
    if (!is_string($token) || !isset($_SESSION['token']) || !hash_equals($_SESSION['token'], $token)) {
        jsonResponse(['error' => 'Your session has expired. Reload the page and try again.'], 403);
    }
}

function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int $cents): string {
    return '€' . number_format($cents / 100, 2, '.', ',');
}

function renderPage(string $title, string $body, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . escape($title) . ' · CALLA I MENJA</title><link rel="stylesheet" href="../client/styles.css"></head><body><main class="report"><a class="brand" href="../client/index.html"><img class="brand-logo" src="../../img/logo%20res.avif" alt="CALLA I MENJA logo"> CALLA I MENJA</a><section class="panel"><p class="eyebrow">ORDER RECORDS</p><h1>' . escape($title) . '</h1>' . $body . '<nav class="actions"><a class="button" href="../client/index.html#view">View orders</a><a class="button secondary" href="../client/index.html#operations">Operations menu</a><a class="button secondary" href="../client/index.html">Home</a></nav></section></main></body></html>';
    exit;
}

function readOrders(): array {
    $file = @fopen(ORDERS_FILE, 'rb');
    if ($file === false) renderPage('Orders file unavailable', '<p>The orders file could not be found or opened. Ask the administrator to check the file and its permissions.</p>', 404);
    if (!flock($file, LOCK_SH)) {
        fclose($file);
        renderPage('Orders file unavailable', '<p>The orders file could not be opened for reading.</p>', 404);
    }
    $contents = stream_get_contents($file);
    flock($file, LOCK_UN);
    fclose($file);
    try {
        if ($contents === false) throw new RuntimeException();
        return decodeOrders($contents);
    } catch (Throwable $error) {
        renderPage('Orders file unavailable', '<p>The orders file does not contain valid order data. Please contact the administrator.</p>', 404);
    }
}

function orderTable(array $orders): string {
    if (!$orders) return '<p>No orders have been saved yet.</p>';
    $html = '<div class="table-scroll"><table><thead><tr><th>Order ID</th><th>Customer name</th><th>Address</th><th>Email</th><th>Phone</th><th>Products · quantity · unit price</th><th>Subtotal</th><th>VAT (21%)</th><th>Total including VAT</th><th>Created by</th><th>Created at (UTC)</th></tr></thead><tbody>';
    foreach ($orders as $order) {
        $html .= '<tr>';
        $html .= '<td>' . escape($order['identity']['id']) . '</td>';
        foreach ($order['customer'] as $value) $html .= '<td>' . escape($value) . '</td>';
        $html .= '<td><ul class="product-list">';
        foreach ($order['products'] as $product) $html .= '<li>' . escape($product['name']) . ' × ' . escape((string) $product['quantity']) . ' · ' . money($product['priceCents']) . '</li>';
        $html .= '</ul></td><td>' . money($order['pricing']['subtotalCents']) . '</td><td>' . money($order['pricing']['vatCents']) . '</td><td><strong>' . money($order['pricing']['totalCents']) . '</strong></td><td>' . escape($order['metadata']['createdBy']) . '</td><td>' . escape($order['metadata']['createdAt']) . '</td></tr>';
    }
    return $html . '</tbody></table></div>';
}
