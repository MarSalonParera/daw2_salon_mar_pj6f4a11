<?php
require __DIR__ . '/common.php';
requireMethod('POST');
requireUser();
verifyToken();

function orderInput(string $key, int $limit): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || trim($value) === '' || strlen($value) > $limit) jsonResponse(['error' => 'Please provide a valid ' . $key . '.'], 422);
    return trim($value);
}

function buildOrder(): array {
    $order = [
        'identity' => ['id' => orderInput('id', 80)],
        'customer' => [
            'name' => orderInput('customerName', 200),
            'address' => orderInput('address', 500),
            'email' => orderInput('email', 254),
            'phone' => orderInput('phone', 50),
        ],
        'products' => [],
        'pricing' => ['subtotalCents' => 0, 'vatCents' => 0, 'totalCents' => 0],
        'metadata' => ['createdBy' => $_SESSION['user'], 'createdAt' => gmdate('Y-m-d H:i:s')],
    ];
    if (!filter_var($order['customer']['email'], FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Enter a valid email address.'], 422);
    $selected = $_POST['selected'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    if (!is_array($selected) || !$selected || !is_array($quantities)) jsonResponse(['error' => 'Select at least one product and enter its quantity.'], 422);
    foreach ($selected as $id => $enabled) {
        if (!isset(PRODUCTS[$id])) jsonResponse(['error' => 'An unknown product was selected.'], 422);
        $quantity = filter_var($quantities[$id] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        if ($quantity === false) jsonResponse(['error' => 'Selected product quantities must be whole numbers from 1 to 10,000.'], 422);
        $product = PRODUCTS[$id];
        $order['products'][$id] = $product + ['quantity' => $quantity];
        $order['pricing']['subtotalCents'] += $product['priceCents'] * $quantity;
    }
    $order['pricing']['vatCents'] = intdiv($order['pricing']['subtotalCents'] * 21 + 50, 100);
    $order['pricing']['totalCents'] = $order['pricing']['subtotalCents'] + $order['pricing']['vatCents'];
    return $order;
}

function saveOrder(array $order): void {
    // Require the existing file: a missing file must produce HTTP 404.
    $file = @fopen(ORDERS_FILE, 'r+b');
    if ($file === false) renderPage('Orders file unavailable', '<p>The orders file could not be found or opened. Ask the administrator to check the file and its permissions.</p>', 404);
    if (!flock($file, LOCK_EX)) {
        fclose($file);
        renderPage('Orders file unavailable', '<p>The orders file could not be opened for writing.</p>', 404);
    }
    try {
        $contents = stream_get_contents($file);
        if ($contents === false) throw new RuntimeException();
        $orders = decodeOrders($contents);
        $key = orderKey($order['identity']['id']);
        if (isset($orders[$key])) {
            flock($file, LOCK_UN);
            fclose($file);
            jsonResponse(['error' => 'This order ID already exists. Choose a unique ID.'], 409);
        }
        $orders[$key] = $order;
        $json = encodeOrders($orders);
        rewind($file);
        $written = fwrite($file, $json);
        if ($written !== strlen($json)) throw new RuntimeException();
        if (!ftruncate($file, strlen($json)) || !fflush($file)) throw new RuntimeException();
    } catch (Throwable $error) {
        flock($file, LOCK_UN);
        fclose($file);
        renderPage('Orders file unavailable', '<p>The orders file could not be read or saved. Please contact the administrator before trying again.</p>', 404);
    }
    flock($file, LOCK_UN);
    fclose($file);
}

$order = buildOrder();
saveOrder($order);
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo money($order['pricing']['totalCents']);
