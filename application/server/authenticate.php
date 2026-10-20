<?php
require __DIR__ . '/common.php';
requireMethod('POST');
verifyToken();
$user = $_POST['user'] ?? '';
$password = $_POST['password'] ?? '';
if (!is_string($user) || !is_string($password) || trim($user) === '' || trim($password) === '') jsonResponse(['error' => 'Enter your username and password.'], 422);
try {
    $contents = @file_get_contents(__DIR__ . '/../../users/users.json');
    if ($contents === false) throw new RuntimeException();
    $users = decodeUsers($contents);
} catch (Throwable $error) {
    jsonResponse(['error' => 'The user file is unavailable. Contact the administrator.'], 503);
}
$account = $users[userKey(trim($user))] ?? null;
if ($account !== null && password_verify($password, $account['authentication']['passwordHash'])) {
    session_regenerate_id(true);
    $_SESSION['user'] = $account['identity']['username'];
    $_SESSION['token'] = bin2hex(random_bytes(32));
    jsonResponse(['user' => $account['identity']['username'], 'token' => $_SESSION['token']]);
}
unset($_SESSION['user']);
jsonResponse(['error' => 'Authentication failed. Please check your username and password.'], 401);
