# Admin improvements — 16 November 2026

Implemented locally in the existing PHP/XAMPP application. No schema migration or live-data changes were required.

- Reports ignore outdated responses and keep preview/export filters aligned. Summaries cover all matching records, including those beyond the 100-row preview. Printing loads up to 10,000 matching rows. Invalid and reversed date ranges are rejected server-side.
- Temperature charts group by device ID and aggregate all matching samples by hour over a date range of up to 31 days. Pagination does not change the trend. Missing hours are gaps; tooltips show hourly minimum, maximum and sample count.
- The dashboard returns the full unread notification count, refreshes every 30 seconds, shows its update time and reports connection failures. Occupancy separates occupied, vacant and maintenance units.
- Today's operations exposes arrival, departure, overdue check-out and outstanding-balance queues, with direct check-in/check-out/payment actions. Summary cards and queue headings open filtered record lists.
- Pending reservation availability checks exclude only an authorized current reservation. Booking forms automatically recheck changed dates/group sizes. Payment forms show the selected booking balance.
- Notifications support individual marking and booking-reference links. Table pagination clamps to valid pages after records are removed from a filter.

Verification: 153 existing integration/browser checks passed; 49 additional admin checks passed; three report race regression checks passed. Changed PHP and JavaScript files passed syntax checks. Tests used the isolated database at port 3307 and Apache at port 8088. Desktop and mobile Chrome checks reported no JavaScript exceptions or mobile page overflow.

The reservation calendar proposed during review is a separate future enhancement; this update implements the accuracy fixes and daily-operation interactions. Sensor calibration and real resort operating policies still require operational validation.
