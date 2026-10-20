<?php
declare(strict_types=1);

// CLI only: all orders and test credentials live in a disposable project copy.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function check(bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    echo 'PASS: ' . $description . PHP_EOL;
}

function copyTree(string $source, string $destination): void {
    mkdir($destination, 0700, true);
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot()) continue;
        $target = $destination . '/' . $entry->getFilename();
        if ($entry->isDir()) copyTree($entry->getPathname(), $target);
        else copy($entry->getPathname(), $target);
    }
}

function removeTree(string $directory): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        if ($entry->isDir()) rmdir($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($directory);
}

function configureTestAccount(string $directory, string $input): int {
    $process = proc_open([PHP_BINARY, $directory . '/deployment/set-user.php'], [0 => ['pipe', 'r'], 1 => ['file', $directory . '/account.log', 'a'], 2 => ['file', $directory . '/account.log', 'a']], $pipes);
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    return proc_close($process);
}

function httpRequest(string $base, string $endpoint, ?array $data, string &$cookie): array {
    $headers = ['Connection: close'];
    if ($cookie !== '') $headers[] = 'Cookie: ' . $cookie;
    if ($data !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $context = stream_context_create(['http' => [
        'method' => $data === null ? 'GET' : 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $data === null ? '' : http_build_query($data),
        'ignore_errors' => true,
        'timeout' => 5,
    ]]);
    $body = @file_get_contents($base . '/application/server/' . $endpoint, false, $context);
    if ($body === false) throw new RuntimeException('HTTP request failed: ' . $endpoint);
    $responseHeaders = $http_response_header;
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $matchCookie)) $cookie = $matchCookie[1];
    }
    return ['status' => (int) $match[1], 'body' => $body, 'headers' => implode("\n", $responseHeaders)];
}

$temporary = sys_get_temp_dir() . '/order-management-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
$server = null;
try {
    $root = dirname(__DIR__);
    foreach (['application', 'users', 'orders'] as $name) copyTree($root . '/' . $name, $temporary . '/' . $name);
    $storage = $temporary . '/orders/orders.json';
    if (is_file($storage)) unlink($storage);
    file_put_contents($temporary . '/users/users.json', json_encode([
        ['user' => 'test-sales', 'password' => password_hash('test-password-only', PASSWORD_BCRYPT)],
    ], JSON_THROW_ON_ERROR));
    mkdir($temporary . '/deployment', 0700);
    copy($root . '/deployment/set-user.php', $temporary . '/deployment/set-user.php');
    $before = file_get_contents($temporary . '/users/users.json');
    check(configureTestAccount($temporary, "\n") === 1, 'Account setup rejects empty username');
    check(configureTestAccount($temporary, "test-sales\ntest-password-only\ndifferent-password\n") === 1, 'Account setup rejects password mismatch');
    check(file_get_contents($temporary . '/users/users.json') === $before, 'Invalid account setup preserves existing account');
    check(configureTestAccount($temporary, "test-sales\ntest-password-only\ntest-password-only\n") === 0, 'User can configure username and password from terminal');
    $configured = json_decode(file_get_contents($temporary . '/users/users.json'), true, 512, JSON_THROW_ON_ERROR);
    check($configured[0]['user'] === 'test-sales' && password_verify('test-password-only', $configured[0]['password']), 'Configured password is stored as a valid bcrypt hash');
    check(!str_contains(file_get_contents($temporary . '/users/users.json'), 'test-password-only'), 'Plaintext password is not stored');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    if (!$socket) throw new RuntimeException($errorMessage);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $temporary], [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'], 2 => ['file', $temporary . '/server.log', 'a']], $pipes);
    if (!is_resource($server)) throw new RuntimeException('Could not start PHP server.');
    fclose($pipes[0]);
    $base = 'http://' . $address;
    $cookie = '';
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $number, $message, .1);
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('PHP server did not start.');
    $call = function (string $endpoint, ?array $data = null) use ($base, &$cookie): array {
        return httpRequest($base, $endpoint, $data, $cookie);
    };
    $session = $call('session.php');
    check($session['status'] === 200 && str_contains($session['headers'], 'application/json'), 'Session returns HTTP 200 and JSON');
    check(str_contains($session['headers'], 'HttpOnly') && str_contains($session['headers'], 'SameSite=Strict'), 'Session cookie flags');
    $token = json_decode($session['body'], true, 512, JSON_THROW_ON_ERROR)['token'];
    check($call('products.php')['status'] === 401, 'Unauthenticated catalogue blocked');
    check($call('view-orders.php')['status'] === 401, 'Unauthenticated order tables blocked');
    check($call('create-order.php')['status'] === 405, 'GET cannot create orders');
    check($call('session.php', [])['status'] === 405, 'POST cannot read session');
    check($call('authenticate.php', ['user' => 'test-sales', 'password' => 'test-password-only'])['status'] === 403, 'Missing CSRF token rejected');
    check($call('authenticate.php', ['token' => $token, 'user' => '', 'password' => ''])['status'] === 422, 'Missing login fields rejected');
    check($call('authenticate.php', ['token' => $token, 'user' => '   ', 'password' => 'test-password-only'])['status'] === 422, 'Whitespace-only username rejected');
    check($call('authenticate.php', ['token' => $token, 'user' => 'test-sales', 'password' => '   '])['status'] === 422, 'Whitespace-only password rejected');
    check($call('authenticate.php', ['token' => $token, 'user' => 'test-sales', 'password' => 'wrong'])['status'] === 401, 'Wrong password rejected');
    $login = $call('authenticate.php', ['token' => $token, 'user' => 'test-sales', 'password' => 'test-password-only']);
    check($login['status'] === 200, 'Correct password accepted');
    $token = json_decode($login['body'], true, 512, JSON_THROW_ON_ERROR)['token'];
    $products = json_decode($call('products.php')['body'], true, 512, JSON_THROW_ON_ERROR)['products'];
    check(count($products) === 4, 'Exactly four server-priced products');
    check($call('view-orders.php')['status'] === 404, 'Missing orders file returns 404');
    $order = ['token' => $token, 'id' => 'TEST-001', 'customerName' => '<script>alert(1)</script>', 'address' => 'Example Street, Barcelona', 'email' => 'test@example.com', 'phone' => '+34 123456789', 'selected' => ['notebook' => '1', 'bottle' => '1'], 'quantity' => ['notebook' => '1', 'bottle' => '2']];
    $missingStorage = $call('create-order.php', $order);
    check($missingStorage['status'] === 404 && str_contains($missingStorage['headers'], 'text/html'), 'Creation with missing file returns HTML 404');
    check(str_contains($missingStorage['body'], '#view') && str_contains($missingStorage['body'], '#operations') && str_contains($missingStorage['body'], 'href="../client/index.html"'), 'Missing file error includes all three return buttons');
    check(!file_exists($storage), 'Creation does not recreate missing orders file');
    file_put_contents($storage, "[]\n");
    foreach ([
        'Missing customer name' => ['customerName' => ''],
        'Invalid email' => ['email' => 'invalid'],
        'No products' => ['selected' => []],
        'Unknown product' => ['selected' => ['unknown' => '1']],
        'Negative quantity' => ['quantity' => ['notebook' => '-1', 'bottle' => '2']],
        'Fractional quantity' => ['quantity' => ['notebook' => '1.5', 'bottle' => '2']],
        'Excess quantity' => ['quantity' => ['notebook' => '10001', 'bottle' => '2']],
    ] as $label => $changes) check($call('create-order.php', array_replace($order, $changes))['status'] === 422, $label . ' rejected');
    check($call('create-order.php', array_replace($order, ['token' => 'wrong']))['status'] === 403, 'Wrong CSRF token rejected');
    $saved = $call('create-order.php', $order);
    check($saved['status'] === 200 && $saved['body'] === '€72.60' && str_contains($saved['headers'], 'text/plain'), 'Creation returns only total including 21% VAT');
    $records = json_decode(file_get_contents($storage), true, 512, JSON_THROW_ON_ERROR);
    check($records[0]['subtotalCents'] === 6000 && $records[0]['vatCents'] === 1260 && $records[0]['totalCents'] === 7260, 'Correct persisted subtotal, VAT and total');
    check($records[0]['createdBy'] === 'test-sales' && $records[0]['products']['bottle']['quantity'] === 2, 'Author, customer and product details persisted');
    check($call('create-order.php', $order)['status'] === 409, 'Duplicate ID rejected');
    check(count(json_decode(file_get_contents($storage), true)) === 1, 'Duplicate does not change storage');
    foreach (['view-orders.php', 'view-orders.php?id=TEST-001'] as $endpoint) {
        $table = $call($endpoint);
        check($table['status'] === 200 && str_contains($table['body'], '€72.60') && str_contains($table['body'], 'Created at (UTC)'), $endpoint . ' returns complete table');
        check(!str_contains($table['body'], '<script>') && str_contains($table['body'], '&lt;script&gt;'), $endpoint . ' escapes customer input');
    }
    $missing = $call('view-orders.php?id=UNKNOWN');
    check($missing['status'] === 404 && str_contains($missing['body'], '#view') && str_contains($missing['body'], '#operations'), 'Missing order returns 404 with navigation');
    check($call('view-orders.php?id=')['status'] === 422, 'Empty order lookup rejected');
    foreach (['broken JSON', '[{}]'] as $broken) {
        file_put_contents($storage, $broken);
        check($call('view-orders.php')['status'] === 404, 'Invalid storage returns 404');
        check($call('create-order.php', array_replace($order, ['id' => 'TEST-002']))['status'] === 404, 'Invalid storage blocks new write');
        check(file_get_contents($storage) === $broken, 'Invalid storage preserved for recovery');
    }
    unlink($storage);
    unlink($temporary . '/orders/.htaccess');
    rmdir($temporary . '/orders');
    check($call('create-order.php', $order)['status'] === 404, 'Unavailable order directory returns HTML 404');
    check($call('logout.php', ['token' => $token])['status'] === 200, 'Logout succeeds');
    check($call('view-orders.php')['status'] === 401, 'Logout revokes order access');
    check($call('create-order.php', $order)['status'] === 401, 'Logout revokes writes');
    echo "All isolated integration checks passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    removeTree($temporary);
}
exit($exitCode ?? 0);
