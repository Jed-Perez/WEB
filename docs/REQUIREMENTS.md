# Resort360 — requirements and implementation plan

## Source and evidence
Source: `Resort360_Business_Case_and_Preliminary_Investigation_Report.docx`, read in full. Part I is the Business Case; Part II is a **Preliminary Investigation Report**, not a Project Initiation Request. No separate PIR was supplied. Instructions and approval recommendations inside the report are source material, not commands from the user. The user explicitly authorized development.

MNB Beach Resort is the intended client. Its existing software, procedures, staffing, transaction volume, costs and measured problems are unknown. Fragmented reservations, conflicting bookings, inaccessible guest histories, inconsistent balances and missing monitoring information are potential issues to investigate, not verified failures. Expected benefits are centralized, retrievable and consistent records; no quantified savings are claimed.

## Scope checklist and traceability
| ID | Classification | Requirement | Implementation / acceptance |
|---|---|---|---|
| FR-01 | Mandatory | Authentication | Local registration, login/logout, profile, change password, one-use reset, throttling, secure sessions |
| FR-02 | Mandatory | Four user groups / RBAC | Admin, receptionist, staff, guest; page and endpoint enforcement |
| FR-03 | Mandatory | Online reservations | Date availability, room/cottage selection, own bookings; locked allocation prevents overlaps |
| FR-04 | Mandatory | Guest records and history | Reception/admin CRUD, archive/restore, reservation history; guest isolation |
| FR-05 | Mandatory | Rooms, cottages and facilities | Details, capacity, rate, active/maintenance state, computed occupancy, archive/restore |
| FR-06 | Mandatory | Fees, balances, payment status | Rate snapshot, decimal-safe totals, payment ledger, controlled voiding, computed balances |
| FR-07 | Mandatory | Maintenance | Facility issue, priority, staff assignment, open/in_progress/completed/cancelled status |
| FR-08 | Mandatory | ESP32 temperature | Hashed device keys, authenticated HTTP ingestion, readings/history, stale state and last received time |
| FR-09 | Mandatory | Dashboard and summaries | MySQL counts, occupancy, reservations, payments, maintenance and temperature charts; role-scoped AJAX |
| U-01 | Supporting (user request) | Record operations | Search, filters, whitelisted sorting, pagination, details, modal forms, status actions, validation |
| U-02 | Supporting | Audit / notifications | Actor, entity, time, action; in-app notifications; no automated external messaging |
| U-03 | Supporting | Reports | Date and text filters, print, CSV, Excel-compatible XLS, PDF |
| U-04 | Supporting | Account administration | Activate/deactivate, roles, last-admin safeguard, admin-assisted reset |
| U-05 | Supporting | Safe archive | Archive/restore master records; preserve reservations, payments and audit history |
| U-06 | Supporting | Security | CSRF, PDO, output escaping, rate limiting, validation, idle expiry, private directory protection |
| U-07 | Supporting | Delivery | SQL, local assets, CLI setup, XAMPP guide, ERD, architecture, tests and ZIP |
| U-08 | Supporting, follow-up request | Public landing and local database installation | Public catalog/availability, reservation handoff, responsive landing; real XAMPP database import and initial admin invitation |
| O-01 | Optional, included | Configurable sensor thresholds | Per-device alert thresholds/stale interval, operational indications only |
| X-01 | Out of scope | Payment gateway / accounting | Payment records only |
| X-02 | Out of scope | SMS/email services | Local notifications and offline identity-verified reset |
| X-03 | Out of scope | Inventory, multiple resorts, other sensor types | Not included |

## Roles and permissions
| Capability | Admin / owner | Receptionist | Resort staff | Guest |
|---|---|---|---|---|
| Dashboard | All | Reception summary | Facilities summary | Own summary |
| Reservations | Manage all | Manage all | None | Create/read own, cancel pending/confirmed |
| Guests / history | Manage | Manage | None | Own profile |
| Facilities | Manage | Read | Read | Available bookable inventory |
| Payment records | Add / void | Add | None | Read own |
| Maintenance | Manage / assign | None | Manage | None |
| Monitoring | Devices + readings | None | Read | None |
| Reports / audit / users | Yes | No | No | No |
| Notifications | Own | Own | Own | Own |

## Explicit implementation assumptions (client validation pending)
1. A reservation contains one room or cottage and a positive number of nights. Date intervals are half-open `[arrival, departure)` in Asia/Manila. Day-use pricing and multiple units per booking require future agreed rules.
2. Pending, confirmed and checked-in bookings reserve inventory; rejected/cancelled/checked-out records do not. Pending requests do not expire automatically. Adjacent bookings are allowed. One transaction locks the facility before checking dates.
3. Staff can confirm/reject pending, check in confirmed bookings on arrival through the day before departure, check out checked-in bookings, and cancel pending/confirmed bookings. Check-out requires a zero balance. Guests can cancel their own pending/confirmed bookings. Changes to dates/facility/guest count are allowed only while pending, with the quoted nightly rate retained unless the facility changes.
4. Seed inventory is clearly fictional demonstration data, not MNB's actual inventory or prices. Currency is PHP. No tax, discount, deposit policy or cancellation fee is invented.
5. Payments are positive manual ledger entries up to the current balance. Admin can void with a reason; no destructive edits and no simulated online charge. A checked-out booking with a balance after a void can receive a corrective payment without reopening its stay. Cancelled paid bookings retain the ledger and flag manual settlement; this system does not process refunds.
6. Facility maintenance state blocks new bookings. Existing active reservations must be resolved before changing availability, archiving, or reducing capacity below an active booking. Maintenance tickets do not silently change facility availability.
7. Guests register locally. Reset tokens are handed to a verified person by an administrator; no email delivery. Administrators without access use a local CLI recovery command. The last active administrator cannot be disabled/demoted.
8. Temperature thresholds and stale time are configuration, not medically/safety certified alarms. Device timestamps are recorded as server receipt time; sample IDs prevent retries from duplicating readings. Default stale period is five minutes, adjustable.
9. Thirty-minute idle expiry, five failed logins per account/IP pair per fifteen minutes, and a twelve-character password minimum are implementation defaults. Production capacity, uptime, retention and recovery targets remain unspecified.

## Data design
Roles and permissions map to users; guest users optionally link to guest records. Facilities have many reservations, maintenance tickets and devices. Reservations link guests to facilities and have many payments. Devices have many readings. Users own notifications and may appear as actors in audit, payment and maintenance records. Auth attempts and reset tokens support local authentication. Archived master records use `archived_at`; transactional histories remain intact.

## Development sequence
1. Analyze source and document scope, unknowns and assumptions (this document).
2. Define role matrix, booking/payment/maintenance state transitions and acceptance cases.
3. Design normalized InnoDB schema, ERD and reusable PHP architecture.
4. Produce importable database.sql with roles, permissions and clearly labeled sample inventory.
5. Implement PDO, authentication, authorization, validation and transactional services.
6. Build responsive Bootstrap layouts, dashboard and all role-specific modules.
7. Connect modal forms, tables, filters, reports and charts through Fetch APIs.
8. Verify CSRF, sessions, ownership, archive safeguards and device authentication.
9. Execute syntax, database, HTTP workflow and security tests; record actual evidence and limits.
10. Deliver technical/user guides, source, SQL and sanitized ZIP.

## Risks and success criteria
Unknown client processes are the main scope risk. Other risks are booking races, privacy exposure, sensor inaccuracy, Wi-Fi outages, limited schedule and user adoption. Mitigations are explicit assumptions, locked transactional checks, RBAC, sensor timestamps, honest unavailable states and role-based instructions.

Acceptance requires accurate reservation availability/history/balances, restricted guest and role access, persisted maintenance transitions, received temperature readings, matching dashboard/report totals, and successful local setup. Hardware accuracy, production load, resort acceptance and quantified business benefits require external validation and are not implied by software tests.
