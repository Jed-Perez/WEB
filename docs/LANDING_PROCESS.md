# Landing-page booking process

> Updated scope: landing-page booking, room listings and saved booking handoff have been removed at the user's request. Reservations run in the signed-in workspace. The earlier landing-process tests/results below document the previous implementation and are no longer current acceptance checks.

Updated locally on 16 November 2026. No schema migration or live-data updates were required.

Guests now follow this path: choose dates and group size, inspect available spaces and stay totals, review a selected space, sign in or register if necessary, return to the landing-page review, and explicitly submit a pending reservation. The receipt includes the booking reference and actual saved total.

The landing page also shows a signed-in guest's paginated reservations, current status, payment totals, outstanding balance, payment ledger and cancellation controls. Pending-booking edits and profile settings link to the existing guest workspace. Reception and admin workflows remain in their workspace.

Accuracy protections:

- Date/group changes invalidate old results immediately. Outdated availability responses cannot make old selections bookable again.
- Opening a review checks current availability. Submitting checks capacity, conflicts and the reviewed nightly rate on the server.
- Repeated submit clicks are disabled. Existing transaction locks continue to protect against overlapping bookings across sessions.
- Drafts preserve only booking selection and notes through authentication. Return destinations are fixed local routes. Success/sign-out clear the draft; profile navigation does not consume it.
- Booking status is explicitly pending, and payment remains arranged with reception. Cancellation retains any payment history and explains settlement.

Verification: 153 existing integration/browser checks, 30 updated landing checks and 24 focused process checks passed. The latter covers mobile review, changed prices, delayed responses, login handoff, ledger display, cancellation and draft cleanup. Changed PHP and JavaScript files passed syntax checks. Tests used isolated ports 3307/8088; checks of the normal XAMPP landing page were read-only.
