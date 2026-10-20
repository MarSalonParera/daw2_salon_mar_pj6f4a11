# Rubric evidence and presentation checklist

Reviewed on 20 October 2026. This records implementation evidence; it does not assign a grade or claim that a submission deadline has been met.

| Criterion | Evidence | Status |
| --- | --- | --- |
| Git and GitHub | Existing repository, two real historical commits, GitHub origin configured | Connected; final reviewed changes must be committed and pushed before submission |
| HTTPS | `deployment/setup-local.sh`, local TLS virtual host, redirect, production TLS example | Prepared; activation requires administrator password; local certificate is self-signed |
| HTTP methods and status | GET for session, catalogue and tables; POST for sign-in, sign-out and creation; explicit 200/404/405 | Tested with PHP HTTP integration checks |
| File and JSON storage | One `orders/orders.json`, created on first successful order; locked read/write, JSON decoding and schema validation | Tested in isolated storage |
| Reusable PHP functions | Shared method, authentication, CSRF, JSON, decoding, escaping, rendering and table functions; separate creation functions | Implemented |
| Multidimensional associative arrays | Product catalogue, customers/order fields, selected products and persisted order records use nested associative arrays | Implemented; the JSON root is a list of order objects |
| Business logic I | Home, guide, authentication, operations menu, creation, viewing menu, all orders and exact-ID lookup | Implemented; server requests tested, browser walkthrough remains |
| Business logic II | Invalid credentials, missing/unreadable file and missing order; HTML 404 pages with all return links | Tested |
| External CSS | `application/client/styles.css`, shared by static screens and PHP tables | Implemented |
| HTML and JavaScript | Forms, DOM updates, functions, events, fetch, quantity enable/disable and required-field checks | Implemented; business calculations and persistence remain in PHP |
| English | English application folders, filenames, labels, documentation and server messages | Implemented with the user's approval to restore English folder names |
| On-time delivery and presentation | GitHub URL below; walkthrough below | Must be completed by the student; deadline and presentation date were not provided |
| Late presentation deduction | Depends on actual submission and presentation dates | Cannot be determined from the code |

## Run the automated checks

```sh
find application tests -name '*.php' -exec php -l {} \;
php tests/integration.php
```

The 49 checks pass against a disposable PHP server with a temporary password hash and temporary orders. They do not alter `users/users.json` or real customer orders. They cover authentication, cookie flags, protected endpoints, GET/POST restrictions, CSRF, missing fields, email and quantity validation, four products, 21% VAT, plain-text creation response, JSON persistence, duplicates, complete HTML tables, escaping, missing file/order, malformed storage, unavailable directory, and logout.

Apache was independently checked: the session endpoint responds with HTTP 200 and `application/json`. Direct access to `users/users.json` currently returns HTTP 200 because the active Apache configuration ignores the project's `.htaccess`. This must be fixed by activating the prepared Apache configuration before using real credentials or customer records. HTTPS and actual Apache write permissions also remain pending that administrator step. A passing PHP development-server test does not prove Apache file restrictions or HTTPS activation. Validate those after running the setup command.

## Activate and verify Apache HTTPS

```sh
sudo sh deployment/setup-local.sh
```

This enables Apache SSL/headers/rewrite, creates a localhost certificate with SANs for localhost and 127.0.0.1, applies the project configuration, gives Apache group write access to the orders directory, and reloads Apache after a configuration check. It does not change the predefined user. The administrator password must be entered in a normal terminal. For a real deployment use a trusted domain certificate and the production example, rather than the development certificate.

Both prepared local Apache configuration files passed `apache2 -t` with temporary test certificates (`Syntax OK`). This syntax check did not activate HTTPS or modify the running server.

After activation, verify:

- `http://localhost/restaurantOrderApp/` redirects to HTTPS.
- `https://localhost/restaurantOrderApp/` opens the application; verify and trust the development certificate locally.
- `https://localhost/restaurantOrderApp/application/server/session.php` returns JSON and a cookie with `Secure`, `HttpOnly` and `SameSite=Strict`.
- Direct requests to `/restaurantOrderApp/users/users.json` and `/restaurantOrderApp/orders/orders.json` return HTTP 403.
- Use the browser Network panel to verify the documented 200, 401, 404 and 405 responses.

## Presentation walkthrough

Account configuration: run `php deployment/set-user.php` in the project terminal to choose the username and password. Only a bcrypt hash is saved in `users/users.json`; failed validation leaves the previous account intact. This tool is CLI-only. The website login checks both fields before making a request, verifies the saved hash on the server, opens the operations menu on success, and shows an error on the home screen on failure. The operations menu uses direct action buttons at the user’s request. The viewing menu follows section 6: choose all orders or one by ID and use one Continue button. The one-order screen includes its input, GET submit button and links to the viewing menu, operations menu and home. Previous isolated JavaScript checks covered authentication; a full browser walkthrough remains necessary.

1. Show the logo and application name, open the guide, and return home.
2. Demonstrate required sign-in fields and invalid authentication returning home with an error.
3. Sign in as `admin` with the project password chosen by the user and select creation from the operations menu.
4. Enter all customer fields and a unique ID; select one notebook and two bottles. Show enabled quantities and save.
5. Explain that PHP calculates €60.00 subtotal, €12.60 VAT and €72.60 total, and returns only the formatted total.
6. Show the JSON file and its nested product/customer fields without exposing customer data from other work.
7. Show all orders, then find the new order by its ID. Show every meaningful table column.
8. Look up an unknown ID and demonstrate HTTP 404 and all return links. Demonstrate file errors in an isolated copy rather than deleting real orders.
9. Show external CSS, JavaScript event functions and PHP reusable functions.
10. Show HTTPS, the secure session cookie, Git history and the GitHub repository.

## Submission

Configured repository: https://github.com/MarSalonParera/daw2_salon_mar_pj6f4a11

Review and commit the actual changes, then push to the intended branch using your configured GitHub credentials. Do not fabricate dated commits to simulate project evolution. Submit the repository URL and attend the presentation by the dates set by the instructor. The example account hash has been replaced, at the user's request, with a bcrypt hash of their chosen project password.
