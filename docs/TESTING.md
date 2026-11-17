# Testing and reproduction

> Updated scope: landing-page booking, room listings and saved booking handoff have been removed at the user's request. Reservations run in the signed-in workspace. The earlier landing-process tests/results below document the previous implementation and are no longer current acceptance checks.

Tests run against an isolated Apache instance at `127.0.0.1:8088` and a newly initialized MariaDB instance at `127.0.0.1:3307`. The normal XAMPP database and its data are not used. `tests/run.py` recreates **only `resort360_test` on port 3307**. `tests/final_review.py` also recreates **only `resort360_test_setup` on that port**. Do not repoint these scripts to a live database.

## Automated checks

Landing process regressions: run `python tests/landing_process.py` after the base suite. It writes only to `resort360_test` on port 3307, creates uniquely named test inventory/guests, and resets the generated test administrator's password. It tests stale-search invalidation, reordered responses, login return, preserved notes, price-change rejection, duplicate-click prevention, pending confirmation, guest ledger/cancellation, expired drafts and draft isolation on logout. Results are `docs/landing-process-results.json`; the mobile review capture is `docs/landing-review-mobile.png`. The updated `tests/landing_checks.py` tests registration through submission on the landing page.

Admin accuracy checks: after `tests/run.py`, run `python tests/admin_accuracy.py` against the same isolated services, then `node tests/admin_ui.test.js`. The admin suite adds uniquely named fixtures only to `resort360_test` on port 3307 and resets only generated test account passwords. It covers full report totals and printing, availability ownership, notification counts and ownership, independent sensor series, dashboard actions, refresh failure/recovery and mobile layout. The Node checks reproduce reordered responses, navigation during report loading and filter invalidation. Browser checks may require access to the installed Playwright package and Chrome. Results are in `docs/admin-accuracy-results.json`; screenshots are `docs/admin-updated-desktop.png` and `docs/admin-updated-mobile.png`.

`tests/run.py` imports the delivered SQL, creates generated test accounts, makes real cookie-authenticated HTTP requests to Apache/PHP, runs concurrent booking requests in separate sessions, and drives installed Chrome through Playwright. Test passwords are randomly generated in process memory; none are packaged. It writes `docs/test-results.json` and browser screenshots.

`tests/final_review.py` verifies the actual first-administrator CLI, refusal of repeated bootstrapping and CLI recovery. It also verifies chart canvas pixels on a mobile viewport and opens every writable module's Bootstrap form. It writes `docs/review-results.json`.

`tests/landing_checks.py` adds landing/catalog/availability checks, registration and sign-in with a preserved selection, a real test reservation, mobile navigation, FAQ behavior, invitation and reset links, and the installer's non-overwrite guard. Its destructive setup is restricted to `resort360_test_invite` on port 3307; its browser visits to `http://localhost/webngakingbaby/` are read-only. It writes `docs/landing-results.json`. Run it after the other two suites.

The final test report is generated from those JSON files. Earlier failures are not counted as final passes. The report describes the fixes discovered during execution and the external limitations.

## Reproduce on this workstation

1. Start the isolated services with `powershell -ExecutionPolicy Bypass -File tests/start.ps1`. It creates its data/log/config files below `tests/runtime`. Default binaries are under `C:\xampp`; pass `-XamppRoot` if different. Apache uses the XAMPP PHP session directory, which must be writable.
2. Install optional **test-only** tooling: `python -m pip install --target tests/runtime/python playwright`. Chrome must be installed at the path specified in the test scripts. These tools are not needed to run the delivered application.
3. Run `python tests/run.py`, then `python tests/final_review.py`. In a restricted agent environment, Chrome/session-file access may require execution approval.
4. Run PHP syntax checks (`C:\xampp\php\php.exe -l <file>`) and, if Node is available as a test utility, `node --check assets/js/app.js`, `common.js`, and `auth.js`. Node is not used by the application.
5. Stop the two isolated services using the PIDs returned by `tests/start.ps1`; verify they still refer to those test instances. For MariaDB, `C:\xampp\mysql\bin\mysqladmin.exe --host=127.0.0.1 --port=3307 --user=root shutdown` shuts down only the test instance. Do not stop your normal XAMPP services by mistake.

The scripts assume localhost ports 3307/8088 are free, PHP 8.2-compatible XAMPP paths, and no `config/local.php` overriding their test environment variables. Use a clean checkout for reproduction if you have configured a live local.php.

## Manual acceptance checklist

- Import into an empty database using phpMyAdmin; securely create initial admin; replace demo inventory/rates.
- Sign in under all four roles and compare visible and API permissions with REQUIREMENTS.md.
- Book adjacent dates, attempt overlap/capacity errors, cancel requests, verify occupied counts and guest history.
- Record/void partial and full payments, reconcile balances, check out, review paid-cancellation notifications.
- Assign/complete maintenance, block and restore inventory, verify active-booking archive safeguards.
- Confirm search, sort, filter, pagination, details, status confirmations and modal errors on desktop and mobile.
- Review generated CSV/XLS/PDF content with the resort's own approved sample records; verify date meanings and preview limits.
- Connect actual ESP32 hardware; verify reading accuracy, power cycling, disconnect/stale behavior and key rotation.
- Configure and test backups/restore and deployment permissions appropriate to the actual environment.

## Limits

The tests cover representative valid and invalid workflows, not every possible input combination. No production load benchmark, external penetration test or real resort user-acceptance session was performed. Hardware and Arduino compilation were not available. PDF export uses a core Latin font and transliterates unsupported text; CSV/XLS retain Unicode. The SQL sample inventory is synthetic and the screenshots contain disposable QA records, including intentionally hostile text used to verify escaping.
