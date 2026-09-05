<<<<<<< HEAD
# GMT Hotel and Events Centre — Hotel Management System

A PHP/MySQL hotel management application, styled to the GMT dark-brown /
cream / white brand identity, built to be packaged as a Windows desktop app.

## What's built so far (Phase 1)

 **Full database schema** (`database/schema.sql`) covering every module in
  the spec: users/roles/permissions, staff, rooms & room types, guests,
  reservations, check-in/out, housekeeping, payments, invoices, events,
  restaurant/POS, expenses, notifications, audit logs, settings.
 **Authentication & security**: session-based auth, `password_hash()` /
  `password_verify()`, account lockout after failed attempts, CSRF tokens on
  every write, idle session timeout, audit logging on every sensitive action.
 **Branded login screen**: boot/loading sequence, Ken-Burns rotating hero,
  glass login card, show/hide password, remember-me, system status indicator.
 **Dashboard**: KPI cards (revenue, occupancy, check-ins/outs, pending
  reservations, guests, events) and Chart.js analytics (7-day revenue,
  booking statistics, revenue by source, occupancy donut) — all reading live
  from the database.
 **Rooms module**: visual grid with status badges, instant search,
  floor/type/status filters, pagination, add/edit modal, guarded delete
  (blocks deleting occupied/reserved rooms), full validation, audit logging
  — backed by `public/api/rooms.php`.
 **Guests module**: searchable directory with stays/spend columns,
  add/edit modal, guarded delete (blocks guests with active/upcoming
  reservations) — backed by `public/api/guests.php`. Each guest has a full
  **profile page** (`guest_profile.php`) showing current reservation,
  full stay history, total spending, outstanding balance, and payment
  history.
 **Reservations module**: table view *and* month calendar view, guest/room
  typeahead search, live total calculation (`Room Rate × Nights + Tax −
  Discount`), **double-booking prevention** (server-side overlap check on
  create/edit), status workflow (pending → confirmed → checked_in →
  checked_out, plus cancel), automatic room-status sync, CSV export, and
  print — backed by `public/api/reservations.php` and
  `public/api/reservations_export.php`.

 **Check-In workflow** (`checkin.php`): shows reservations due for arrival
  today or overdue, search by code/guest/room, a verification panel (guest,
  ID on file, room, balance due), one-click confirm that records the
  check-in, flips the room to occupied, and prints a check-in slip.
 **Check-Out workflow** (`checkout.php`): shows currently checked-in
  guests, builds a **live final invoice** on open — room charges, automatic
  extra-night detection if today is past the planned check-out date,
  restaurant charges pulled from any orders tied to the reservation, tax,
  discount, amount already paid, and outstanding balance — then on confirm
  it records the check-out, generates a real invoice + invoice line items,
  flips the room to "cleaning", auto-creates a housekeeping task for it, and
  prints the invoice.
 **Housekeeping board** (`housekeeping.php`): room-status summary strip,
  a "rooms needing attention" panel with one-click Mark Available /
  Maintenance, and a four-column task board (Pending → In Progress →
  Completed → Inspected) with staff assignment and priority — advancing a
  task through the board keeps room status in sync automatically.

The database now ships with **realistic seed data** (4 room types, 10 rooms
across 4 floors, 6 staff members, 3 guests — real-sounding Nigerian names,
no "John Doe" placeholders) so every module above is demoable immediately
after import.

 **Payments** (`payments.php`): record a payment against a reservation
  *or* an event via search-as-you-type, auto-suggests the outstanding
  balance as the amount, and keeps `reservations.payment_status`
  (unpaid/partial/paid) and `events.balance` in sync automatically.
 **Invoices** (`invoices.php`): every invoice generated at check-out,
  searchable/filterable, with **CSV export** (native `fputcsv`) and
  **Excel export** (a real `.xls` file Excel opens natively — see note
  below), plus void.
 **Events** (`events.php`): table + calendar view like Reservations,
  package selection that prefills price, live deposit/balance calculation,
  and a status workflow (inquiry → confirmed → completed, or cancel).
 **Room Types** (`room_types.php`): the module that was missing before —
  add/edit/delete room categories (name, base price, capacity, amenities),
  guarded so a type in use by existing rooms can't be deleted.

### Role-based access control (Section 17 of the spec)
Every page and every API endpoint now goes through
`Auth::requireModuleAccess('module_key')` instead of a bare login check.
The permission matrix lives in **`includes/permissions.php`** — one file,
role → allowed modules:

 **Super Admin has `'*'`** unconditional access to every module,
  everywhere, forever. This is checked first and short-circuits everything
  else, so the admin account is never blocked by anything below it.
  Every other role (Manager, Receptionist, Accountant, Housekeeping, Event
  Manager, Restaurant Staff) is scoped to what that job actually needs 
  e.g. Housekeeping can reach the Housekeeping board and Rooms, but not
  Payments or Invoices.
 The **sidebar hides links a role can't open** (`includes/sidebar.php`
  filters by `Auth::moduleAllowed()`), and the **API rejects the request
  server-side** even if someone hits the endpoint directly. it's not just
  a hidden button, both layers enforce it.
 Blocked attempts are written to the audit log.

To grant a role a new module later, add its key to that role's array in
`includes/permissions.php` — nothing else needs to change. Modules not yet
built (Staff, Expenses, Restaurant, Reports, Settings, etc.) are already
listed in the matrix for the roles that should eventually have them; their
sidebar links will 404 until those pages exist, same as before.

### A note on exports (Section 20 of the spec)
 **CSV** is genuine, native PHP (`fputcsv`)  no library needed.
 **"Excel"** is a real technique, not a renamed CSV: it serves an HTML
  table with `Content-Type: application/vnd.ms-excel` and a `.xls`
  filename, which Excel opens natively as a workbook. For a true `.xlsx`
  binary, swap in PhpSpreadsheet at build time (one function change).
 **PDF** is currently "Print / Save as PDF" from a dedicated print-styled
  view (`invoice_print.php`, plus the check-in/check-out slips)  genuinely
  usable today. I did **not** hand-roll binary PDF generation, because I
  have no way to execute PHP in this environment to verify byte level
  output, and a subtly malformed PDF is a worse failure mode than an
  honest "print to PDF" button. Add Dompdf via Composer once you have a
  real PHP environment to test against see `desktop/packaging-instructions.md`.

Every other module in the spec (restaurant, staff, expenses, reports
centre, notifications, audit log viewer, backup/restore, settings) follows
the **same pattern**: a page under `public/`, a JSON API under
`public/api/`, and shared partials from `includes/`.

## Animation

The spec needs Framer Motion which is React-only and can't run in this
PHP/vanilla-JS stack. The fix: **[Motion](https://motion.dev)**, the
framework-agnostic vanilla-JS engine the Framer Motion team spun out of the
same project (same spring physics, no React or build step required).

`assets/js/app.js` loads it lazily via dynamic `import()` and exposes:
 `GMT.entrance(selector)` — staggered fade/slide-in for any list of
  elements (KPI cards, room grid, table rows) — call it right after you
  render dynamic content
 `GMT.openModal(id)` / `GMT.closeModal(id)`  spring scale/fade for every
  modal, already wired into Rooms, Guests, and Reservations
 `GMT.toast(message, type)` — animated slide-in/out notifications

Everything degrades to instant show/hide if Motion fails to load, so a slow
or offline first load never blocks the UI. **For the packaged desktop app**,
vendor `motion.js` locally instead of pulling from the CDN at runtime (see
`desktop/packaging-instructions.md`) same as Tailwind/Chart.js/Lucide.

When you build the next module, reuse `GMT.entrance()` / `GMT.openModal()`
rather than adding new CSS-only animations, so the motion language stays
consistent across the app.

### Phase 4 — Restaurant/POS, Staff, Expenses

 **Restaurant/POS** (`restaurant.php`): a real POS screen pick a dine-in
  table, a room-charge reservation, or takeaway, add menu items, see the
  running total (tax auto-applied from Settings), submit. Separate tabs
  manage the Orders board (open → served → paid/cancelled, syncing table
  status automatically), the Menu (categories + items), and Tables. Room
  charges land on the same `reservation_id` that Check-Out already reads
  when building the final invoice order it here, it shows up there.
 **Staff** (`staff.php`): HR records  department, position, contact,
  optional system role tag, employment status. Deleting is blocked if the
  person has task or order history (use "inactive" status instead).
 **Expenses** (`expenses.php`): categorized expense tracking with a
  live category-breakdown chart and month total, full CRUD, CSV-ready.

### Phase 5 — Reports, Notifications, Audit, Backup, Settings

 **Reports Centre** (`reports.php` + `includes/report_queries.php`): one
  screen, 15 report types (Revenue, Yearly Revenue, Occupancy,
  Reservations, Cancelled Reservations, Check-In, Check-Out, Guests,
  Payments, Outstanding Balances, Expenses, Events, Restaurant Sales,
  Staff Activity, Room Performance), each with a date range, chart where
  relevant, and Print/CSV/Excel export. **Note on consolidation**: the
  original spec lists "Daily sales", "Daily revenue", "Weekly revenue",
  and "Monthly revenue" as separate items these are the same underlying
  query at different date-range widths, so they're one flexible "Revenue
  Report" you set a range on, rather than three/four near-identical pages.
  Every report type shares one query function (`buildReport()`), so the
  on-screen view, CSV, and Excel export can never drift out of sync with
  each other.
 **Analytics** (`analytics.php`): a distinct page from Reports  fixed
  30-day trend view (revenue, occupancy, revenue by room type, top 5
  guests by spend) for an at-a-glance read, vs. Reports' arbitrary-range
  deep dive. 
  **Notifications**: real data now, not a static "3" badge. The topbar
  bell merges **stored** notifications (fired via a new `notify()` helper
  at real action points — new reservation, payment received) with **live
  computed** alerts (arrivals due, checkouts due, outstanding balances,
  rooms in maintenance, events in the next 3 days) that are never stored
  and so never go stale or duplicate.
 **Audit Log viewer** (`audit_logs.php`): read-only, searchable by
  action/user, filterable by module and date range  for the audit trail
  every module has been writing to since Phase 1.
 **Backup & Restore** (`backup.php`): pure-PHP dump/restore, no
  `mysqldump` binary dependency (portable across setups). Backup streams
  a full `.sql` file (structure + data, every table). Restore accepts an
  uploaded `.sql` file, runs it inside a transaction with a quote-aware
  statement splitter (so semicolons inside text fields, like expense
  notes, don't cause false splits), and rolls back entirely on any error
   you never end up with a half-restored database. Both actions are
  logged and shown as history on the page. **Super Admin only.**
 **Settings** (`settings.php`): hotel name, logo upload, address,
  phone, email, currency code/symbol, tax rate, invoice/reservation
  prefixes, date/time format — writes straight to the `settings` table
  every other module already reads from. **Super Admin only.**

## Fixes and additions since Phase 5

**Fatal error sweep (PDOException: Invalid parameter number)** the same
bug class as the original login fix: PDO's native prepared statements
(`EMULATE_PREPARES => false`) don't allow reusing one named placeholder
twice in a single query. I did a full-codebase sweep (not just the
reported Analytics page) and found **five** occurrences across
`analytics.php`, `report_queries.php` (Occupancy Report), and
`api/settings.php` / `api/staff.php`. All five are fixed, and the sweep
script itself is now something I'll re-run before considering any query
work "done."

**Room photos**  rooms have a real image now. Upload a photo in the
Add/Edit Room modal (`public/api/upload_image.php`, a small reusable
endpoint) and it shows on the room grid card instead of a placeholder
icon closer to the "beautiful visual room grid" the spec asked for.

**Asset folder restructure — read this if you're re-deploying.** The
shared `assets/css` and `assets/js` folder used to sit *next to*
`public/`, reached via `../assets/...` from every page. That only works
if your web server exposes the *entire* project folder  which
contradicts the security setup documented elsewhere in this README
(`config/`, `database/`, `includes/` should stay **outside** the web
root). It happened to work in the exact XAMPP setup being tested against,
but would silently break CSS/JS the moment someone points the document
root at `public/` only, as recommended. **Fixed**: `assets/` now lives
inside `public/assets/`, and every reference is a plain `assets/...` path
with no `../`. This also matches where uploaded images are stored, so
everything under `public/` is now self-contained and correctly served
whether the web root is the whole project folder or just `public/`.

## Payments edit/delete, real PDFs, and WhatsApp send-assist

 **Payments** now has Edit and Delete on every row — no more going into
  the database directly. Editing lets you fix amount/method/date/
  reference/notes; the reservation or event it's attached to isn't
  editable (delete and re-record if it was linked to the wrong booking).
  Both actions correctly recalculate the reservation's payment status or
  event balance afterward — I also fixed a design smell while I was in
  there: event balances used to be *decremented* on each payment, which
  meant editing or deleting a payment could never correct it. They're now
  *recomputed from scratch* every time (`price - deposit - SUM(payments)`),
  same as reservations already did — much harder to get into a bad state.

- **Real PDF generation** (`includes/SimplePdf.php`) — a small,
  dependency-free PDF writer (no Composer package). It's used for the
  Invoice PDF (`api/invoice_pdf.php`) and a new Check-In Confirmation PDF
  (`api/checkin_pdf.php`). This is a genuine PDF binary, not a
  print-to-PDF view — but see the caveat in the main "honest stand-ins"
  section above: I can't execute PHP to test this byte-for-byte, so
  double-check a downloaded PDF actually opens cleanly before relying on it.

## Requirements

 PHP 8.1+ with `pdo_mysql`, `mbstring`, `openssl`
 MySQL 8+ (or MariaDB 10.6+)
 A modern Chromium-based runtime for the desktop shell (see
  `desktop/packaging-instructions.md`)

## Local setup

1. Create the database and import the schema:
   ```
   mysql -u root -p < database/schema.sql
   ```
2. Copy `config/config.php` and set your local `DB_USER` / `DB_PASS`.
3. On first run, generate a real password hash for the seeded admin account
   (the placeholder in `schema.sql` is not a valid hash):
   ```php
   php -r "echo password_hash('YourNewPassword!', PASSWORD_DEFAULT);"
   ```
   then `UPDATE users SET password_hash = '...' WHERE username = 'admin';`
4. Serve the `public/` folder:
   ```
   php -S localhost:8000 -t public
   ```
5. Visit `http://localhost:8000` → you'll land on the login screen.

## Desktop packaging

See `desktop/packaging-instructions.md` for wrapping this into
`GMT-Hotel-Management-System.exe` with PHP Desktop, bundling MySQL/MariaDB,
and building a Windows installer.

## Roadmap

All phases from the original spec are now built:

1. ~~Reservations + Guests~~ ✅
2. ~~Check-in / Check-out + Housekeeping~~ ✅
3. ~~Payments + Invoices + Events~~ ✅
4. ~~Restaurant/POS + Staff + Expenses~~ ✅
5. ~~Reports centre + Notifications + Audit log viewer + Backup & Restore + Settings~~ ✅

Everything is guarded by the RBAC layer in `includes/permissions.php`. What's
genuinely left before this is production-ready:


 **Desktop packaging** — wrap with PHP Desktop per
  `desktop/packaging-instructions.md`; not yet built or tested as a `.exe`.
 **Vendor the CDN dependencies locally** (Tailwind, Chart.js, Lucide,
  Motion) for a fully offline desktop build.
 **Swap the honest stand-ins for the real thing when you have a build
  step with internet access**: PhpSpreadsheet for true `.xlsx` (current
  Excel export is a real, Excel-openable `.xls` via HTML table, not a
  renamed CSV — but not a native binary), Dompdf for server-generated PDF
  binaries (current PDF path is a styled Print view, genuinely usable via
  "Print to PDF").
 **Cloudinary image upload** in the room/listing flows — currently only
  the Settings logo has file upload; room and menu item images are
  path-only fields.
=======
# Hotel-management-system
A complete management system for managing guests, rooms, bookings, payments, staff and reports
>>>>>>> 8b150308e37b9675d59c5f0be1411dc642048962
