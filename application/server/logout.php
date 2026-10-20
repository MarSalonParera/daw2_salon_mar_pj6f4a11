<?php
require __DIR__ . '/common.php';
requireMethod('POST');
verifyToken();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $parameters = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $parameters['path'], 'secure' => $parameters['secure'], 'httponly' => true, 'samesite' => 'Strict']);
}
session_destroy();
jsonResponse(['message' => 'You have signed out.']);
