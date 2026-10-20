"""End-to-end PHP checks against an isolated copy; no third-party dependencies."""
import http.cookiejar
import json
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


def main():
    php = shutil.which('php')
    if not php:
        raise SystemExit('Cannot run integration checks: PHP 8.1+ is required.')
    root = Path(__file__).resolve().parents[1]
    with tempfile.TemporaryDirectory(prefix='order-management-test-') as directory:
        project = Path(directory)
        for name in ('aplicacio', 'usuaris', 'comandes'):
            shutil.copytree(root / name, project / name)
        orders = project / 'comandes/orders.json'
        orders.unlink(missing_ok=True)
        hashed = subprocess.check_output([php, '-r', 'echo password_hash("test-password", PASSWORD_DEFAULT);'], text=True)
        (project / 'usuaris/users.json').write_text(json.dumps([{'user': 'admin', 'password': hashed}]))
        for script in (project / 'aplicacio/servidor').glob('*.php'):
            subprocess.run([php, '-l', str(script)], check=True, capture_output=True)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        with tempfile.TemporaryFile() as log:
            server = subprocess.Popen([php, '-S', f'127.0.0.1:{port}', '-t', str(project)], stdout=log, stderr=log)
            try:
                opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

                def call(endpoint, data=None):
                    body = None if data is None else urllib.parse.urlencode(data).encode()
                    request = urllib.request.Request(f'http://127.0.0.1:{port}/aplicacio/servidor/{endpoint}', data=body)
                    try:
                        response = opener.open(request, timeout=5)
                    except urllib.error.HTTPError as error:
                        response = error
                    return response.status, response.read().decode(), response.headers

                for attempt in range(50):
                    try:
                        status, body, _ = call('session.php')
                        break
                    except urllib.error.URLError:
                        time.sleep(.1)
                else:
                    raise AssertionError('PHP development server did not start')
                assert status == 200
                token = json.loads(body)['token']
                assert call('products.php')[0] == 401
                assert call('create-order.php')[0] == 405
                assert call('authenticate.php', {'user': 'admin', 'password': 'test-password'})[0] == 403
                assert call('authenticate.php', {'user': 'admin', 'password': 'wrong', 'token': token})[0] == 401
                status, body, _ = call('authenticate.php', {'user': 'admin', 'password': 'test-password', 'token': token})
                assert status == 200
                token = json.loads(body)['token']
                status, body, _ = call('products.php')
                assert status == 200 and len(json.loads(body)['products']) == 4
                assert call('view-orders.php')[0] == 404
                order = {'token': token, 'id': 'TEST-1', 'customerName': '<script>alert(1)</script>', 'address': '1 Test Street, Europe', 'email': 'test@example.com', 'phone': '+34 123456789', 'selected[notebook]': '1', 'quantity[notebook]': '1', 'selected[bottle]': '1', 'quantity[bottle]': '2'}
                assert call('create-order.php', {**order, 'token': 'wrong'})[0] == 403
                assert call('create-order.php', {**order, 'quantity[bottle]': '-1'})[0] == 422
                assert call('create-order.php', {**order, 'quantity[bottle]': '1.5'})[0] == 422
                assert call('create-order.php', {**order, 'email': 'invalid'})[0] == 422
                status, body, headers = call('create-order.php', order)
                assert status == 200 and body == '€72.60', (status, body)
                assert 'text/plain' in headers['Content-Type']
                saved = json.loads(orders.read_text())
                assert saved[0]['totalCents'] == 7260 and saved[0]['vatCents'] == 1260
                assert saved[0]['products']['bottle']['quantity'] == 2
                assert call('create-order.php', order)[0] == 409
                assert len(json.loads(orders.read_text())) == 1
                for endpoint in ('view-orders.php', 'view-orders.php?id=TEST-1'):
                    status, body, _ = call(endpoint)
                    assert status == 200 and '€72.60' in body
                    assert '<script>' not in body and '&lt;script&gt;' in body
                    assert 'Created at (UTC)' in body
                assert call('view-orders.php?id=missing')[0] == 404
                orders.write_text('broken JSON')
                assert call('view-orders.php')[0] == 404
                assert call('create-order.php', {**order, 'id': 'TEST-2'})[0] == 404
                assert orders.read_text() == 'broken JSON'
                orders.write_text('[{}]')
                assert call('view-orders.php')[0] == 404
                assert call('create-order.php', {**order, 'id': 'TEST-2'})[0] == 404
                assert orders.read_text() == '[{}]'
                assert call('logout.php', {'token': token})[0] == 200
                assert call('view-orders.php')[0] == 401
                assert call('create-order.php', order)[0] == 401
                print('PASS: PHP syntax and isolated HTTP integration checks.')
            finally:
                server.terminate()
                server.wait(timeout=5)


if __name__ == '__main__':
    main()
