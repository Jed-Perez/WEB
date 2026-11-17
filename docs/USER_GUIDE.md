# Resort360 user guide

## Everyone

Sign in with your email and password. Use the profile menu for contact details, password changes and sign out. Idle sessions expire after 30 minutes. Five failed logins for the same email/IP within 15 minutes temporarily block that pair. Reception can coordinate identity-verified password recovery. A reset code is never emailed automatically.

The sidebar lists only your authorized modules. On a phone, use the menu button at the top left. Tables provide search, status/date filters where relevant, column sorting and page controls. The eye button opens record details; the pencil opens an edit modal. Required fields are marked with an asterisk. Validation errors appear inside the modal. Notifications appear under the bell; mark all read when reviewed.

## Guests

1. Open the landing page and choose **Sign in to reserve**, or create a guest account using **Create your guest account**.
2. After signing in, use **New reservation** in the workspace. Enter arrival, departure and guest count, check availability and select a room or cottage.
3. Review the quote and save the request. It starts as **Pending** and must be confirmed by reception.
4. Use **My reservations** in the workspace to view status, details, recorded payments and the remaining balance. Edit pending requests or cancel pending/confirmed bookings there.
5. Arrange payment directly with the resort. Cancellation retains recorded payments; contact reception about settlement.
6. Use **My profile** for contact details and password settings. The landing page introduces the resort; reservation processing remains in the signed-in workspace.

## Reception / reservation staff

1. Add a walk-in guest in **Guests**, or use an existing guest. Avoid collecting unnecessary sensitive information.
2. Use **Reservations → New reservation**, choose the guest, dates, guest count and unit. Check availability and save.
3. Use the Status menu to confirm/reject a pending request. Confirmed bookings can be checked in during their reserved stay. A previous occupant must check out before the next occupant checks in.
4. Record money actually received in **Payment records**. Select the reservation, method, amount and receipt/reference. Overpayments and nonpositive amounts are rejected.
5. View reservation details to reconcile the ledger. Only an administrator can void an incorrect payment, with a reason.
6. Check out the guest after the balance reaches zero. Booking and payment history remains accessible.
7. Edit guest contact details or inspect booking history. Archive unused guest records when they have no active bookings; select Archived records to restore them.

Reception has no account-administration, audit, sensor or management-report access. It can read room/cottage details but cannot change the inventory.

## Resort staff

Review facility details and occupancy. In **Maintenance**, report an issue, choose a facility, set priority and optionally assign an active staff member/admin. Update work to In progress and then Completed, recording the resolution. Tickets can also be cancelled. Editing a completed ticket to Open reopens the work and clears its completion timestamp.

In **Facility monitoring**, review the latest temperature and last received time. Online means a recent reading was received, not that the sensor has been independently calibrated. Stale and No data identify missing updates. An out-of-range notice uses the configured thresholds. **Temperature history** charts hourly averages across the selected dates, independently of table pagination. The default range is seven days; select up to 31 days. Each device has its own series even when names match. Hover a point to inspect minimum, maximum and sample count; missing hours remain gaps.

## Administrator / owner

You have all operational permissions plus these controls:

The **Overview** refreshes every 30 seconds while visible and no modal is open. Its timestamp uses Asia/Manila; a warning identifies failed refreshes. Use the refresh button to update immediately. Summary cards open the related records. **Today's operations** lists arrivals, departures, overdue check-outs and outstanding balances, with up to five bookings in each queue. Click a queue heading for the full filtered list. Check-in, check-out and payment buttons enforce the same server rules as the reservation screen. The occupancy chart separates occupied, vacant and maintenance units; vacant units still require a date availability check.

Booking forms recheck availability when dates or group size change. Editing a pending reservation includes its own held room, subject to other bookings. Payment forms show the selected booking's total, valid payments and remaining balance. Notifications can be marked read individually; notifications containing a booking reference link to that booking's filtered list.

- **Rooms & cottages:** create/edit rooms, cottages and non-bookable facilities. Set capacity, nightly rate, description and availability. Resolve active reservations before blocking or archiving a unit. Resolve open maintenance before archiving it. Existing bookings retain their quoted rate.
- **Team & accounts:** create users, assign one of four roles, activate/deactivate accounts and issue reset codes. A role or activation change invalidates prior sessions. The last active administrator cannot be removed. Initial passwords are entered into a password field; ask the user to change theirs after signing in.
- **Payment records:** void an incorrect record with a reason. Voiding keeps the original entry and changes the balance. If a void creates a balance on a checked-out booking, that booking returns to the payment selector so you can record a corrective receipt without changing its stay status. Review the notification and reconcile the ledger.
- **Facility monitoring:** create devices and save the one-time key privately. Configure a facility, valid temperature range and stale interval. Rotate a key if lost/compromised, then update the ESP32. Deactivation prevents ingestion.
- **Reports:** select reservations, payments, maintenance, readings or facilities; set dates/search; generate the preview. Dates apply to arrival for reservations and creation/receipt for other reports. The preview shows up to 100 rows, with server-calculated totals for all matching records. Payment totals exclude voids; reservation totals include all matching booking statuses. **Print all results** loads every matching row, up to 10,000, before opening print. CSV, XLS and PDF use the same applied filters. Changing filters disables print/export until you generate again. Narrow filters for larger datasets. XLS is XML SpreadsheetML and may produce Excel's format/extension confirmation; CSV is an alternative.
- **Activity log:** search the actor, operation, entity and details. Audit entries cannot be edited or deleted through the application.

## Common situations

- **Date conflict:** another pending/confirmed/checked-in booking holds the space. Choose different dates or another unit. Adjacent date ranges are allowed.
- **Pending requests never expire:** reception must confirm, reject or cancel them. This is an explicit assumption pending client policy.
- **Paid booking cancelled/rejected:** the ledger is retained. An internal notification requests manual settlement review. The system does not issue refunds.
- **Maintenance ticket vs blocked space:** work tickets are separate from availability. An administrator blocks the space explicitly after resolving active bookings.
- **No devices/readings:** configure actual hardware. The application does not invent temperatures.
- **Network interruption:** read the message and check the table before resubmitting. Financial records should be checked for duplicates if the response is lost.
- **Lost device key:** rotate it. Only its hash is stored, so the original cannot be recovered.
