# Order Management

A PHP sales-order application with an English interface, server-side authentication, a fixed four-product catalogue, 21% VAT, and JSON persistence. No client storage or client-side order calculations are used. JavaScript handles form validation, navigation, requests, and display only.

## Requirements and launch

- PHP 8.1 or newer, with sessions and JSON enabled.
- Apache 2.4 with PHP configured, `mod_ssl`, and `mod_headers`.
- Chrome or Firefox with JavaScript enabled.

The existing checkout is the application root (the assignment calls this directory `orderManagementApp`; it can be deployed under that name without changing code). Open its `index.html` through Apache, rather than as a local file.

For development only, run `php -S 127.0.0.1:8080 -t .` from the project root and visit `http://127.0.0.1:8080`. The PHP development server does **not** process `.htaccess`; use it only on a trusted local machine, since the JSON files are not protected by Apache there. Production must use Apache and HTTPS.

For this local Apache checkout, run `sudo sh deployment/setup-local.sh` from a terminal. It enables HTTPS at `https://localhost/restaurantOrderApp/`, redirects this application's HTTP requests, and configures the orders directory permissions. The local certificate is self-signed and must be verified and trusted locally for browser testing. The script requires the administrator password and does not change the predefined user. Activation has not yet been completed because administrator authentication is required.

For production, adapt `deployment/apache-ssl.conf.example` to the actual hostname, root path, and issued, trusted TLS certificates, then enable it in Apache. HTTPS requests use secure, HttpOnly, SameSite session cookies.

Allow Apache's PHP process to create and update files in `orders/` and read `users/users.json`. Keep the users file read-only to the application. Do not use world-writable permissions. Enable `AllowOverride All` for the root so the `.htaccess` protections take effect. Direct requests to `users/users.json` and `orders/orders.json` must return 403 on Apache.

The configured user is `admin`. At the user's request, the example password hash has been replaced with a bcrypt hash of their chosen project password. Only the hash is stored in `users/users.json`.

To choose your own username and password, run this command from the project root in your terminal:

```sh
php deployment/set-user.php
```

Enter your username, password and password confirmation when prompted. Password input is hidden in an interactive terminal. The tool replaces the configured account only when validation succeeds, and stores a bcrypt hash rather than the plaintext password. Passwords must contain 8 to 72 bytes. Sign out of existing sessions before using the changed account. This is a CLI configuration tool, unavailable over HTTP; the website's sign-in form only authenticates existing accounts.

Folder names, filenames and the interface are in English, as confirmed by the user to meet the rubric.

## Layout

```text
index.html                      Application entry
application/client/index.html   English screens and forms
application/client/styles.css   Responsive presentation
application/client/app.js       Interface events and HTTP requests
application/server/common.php   Session, shared functions, product catalogue
application/server/data.php     Multidimensional runtime model and JSON adapters
application/server/session.php  GET session and CSRF token
application/server/authenticate.php  POST authentication
application/server/logout.php   POST sign out
application/server/products.php GET server-formatted catalogue
application/server/create-order.php POST validation, calculation, persistence
application/server/view-orders.php  GET all orders, or ?id=ORDER-ID
users/users.json                Configured account with bcrypt password hash
orders/orders.json              Existing JSON storage; initialized to [] during setup
deployment/                     Apache HTTPS example
tests/integration.php           Isolated PHP HTTP integration checks
RUBRIC.md                       Evidence and presentation checklist
```

## Using the application

1. Open the home screen, read the guide, and sign in.
2. Choose “Create a new order” or “View orders” in the operations menu and click its single “Continue” button.
3. To create an order, enter a unique order ID, full customer name, address, email, and phone. Check at least one product, enter a quantity from 1 to 10,000, and save. The server returns only the formatted total including VAT on success, with HTTP 200.
4. To review orders, click “View orders”, choose all orders or one order by ID, and press the single “Continue” button. For one order, enter its exact, case-sensitive ID on the next screen and click “Find order”. The server returns HTML tables and HTTP 200. Missing files and missing IDs return an HTML error page and HTTP 404, with links to the order menu, operations menu, and home.

Authentication failure displays an error and returns to the home screen. Protected server operations independently verify the session. POST endpoints also validate a session CSRF token. Duplicate IDs return 409; invalid fields return 422; unauthenticated requests return 401; incorrect HTTP methods return 405.

The catalogue is defined once in PHP: notebook €12, bottle €24, tote bag €18, and desk lamp €45, before VAT. Prices and totals are stored as integer cents. VAT is rounded once per order using integer arithmetic. A notebook and two bottles cost €60 before VAT, €12.60 VAT, and €72.60 in total.

Every saved record contains customer data, selected products and their quantities and unit prices, subtotal, VAT, total, the authenticated author, and a UTC creation timestamp. A lock covers the complete read/duplicate-check/write transaction to prevent concurrent requests overwriting each other. Invalid JSON is reported rather than silently discarded. A single local JSON file is suited to this assignment; it has no database transactions or automated backups.

The file is `orders/orders.json` (`orders.js` in the attachment is a typo). At the user’s request, both reading and saving require an existing file: a missing or inaccessible file returns HTTP 404 with an HTML error message and three return buttons. The setup script initializes a missing file to `[]`; request handlers do not create it. Existing orders are preserved during setup.

## Verification

Run `php tests/integration.php`. All 63 checks passed against an isolated copy with temporary credentials and order storage. No Python is required. The checks include associative runtime collections, nested customer/pricing data, compatibility with existing JSON, multiple orders, and exact lookup of numeric-looking IDs. All PHP files passed `php -l`. Apache's session endpoint was independently confirmed to return HTTP 200 and JSON with PHP 8.4 installed.

## Multidimensional associative runtime data

Business logic uses named, nested PHP arrays throughout creation, authentication, lookup and rendering:

```php
$orders['order:ORD-001']['identity']['id'];
$orders['order:ORD-001']['customer']['email'];
$orders['order:ORD-001']['products']['bottle']['quantity'];
$orders['order:ORD-001']['pricing']['totalCents'];
$orders['order:ORD-001']['metadata']['createdBy'];
$users['user:mar']['identity']['username'];
$users['user:mar']['authentication']['passwordHash'];
$products['bottle']['price'];
```

`data.php` converts the existing JSON lists into these associative runtime collections immediately after reading them. `encodeOrders()` converts them back only when writing, preserving the on-disk order format and the required users format. Prefixes keep identifiers such as `123` and `0123` distinct string keys. Lookup and duplicate detection use those keys directly. Empty collections, HTTP input/response fields, serialization lists and PHP API option arrays are boundary structures rather than alternative business data models.

The current Apache configuration ignores `.htaccess`: a direct request to `users/users.json` returned HTTP 200 during verification. Activate the prepared setup before using real credentials or customer records, then confirm direct JSON requests return 403. Also walk through the browser screens and confirm HTTPS. See `RUBRIC.md` for the complete evidence, remaining deployment checks and presentation sequence. No browser automation was available for this review.

## Version control

The workspace has an existing Git repository and an origin at https://github.com/MarSalonParera/daw2_salon_mar_pj6f4a11. Existing commits record the initial structure and subsequent implementation. Review the final changes, commit them and push to the intended branch before submitting the repository URL. Runtime `orders/orders.json` is ignored to avoid committing customer records. No commit history has been fabricated and no GitHub push has been performed during this review. Submission and presentation deadlines were not supplied.
