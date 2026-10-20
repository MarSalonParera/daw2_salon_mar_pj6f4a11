<?php
declare(strict_types=1);

// Domain collections use string keys, even for numeric-looking identifiers.
function orderKey(string $id): string {
    return 'order:' . $id;
}

function userKey(string $username): string {
    return 'user:' . $username;
}

// Adapt the existing JSON format at the storage boundary without migrating files.
function orderFromRecord(array $record): array {
    foreach (['id', 'customerName', 'address', 'email', 'phone', 'createdBy', 'createdAt'] as $field) {
        if (!isset($record[$field]) || !is_string($record[$field])) throw new RuntimeException('Invalid order field.');
    }
    foreach (['subtotalCents', 'vatCents', 'totalCents'] as $field) {
        if (!isset($record[$field]) || !is_int($record[$field]) || $record[$field] < 0) throw new RuntimeException('Invalid order price.');
    }
    if (empty($record['products']) || !is_array($record['products']) || array_is_list($record['products'])) throw new RuntimeException('Invalid product collection.');
    foreach ($record['products'] as $product) {
        if (!is_array($product) || !isset($product['name'], $product['quantity'], $product['priceCents']) || !is_string($product['name']) || !is_int($product['quantity']) || $product['quantity'] < 1 || !is_int($product['priceCents']) || $product['priceCents'] < 0) throw new RuntimeException('Invalid product.');
    }
    return [
        'identity' => ['id' => $record['id']],
        'customer' => [
            'name' => $record['customerName'],
            'address' => $record['address'],
            'email' => $record['email'],
            'phone' => $record['phone'],
        ],
        'products' => $record['products'],
        'pricing' => [
            'subtotalCents' => $record['subtotalCents'],
            'vatCents' => $record['vatCents'],
            'totalCents' => $record['totalCents'],
        ],
        'metadata' => ['createdBy' => $record['createdBy'], 'createdAt' => $record['createdAt']],
    ];
}

function orderToRecord(array $order): array {
    return [
        'id' => $order['identity']['id'],
        'customerName' => $order['customer']['name'],
        'address' => $order['customer']['address'],
        'email' => $order['customer']['email'],
        'phone' => $order['customer']['phone'],
        'products' => $order['products'],
        'subtotalCents' => $order['pricing']['subtotalCents'],
        'vatCents' => $order['pricing']['vatCents'],
        'totalCents' => $order['pricing']['totalCents'],
        'createdBy' => $order['metadata']['createdBy'],
        'createdAt' => $order['metadata']['createdAt'],
    ];
}

function decodeOrders(string $contents): array {
    $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($records) || !array_is_list($records)) throw new RuntimeException('Invalid order list.');
    $orders = [];
    foreach ($records as $record) {
        if (!is_array($record)) throw new RuntimeException('Invalid order record.');
        $order = orderFromRecord($record);
        $key = orderKey($order['identity']['id']);
        if (isset($orders[$key])) throw new RuntimeException('Duplicate stored order ID.');
        $orders[$key] = $order;
    }
    return $orders;
}

function encodeOrders(array $orders): string {
    // A JSON list is retained only for the existing on-disk format.
    return json_encode(array_values(array_map('orderToRecord', $orders)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function decodeUsers(string $contents): array {
    $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($records) || !array_is_list($records)) throw new RuntimeException('Invalid user list.');
    $users = [];
    foreach ($records as $record) {
        if (!is_array($record) || !isset($record['user'], $record['password']) || !is_string($record['user']) || !is_string($record['password'])) throw new RuntimeException('Invalid user record.');
        $key = userKey($record['user']);
        if (isset($users[$key])) throw new RuntimeException('Duplicate stored username.');
        $users[$key] = [
            'identity' => ['username' => $record['user']],
            'authentication' => ['passwordHash' => $record['password']],
        ];
    }
    return $users;
}
