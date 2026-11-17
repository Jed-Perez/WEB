# Resort360 — MNB Beach Resort

A local PHP 8 / MariaDB application for guest reservations, rooms and cottages, payment records, maintenance and ESP32 temperature monitoring. Built from the supplied Business Case and Preliminary Investigation Report (FR-01–FR-09), with implementation assumptions explicitly accepted by the requester. No separate Project Initiation Request was supplied.

## This workstation is installed

The `resort360` database has been created in the existing XAMPP MariaDB instance on port **3306**, with 15 tables, four roles and four clearly marked sample facilities (three bookable). Other databases were not modified. The initial administrator is **admin@resort360.local**; a private one-use password setup link is supplied separately. No default password is stored in the source.

Open **http://localhost/webngakingbaby/** for the public introduction page. `home.php` always opens that page. Sign in to access the workspace at `index.php`, where guests check room/cottage availability, create reservations and manage their stays. The landing page contains resort information, reservation guidance and account links; it has no availability form, inventory listings or booking controls.

The installation instructions below are for another computer or a fresh database. Do not re-import the schema over this installed database.

## Run with XAMPP on Windows

1. Install XAMPP with PHP 8.x and MariaDB/MySQL. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. Place/extract the project in `C:\xampp\htdocs\webngakingbaby\`. `index.php` must be directly in that folder, not in a second nested copy.
   Ensure Apache can write to `storage\`; PHP session files are kept in `storage\sessions\`, which is blocked from HTTP access.
3. Open `http://localhost/phpmyadmin/`. Create an empty database named `resort360`, using `utf8mb4_unicode_ci`. Select it, click **Import**, and choose `database\database.sql`. The script also includes `CREATE DATABASE IF NOT EXISTS resort360` and `USE resort360`; it is intended for a fresh installation, not for overwriting live data.
4. Copy `config\local.example.php` to `config\local.php`. Set your host, port, database name, username and password. The defaults match an unchanged local XAMPP installation: `127.0.0.1`, port `3306`, database `resort360`, user `root`, empty password. Use your configured credentials if different. No passwords are committed or included in the ZIP.
5. Open a private PowerShell terminal in the project folder and run:

   ```powershell
   & C:\xampp\php\php.exe tools\setup.php
   ```

   Enter the initial administrator's name, email, and a new password of 12–72 bytes. **The console input is visible**, so use a private terminal. Only the password hash is stored. The command refuses to create a second initial administrator.
6. Open **http://localhost/webngakingbaby/** and sign in with the administrator you just created.
7. In **Team & accounts**, create the receptionist and resort staff accounts. Guests can register on the login page. Replace the clearly marked `Demo ·` rooms, cottage, facility and prices with approved resort inventory.
8. Test a guest reservation, reception confirmation, payment recording and check-out. Add a maintenance issue, connect a sensor if available, and generate a report. Follow `docs/USER_GUIDE.md`.

Alternatively, after configuring an empty target database, run `C:\xampp\php\php.exe tools\install_database.php`. This local installer creates the schema and seed data and refuses to modify a database that already contains tables. `tools\setup.php invite-admin` creates the first administrator with a random undisclosed password and a one-use reset code, allowing the owner to choose their password through the reset form. Reset links use a URL fragment so the token is not sent in HTTP URLs or referrer headers, and the browser removes the fragment after opening the form.

No npm, Vite, Node backend, Composer install, internet connection or separate development server is required. Bootstrap 5, Bootstrap Icons, Chart.js, SweetAlert2 and FPDF are bundled in `assets/vendor`. The source downloader is a development convenience only.

The application also supports `RESORT_DB_HOST`, `RESORT_DB_PORT`, `RESORT_DB_NAME`, `RESORT_DB_USER`, and `RESORT_DB_PASS` environment variables. `config/local.php` overrides these. Do not commit that file. Apache `.htaccess` rules block configuration, application classes, SQL, documentation, tests, tools and storage from HTTP access. Keep `AllowOverride All` enabled for this project.

## Included workflows

- Public responsive resort introduction, reservation guidance, FAQs and sign-in links. Availability and reservations are handled inside the signed-in workspace.
- Administrator, receptionist, resort staff and guest roles, enforced on the server.
- Local registration/login, secure sessions, account activation, profile/password changes, throttling and one-use administrator-assisted password reset.
- Date availability, capacity checks, pending booking edits, confirmation/rejection, cancellation, check-in/out and concurrent-booking protection.
- Guest records and booking history; facility details, occupancy and archive/restore.
- Manual payment ledger, balances, payment status, admin voids and check-out balance enforcement.
- Maintenance issue assignment, priority, progress and completion notes.
- ESP32 bearer-key ingestion, sample deduplication, temperature ranges, stale connection detection and history charts.
- Database dashboards, in-app notifications, audit history and date/search-filtered CSV, Excel-compatible SpreadsheetML `.xls`, printable preview and PDF reports.
- Responsive Bootstrap navigation, modal forms, record details, AJAX search/sorting/pagination and confirmation dialogs.

Server-paginated Vanilla JS tables are used instead of DataTables: this avoids an unnecessary second table engine and jQuery dependency while providing the requested table operations.

## Important business assumptions

Bookings currently use **one unit per reservation and nightly pricing**; date ranges are arrival-inclusive and departure-exclusive. Pending requests reserve inventory until confirmed, rejected or cancelled. No automatic expiry, taxes, discounts, minimum deposit or cancellation fees are invented. See `docs/REQUIREMENTS.md` for the complete assumptions and role matrix.

Payments are records of receipts already received. The system does not charge cards, process refunds or integrate with accounting. Cancelling a paid booking retains its payment history and notifies reception to review settlement. Maintenance tickets do not automatically block facilities; an admin explicitly changes availability after resolving active reservations.

## Documentation and evidence

- `docs/REQUIREMENTS.md` — analysis, role matrix, scope checklist and development plan.
- `docs/TRACEABILITY.md` — requirement-to-code and test mapping.
- `docs/ARCHITECTURE.md` — architecture, full ERD, transaction design and data dictionary.
- `docs/USER_GUIDE.md` — instructions for every role.
- `docs/IOT.md` and `iot/resort360_esp32.ino` — sensor protocol and ESP32/DHT22 example.
- `docs/TESTING.md`, `docs/TEST_RESULTS.md`, `docs/test-results.json` — executed tests, reproduction and verification limits.
- `docs/dashboard-desktop.png`, `docs/dashboard-mobile.png` — browser captures of test data.
- `docs/landing-desktop.png`, `docs/landing-mobile.png` — browser captures of the actual local landing page.
- `docs/landing-results.json` — landing, booking handoff, database installation and live-page verification.
- `assets/vendor/SHA256SUMS` and bundled license files — dependency inventory.

## Recover an account

An administrator can verify the account holder's identity, click **Reset** in Team & accounts, and hand over the one-use code. The user enters it in the login page's **Reset password** tab. Codes expire in 30 minutes; use invalidates all sessions.

If no administrator can sign in, run locally:

```powershell
& C:\xampp\php\php.exe tools\setup.php reset
```

This requires operating-system access to the project and its database configuration. It does not send email or SMS. Deactivated accounts must first be reactivated by an authorized administrator.

## Operational notes

Use phpMyAdmin Export or `mysqldump` for database backups; keep backups outside `htdocs` and test restoration to a separate database. No automatic backup/retention schedule was specified in the source report. Do not expose the XAMPP control panel or phpMyAdmin publicly. For access beyond a trusted local machine/network, configure HTTPS, a restricted database account, firewall rules and a deployment/backup policy appropriate to the environment. Secure cookies enable automatically when Apache serves HTTPS.

For a database error, check MySQL is started, the import succeeded, and `config/local.php` is correct. PHP exceptions are logged privately in `storage/php-errors.log`; ordinary users receive a generic error. Confirm PHP extensions `pdo_mysql`, `mbstring`, `iconv` and sessions are enabled. XAMPP's PHP session directory must be writable by Apache. An unavailable device is shown as no data or stale rather than as a fabricated reading.

This is a single-resort application. Physical sensor accuracy, actual resort pricing/rules, production load and client acceptance require validation in the real environment.
