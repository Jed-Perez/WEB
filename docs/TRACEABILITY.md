# Requirements traceability matrix

Source: supplied Business Case Part I and Preliminary Investigation Report Part II, especially FR-01–FR-09. The requester approved the documented assumptions. This matrix traces implemented scope; physical/client acceptance limits are separate from software completion.

| Requirement | UI / workflow | Backend / database | Executed evidence |
|---|---|---|---|
| FR-01 Login | login.php, auth.js, profile | Auth.php; users, auth_attempts, password_resets | Role logins, logout invalidation, regeneration, throttling, profile/password and reset token consumption |
| FR-02 RBAC | Role-filtered PHP navigation | Auth::require, Resort::access, owner checks, role_permissions | Staff/guest/reception forbidden access, guest isolation, last-admin protection |
| FR-03 Online reservation | New reservation modal, availability, My reservations | Resort::availability/reserve/reservationStatus; reservations | Availability, capacity/dates, overlap, concurrent race, edit/status/check-in/out, browser reservation flow |
| FR-04 Guests/history | Guests, details/history modal | Resort::save/list/archive; guests/users/reservations | Add/update/archive/restore, linked history, XSS text safety, browser modal create/edit/detail |
| FR-05 Rooms/cottages | Rooms & cottages, occupancy | facilities and reservation queries | Inventory creation, availability, archive/restore and active-booking safeguards; dashboard SQL comparison |
| FR-06 Payment records | Payment modal, booking ledger/balance | Payment/void transactions, integer cents, payments | Partial/full/over/negative payment, void permissions, balance recalculation, check-out guard |
| FR-07 Maintenance | Issue modal, assignment, priority/status | maintenance, users and facilities FKs | Staff issue/progress/completion and mandatory resolution; browser page/form |
| FR-08 ESP32 monitoring | Device cards, stale status, history chart | api/sensor.php; devices/readings | Key validation/rotation, reading persistence, deduplication, conflict, range, rate limit, stale detection |
| FR-09 Dashboard/reports | Overview, charts, Reports | Dashboard SQL, report queries/export | Counts vs SQL, CSV/XLS/PDF signatures, browser overview and reports |
| User request: CRUD/AJAX | Shared module tables and Bootstrap modals | api/index.php REST-style operations | JSON validation, search, pagination, sorting and browser AJAX checks |
| User request: security | Escaped HTML, protected menus/forms | PDO, CSRF, CSP, protected directories, sessions | Apache denied paths, CSRF, injection treated as data, stored XSS rendered as text |
| User request: audit | Activity log | audit_logs in transactions | Creation/change/void/authentication entries retained; no edit/delete endpoint |
| User request: notifications | Bell and notifications list | notifications, recipient filtering | Persistent assigned updates, own read marking |
| User request: archive | Archive/restore buttons and filter | archived_at; transaction guards | Active booking restriction, archive visibility and restoration |
| User request: responsive UI | Sidebar, topbar, cards, responsive tables | Local Bootstrap/Icons/Chart.js/SweetAlert2 | Chrome desktop/mobile screenshots, navigation, overflow and JavaScript exception checks |
| User request: XAMPP delivery | README and setup CLI | Native PHP/MySQL, local assets, SQL import | Actual XAMPP Apache/PHP/MariaDB test deployment, schema import |
| User request: documentation | README + docs | Schema/ERD/source map | File manifest and ZIP validation |
| Follow-up: database installation | Local XAMPP `resort360`, admin invitation | tools/install_database.php, setup.php invite-admin | Fresh install, non-overwrite guard, hashed administrator and reset-code workflow |
| Follow-up: public landing | Anonymous index.php, home.php, live search and cards | api/public.php, landing.js, reservation intent | Public data boundaries, date validation, registration/sign-in handoff, real reservation, mobile navigation, FAQ, reset links (including existing sessions), actual localhost page |

## Explicitly unverified external acceptance

- Physical ESP32 sketch compilation/upload, wiring, sensor accuracy and on-site Wi-Fi.
- MNB Beach Resort's real inventory, tariffs, day-use rules, actual operating procedures and stakeholder acceptance.
- Production-scale capacity, penetration testing, uptime, restore-time targets and economic benefits.

No gateway, accounting, inventory, email/SMS, multi-resort support or extra sensor type is claimed. Detailed test outcomes, including final run counts, are in TEST_RESULTS.md and test-results.json. A loaded page is not evidence that every possible input or hardware condition has been tested.
