# Order Management

A PHP sales-order application with an English interface, server-side authentication, a fixed four-product catalogue, 21% VAT, and JSON persistence. No client storage or client-side order calculations are used. JavaScript handles form validation, navigation, requests, and display only.

## Requirements and launch

- PHP 8.1 or newer, with sessions and JSON enabled.
- Apache 2.4 with PHP configured, `mod_ssl`, and `mod_headers`.
- Chrome or Firefox with JavaScript enabled.

The existing checkout is the application root (the assignment calls this directory `orderManagementApp`; it can be deployed under that name without changing code). Open its `index.html` through Apache, rather than as a local file.

For development only, run `php -S 127.0.0.1:8080 -t .` from the project root and visit `http://127.0.0.1:8080`. The PHP development server does **not** process `.htaccess`; use it only on a trusted local machine, since the JSON files are not protected by Apache there. Production must use Apache and HTTPS.

Adapt `desplegament/apache-ssl.conf.example` to the actual hostname, root path, and issued TLS certificates, then enable it in Apache. It redirects HTTP to HTTPS. HTTPS is only active once the real server and certificates have been configured; the sample configuration does not provision them. HTTPS requests use secure, HttpOnly, SameSite session cookies.

Allow Apache's PHP process to create and update files in `comandes/` and read `usuaris/users.json`. Keep the users file read-only to the application. Do not use world-writable permissions. Enable `AllowOverride All` for the root so the `.htaccess` protections take effect. Direct requests to `usuaris/users.json` and `comandes/orders.json` must return 403 on Apache.

The only predefined user is `admin`, with the exact bcrypt hash supplied by the assignment. **The plaintext password was not supplied and has not been invented or changed.** Use the password provided by the instructor. There is no registration or user-editing feature.

Folder names are in Catalan as requested; filenames and the user interface remain in English.

## Layout

```text
index.html                      Application entry
aplicacio/client/index.html   English screens and forms
aplicacio/client/styles.css   Responsive presentation
aplicacio/client/app.js       Interface events and HTTP requests
aplicacio/servidor/common.php   Session, shared functions, product catalogue
aplicacio/servidor/session.php  GET session and CSRF token
aplicacio/servidor/authenticate.php  POST authentication
aplicacio/servidor/logout.php   POST sign out
aplicacio/servidor/products.php GET server-formatted catalogue
aplicacio/servidor/create-order.php POST validation, calculation, persistence
aplicacio/servidor/view-orders.php  GET all orders, or ?id=ORDER-ID
usuaris/users.json                Unmodified predefined account
comandes/orders.json              Created automatically on first saved order
desplegament/                     Apache HTTPS example
```

## Using the application

1. Open the home screen, read the guide, and sign in.
2. Select an operation in the menu and press Continue.
3. To create an order, enter a unique order ID, full customer name, address, email, and phone. Check at least one product, enter a quantity from 1 to 10,000, and save. The server returns only the formatted total including VAT on success, with HTTP 200.
4. To review orders, select all orders or one order by its exact, case-sensitive ID. The server returns HTML tables and HTTP 200. Missing files and missing IDs return an HTML error page and HTTP 404, with links to the order menu, operations menu, and home.

Authentication failure displays an error and returns to the home screen. Protected server operations independently verify the session. POST endpoints also validate a session CSRF token. Duplicate IDs return 409; invalid fields return 422; unauthenticated requests return 401; incorrect HTTP methods return 405.

The catalogue is defined once in PHP: notebook €12, bottle €24, tote bag €18, and desk lamp €45, before VAT. Prices and totals are stored as integer cents. VAT is rounded once per order using integer arithmetic. A notebook and two bottles cost €60 before VAT, €12.60 VAT, and €72.60 in total.

Every saved record contains customer data, selected products and their quantities and unit prices, subtotal, VAT, total, the authenticated author, and a UTC creation timestamp. A lock covers the complete read/duplicate-check/write transaction to prevent concurrent requests overwriting each other. Invalid JSON is reported rather than silently discarded. A single local JSON file is suited to this assignment; it has no database transactions or automated backups.

The attachment contains two inconsistencies: it sometimes says `orders.js`, although the specified file is `orders.json`; and it requires both first-order creation and an error when the file is missing. This implementation uses `orders.json`, creates it for the first order, and returns 404 for reads before that file exists. File creation/read/write failures also return the requested HTML 404 page.

## Verification

On a configured PHP and Apache server, manually verify sign-in, required fields, product selection, order creation, duplicate IDs, and order lookup. A notebook and two bottles must produce a total of €72.60 including VAT. Check that missing orders and unavailable storage return HTTP 404, and that protected operations require sign-in. Confirm HTTPS and the Apache restrictions on direct JSON access.

PHP and Apache are not installed in the provided editing environment, so runtime verification and HTTPS activation require a PHP-enabled machine.

## Version control

The supplied workspace has no usable Git repository. Initialize a repository in a writable checkout, review the files, commit, and connect the GitHub repository supplied by the project owner. Runtime `comandes/orders.json` is ignored to avoid committing customer records. No GitHub repository, submission URL, fabricated commit history, or public deployment has been created.
