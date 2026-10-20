<?php
declare(strict_types=1);

// Account configuration is available only in the terminal, never over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function readEntry(string $prompt, bool $secret = false): string {
    fwrite(STDOUT, $prompt);
    $terminalMode = null;
    if ($secret && stream_isatty(STDIN)) {
        if (!function_exists('shell_exec')) throw new RuntimeException('Hidden password input requires shell_exec in CLI PHP.');
        $terminalMode = trim((string) shell_exec('stty -g'));
        if ($terminalMode === '') throw new RuntimeException('Could not hide terminal password input.');
        shell_exec('stty -echo');
    }
    try {
        $value = fgets(STDIN);
        if ($value === false) throw new RuntimeException('Input cancelled; account was not changed.');
        return rtrim($value, "\r\n");
    } finally {
        if ($terminalMode !== null) {
            shell_exec('stty ' . escapeshellarg($terminalMode));
            fwrite(STDOUT, PHP_EOL);
        }
    }
}

try {
    fwrite(STDOUT, "Configure the application account. This replaces the existing account.\n");
    $user = trim(readEntry('Username: '));
    if ($user === '' || strlen($user) > 100) throw new RuntimeException('Username must contain 1 to 100 bytes.');
    $password = readEntry('Password (8 to 72 bytes): ', true);
    if (trim($password) === '' || strlen($password) < 8 || strlen($password) > 72) throw new RuntimeException('Password must contain 8 to 72 bytes and cannot be blank.');
    $confirmation = readEntry('Confirm password: ', true);
    if ($password !== $confirmation) throw new RuntimeException('Passwords do not match; account was not changed.');
    $accounts = [['user' => $user, 'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]];
    $json = json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $destination = __DIR__ . '/../users/users.json';
    $temporary = tempnam(dirname($destination), '.account-');
    if ($temporary === false) throw new RuntimeException('Cannot create the account file. Check directory permissions.');
    try {
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json) || !chmod($temporary, 0644) || !rename($temporary, $destination)) throw new RuntimeException('Cannot save the account. Check file permissions.');
    } finally {
        if (is_file($temporary)) unlink($temporary);
    }
    unset($password, $confirmation);
    fwrite(STDOUT, "Account saved in users/users.json. Only the bcrypt hash is stored.\nSign out of existing sessions before using the new account.\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
