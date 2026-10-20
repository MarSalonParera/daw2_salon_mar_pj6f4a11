<?php
require __DIR__ . '/common.php';
requireMethod('POST');
verifyToken();
$_SESSION = [];
session_destroy();
jsonResponse(['message' => 'You have signed out.']);
