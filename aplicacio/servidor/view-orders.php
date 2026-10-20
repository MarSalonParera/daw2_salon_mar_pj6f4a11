<?php
require __DIR__ . '/common.php';
requireMethod('GET');
requireUser(true);
$orders = readOrders();
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    if (!is_string($id) || trim($id) === '') renderPage('Order ID required', '<p>Enter an order ID to find a specific order.</p>', 422);
    foreach ($orders as $order) {
        if ($order['id'] === trim($id)) renderPage('Order ' . $order['id'], orderTable([$order]));
    }
    renderPage('Order not found', '<p>No order was found with ID <strong>' . escape(trim($id)) . '</strong>.</p>', 404);
}
renderPage('All orders', orderTable($orders));
