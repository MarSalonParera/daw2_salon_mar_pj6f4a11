<?php
require __DIR__ . '/common.php';
requireMethod('GET');
if (!isset($_SESSION['token'])) $_SESSION['token'] = bin2hex(random_bytes(32));
jsonResponse(['user' => $_SESSION['user'] ?? null, 'token' => $_SESSION['token']]);
