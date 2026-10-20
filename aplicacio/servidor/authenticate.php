<?php
require __DIR__ . '/common.php';
requireMethod('POST');
verifyToken();
$user = $_POST['user'] ?? '';
$password = $_POST['password'] ?? '';
if (!is_string($user) || !is_string($password) || trim($user) === '' || $password === '') jsonResponse(['error' => 'Enter your username and password.'], 422);
try {
    $contents = @file_get_contents(__DIR__ . '/../../usuaris/users.json');
    if ($contents === false) throw new RuntimeException();
    $users = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($users)) throw new RuntimeException();
} catch (Throwable $error) {
    jsonResponse(['error' => 'The user file is unavailable. Contact the administrator.'], 503);
}
foreach ($users as $account) {
    if ($account['user'] === trim($user) && password_verify($password, $account['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = $account['user'];
        $_SESSION['token'] = bin2hex(random_bytes(32));
        jsonResponse(['user' => $account['user'], 'token' => $_SESSION['token']]);
    }
}
unset($_SESSION['user']);
jsonResponse(['error' => 'Authentication failed. Please check your username and password.'], 401);
