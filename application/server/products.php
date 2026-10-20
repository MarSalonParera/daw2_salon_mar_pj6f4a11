<?php
require __DIR__ . '/common.php';
requireMethod('GET');
requireUser();
$products = [];
foreach (PRODUCTS as $id => $product) $products[$id] = ['id' => $id, 'name' => $product['name'], 'price' => money($product['priceCents'])];
jsonResponse(['products' => $products]);
