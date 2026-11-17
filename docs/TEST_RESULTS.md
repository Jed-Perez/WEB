# Actual test results

Final automated result: **204/204 checks passed**.

Execution date: 14 November 2026, Asia/Manila. Environment: Windows, XAMPP Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32, installed Google Chrome, Playwright. Workflow test fixtures were isolated on localhost ports 8088 and 3307. Landing checks also verified the actual localhost installation through read-only browser visits.

PHP application/test syntax checks and JavaScript parse checks also passed. The SQL was imported into empty test databases. Source libraries were bundled with licenses and SHA-256 checksums. See the JSON files for exact per-run timestamps.

## Issues found and resolved

- Apache sandbox initially could not write XAMPP session files; test Apache was rerun with approved session-directory access.
- Apache did not preserve the nonstandard HTTP 419 status; CSRF failures now use standard 403.
- Device Authorization was initially unavailable to PHP; CGIPassAuth On now forwards it.
- The mobile menu backdrop intercepted clicks; sidebar stacking was corrected and retested.
- Windows test import encoding damaged demo text; UTF-8 import/read settings were made explicit and a Unicode round-trip check was added.
- Browser test row selection was tightened to await/select the intended record after AJAX changes.

## Final executed checks

| Check | Result |
|---|---|
| Schema import and hashed test account setup | PASS |
| SQL sample inventory preserves UTF-8 | PASS |
| Anonymous API denied | PASS |
| Apache protects config/config.php | PASS |
| Apache protects app/Auth.php | PASS |
| Apache protects database/database.sql | PASS |
| Apache protects storage/php-errors.log | PASS |
| Apache protects tests/seed.php | PASS |
| Apache protects tools/setup.php | PASS |
| Apache protects docs/REQUIREMENTS.md | PASS |
| Apache protects views/layouts/head.php | PASS |
| Login admin | PASS |
| Login reception | PASS |
| Login staff | PASS |
| Login guest | PASS |
| Login guest2 | PASS |
| Anonymous session can log in | PASS |
| Session ID regenerated on login | PASS |
| Mutation without CSRF rejected | PASS |
| Staff cannot read guests | PASS |
| Reception cannot administer users | PASS |
| Guest cannot read audit history | PASS |
| Guest cannot create facilities | PASS |
| Invalid guest form rejected | PASS |
| Invalid JSON rejected | PASS |
| Create guest with safely stored special characters | PASS |
| Search guest by literal text | PASS |
| SQL injection input treated as data | PASS |
| Injection returns no unrelated records | PASS |
| Edit guest | PASS |
| Create Unicode guest | PASS |
| UTF-8 guest text round-trips through PDO and JSON | PASS |
| Create facility | PASS |
| Availability lookup | PASS |
| New facility is available | PASS |
| Guest creates own reservation | PASS |
| Other guest cannot view reservation | PASS |
| Overlapping reservation blocked | PASS |
| Capacity violation blocked | PASS |
| Departure before arrival blocked | PASS |
| Guest cannot confirm booking | PASS |
| Pending reservation edited | PASS |
| Archive facility with active booking blocked | PASS |
| Maintenance availability conflicting with bookings blocked | PASS |
| Confirm reservation | PASS |
| Edit confirmed reservation rejected | PASS |
| Check in during reserved stay | PASS |
| Check out with balance rejected | PASS |
| Record partial payment | PASS |
| Read computed balance | PASS |
| Balance and partial status accurate | PASS |
| Overpayment rejected | PASS |
| Negative payment rejected | PASS |
| Reception cannot void payment | PASS |
| Admin void requires reason | PASS |
| Admin void keeps ledger | PASS |
| Voided payment excluded from balance | PASS |
| Balance restored after void | PASS |
| Full settlement | PASS |
| Check out after settlement | PASS |
| Guest booking history | PASS |
| History links guest reservation | PASS |
| Guest sees only own payments | PASS |
| Other guest ledger empty | PASS |
| Archive completed-use facility | PASS |
| Archived facility view | PASS |
| Archived record retained | PASS |
| Restore facility | PASS |
| Archive guest without active bookings | PASS |
| Restore guest | PASS |
| Staff creates maintenance | PASS |
| Maintenance completion requires resolution | PASS |
| Staff updates maintenance progress | PASS |
| Staff completes maintenance | PASS |
| Create device with one-time key | PASS |
| Invalid sensor key denied | PASS |
| Authenticated temperature ingestion | PASS |
| Sensor retry deduplicated | PASS |
| Conflicting sample retry rejected | PASS |
| Sensor rate limit enforced | PASS |
| Sensor range validated | PASS |
| Staff sees recorded temperature | PASS |
| One physical sample stored | PASS |
| Device listing contains no credential | PASS |
| Device key never returned in listings | PASS |
| Delayed sensor marked stale | PASS |
| Rotate device credential | PASS |
| Rotated old key revoked | PASS |
| Export csv | PASS |
| Export xls | PASS |
| Export pdf | PASS |
| Guest export denied | PASS |
| Pagination works | PASS |
| Pagination contains one row and correct page | PASS |
| Dashboard from database | PASS |
| Dashboard count matches SQL | PASS |
| User cannot remove last admin | PASS |
| Create staff account through administration | PASS |
| Create session for idle-expiry test | PASS |
| Idle session expiry enforced | PASS |
| Server-side alphabetical sorting | PASS |
| Sorting matches database order | PASS |
| Create booking for cancellation | PASS |
| Guest cancels own request | PASS |
| Create booking for rejection | PASS |
| Reception rejects request | PASS |
| Reception cannot access management export | PASS |
| Concurrent booking race has one winner | PASS |
| Guest self-registration | PASS |
| Issue identity-verified reset | PASS |
| One-use reset accepted | PASS |
| Reset token reuse rejected | PASS |
| New password works | PASS |
| Profile change with wrong current password rejected | PASS |
| Profile and password update | PASS |
| Deactivate account | PASS |
| Deactivation invalidates existing session | PASS |
| Deactivated login denied | PASS |
| Notifications persist | PASS |
| Mark notifications read | PASS |
| No unread notifications remain | PASS |
| Audit entries present | PASS |
| Audit log contains important operations | PASS |
| Logout | PASS |
| Logged-out session denied | PASS |
| Invalid login attempt 1 | PASS |
| Invalid login attempt 2 | PASS |
| Invalid login attempt 3 | PASS |
| Invalid login attempt 4 | PASS |
| Invalid login attempt 5 | PASS |
| Sixth failed login throttled | PASS |
| Chrome login and dynamic dashboard | PASS |
| Chrome page reservations | PASS |
| Chrome page guests | PASS |
| Chrome page facilities | PASS |
| Chrome page payments | PASS |
| Chrome page maintenance | PASS |
| Chrome page devices | PASS |
| Chrome page readings | PASS |
| Chrome page reports | PASS |
| Chrome page users | PASS |
| Chrome page audit | PASS |
| Chrome page notifications | PASS |
| Chrome page profile | PASS |
| Chrome AJAX create and search | PASS |
| Chrome modal edit updates table | PASS |
| Chrome detail modal | PASS |
| Stored XSS rendered as text | PASS |
| Mobile page has no horizontal overflow | PASS |
| Mobile navigation works | PASS |
| Chrome guest availability and reservation modal | PASS |
| Guest navigation hides administration | PASS |
| No browser JavaScript exceptions | PASS |
| Initial administrator CLI creates account | PASS |
| Setup stores a hash instead of plaintext | PASS |
| Initial administrator CLI refuses a second bootstrap | PASS |
| CLI recovery issues one-use token | PASS |
| Review administrator login | PASS |
| Void incorrect receipt after check-out | PASS |
| Payment selector exposes outstanding checked-out stay | PASS |
| Checked-out correction can be selected | PASS |
| Corrective payment after check-out | PASS |
| Read corrected completed stay | PASS |
| Corrective ledger preserves stay and zero balance | PASS |
| Mobile chart canvases contain rendered pixels | PASS |
| Mobile dashboard has no overflow | PASS |
| Bootstrap form renders for reservations | PASS |
| Bootstrap form renders for guests | PASS |
| Bootstrap form renders for facilities | PASS |
| Bootstrap form renders for payments | PASS |
| Bootstrap form renders for maintenance | PASS |
| Bootstrap form renders for devices | PASS |
| Bootstrap form renders for users | PASS |
| Review has no JavaScript exceptions | PASS |
| Anonymous root serves the landing page | PASS |
| Public catalog is database backed | PASS |
| Public catalog exposes only accommodation fields | PASS |
| Non-bookable facilities excluded from catalog | PASS |
| Public endpoint rejects mutations | PASS |
| Public availability validates dates | PASS |
| Public availability returns valid units | PASS |
| Database installer refuses existing tables | PASS |
| Installer refusal preserves data | PASS |
| CLI installer supports a fresh configured database | PASS |
| Administrator invitation creates a one-use code | PASS |
| Invited administrator has an active role and hashed password | PASS |
| Landing inventory cards render | PASS |
| AJAX search displays selected dates | PASS |
| Booking link opens guest registration | PASS |
| Sign-in restores reservation dates | PASS |
| Sign-in restores selected facility and guest count | PASS |
| Landing handoff creates a real reservation | PASS |
| Signed-in guests can return to public landing | PASS |
| Mobile landing has no horizontal overflow | PASS |
| Mobile public navigation works | PASS |
| FAQ accordion works | PASS |
| Signed-in account can open reset-link form | PASS |
| Reset link prefills the one-use token | PASS |
| Reset token fragment removed from address bar | PASS |
| Reset-link password change succeeds | PASS |
| Live XAMPP landing serves successfully | PASS |
| Live installation shows three seeded bookable spaces | PASS |
| Live landing has no mobile overflow | PASS |
| Landing and handoff have no JavaScript exceptions | PASS |

## Verification limits

No physical ESP32, Arduino compilation, sensor calibration, production load test, external penetration test, or resort stakeholder acceptance was performed. HTTP ingestion was tested with authenticated requests. Reports were checked for correct download formats and API authorization; manual reconciliation with real resort records remains a client acceptance step. See TESTING.md.
