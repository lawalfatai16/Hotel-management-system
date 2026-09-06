# GMT Hotel and Events Centre

Hotel and events centre management system built for GMT Hotel and Events
Centre, Iwo, Osun State. PHP and MySQL on the backend, styled to the
hotel's own dark brown, cream and white identity, and packaged to run as a
Windows desktop application rather than a website staff have to browse to.

## About the system

This covers the full day to day operation of the hotel: reservations,
guests, rooms, check in and check out, housekeeping, payments, invoicing,
events, the restaurant, staff records, expenses, reporting, and system
administration. Every module shares the same layout, the same permission
system, and the same design language, so it should feel like one product
rather than a set of bolted together tools.

## Features

**Dashboard**
KPI cards for revenue, occupancy, check ins and outs, pending reservations,
guests and events, backed by live charts for the last seven days of
revenue, booking status, revenue by source and current occupancy.

**Rooms and room types**
Visual room grid with photos, status badges, search and filtering by
floor, type and status. Room types hold pricing, capacity and amenities.
Deleting a room type in use by existing rooms is blocked.

**Guests**
Searchable guest directory with a full profile page per guest covering
current and past reservations, total spend, outstanding balance and
payment history.

**Reservations**
Table and calendar views, guest and room search as you type, live total
calculation, and server side overlap checking so two guests can never be
booked into the same room for overlapping dates. Status moves from
pending to confirmed to checked in to checked out, or to cancelled, and
room status updates automatically at each step.

**Check in and check out**
Check in shows arrivals due today or overdue with a guest and room
verification panel before confirming. Check out builds the final invoice
live, including automatic extra night detection if the guest stays past
the booked date and any restaurant charges billed to the room, then
generates the invoice and moves the room to cleaning.

**Housekeeping**
Room status summary, a rooms needing attention panel with one click
status changes, and a four column task board from pending through to
inspected, with staff assignment and priority.

**Payments and invoices**
Payments can be recorded against a reservation or an event, with editing
and deletion supported and the linked balance always recalculated from
the underlying records rather than adjusted by hand. Invoices are
generated automatically at check out and can be exported as CSV, Excel
or PDF, or sent to the guest on WhatsApp.

**Events**
Table and calendar views for weddings, conferences and other bookings,
with package selection, deposit and balance tracking, and a status
workflow from inquiry to confirmed to completed.

**Restaurant**
A point of sale screen for dine in, room charge and takeaway orders, an
order board, and menu and table management. Room charge orders are tied
to the guest's reservation and appear automatically on their invoice at
check out.

**Staff and expenses**
Staff records with department, position and employment status, and
categorised expense tracking with a monthly breakdown by category.

**Reports and analytics**
One reporting screen covering revenue, occupancy, reservations,
cancellations, check ins and outs, guests, payments, outstanding
balances, expenses, events, restaurant sales, staff activity and room
performance, each with a date range and export to print, CSV or Excel.
Analytics is a separate fixed thirty day view of the same underlying
data for a quicker read.

**Notifications and audit log**
The notification bell shows real alerts, arrivals due, checkouts due,
outstanding balances, rooms under maintenance and events coming up,
alongside a record of payments and new bookings as they happen. Every
sensitive action in the system is written to an audit log with a
dedicated viewer for administrators.

**Backup and restore**
Full database backup and restore built directly into PHP with no
dependency on the mysqldump binary, so it works the same regardless of
hosting setup. Restricted to the super admin role.

**Settings**
Hotel name, logo, contact details, currency, tax rate, document prefixes
and date formats are all configurable rather than hard coded, and used
consistently across the whole application.

## Roles and permissions

Access is controlled through a single permission file at
`includes/permissions.php`, which maps each role to the modules it can
reach. The super admin role always has full access, checked first before
anything else, so it is never affected by changes made to other roles.
Every other role, manager, receptionist, accountant, housekeeping, event
manager and restaurant staff, is scoped to what that job actually needs.
A receptionist, for example, has no route into settings or backup and
restore.

This is enforced twice. The sidebar only shows links a role is allowed
to open, and the underlying API rejects the request even if it is called
directly, so restricting access is not just a matter of hiding a button.
Any blocked attempt is recorded in the audit log. To give a role access
to a module later, add the module's key to that role's list in
`includes/permissions.php`. Nothing else needs to change.

## Requirements

- PHP 8.1+ with `pdo_mysql`, `mbstring`, `openssl`
- MySQL 8+ (or MariaDB 10.6+)
- A modern Chromium-based runtime for the desktop shell (see
  `desktop/packaging-instructions.md`)

## Setup

1. Create the database and import the schema.

   ```
   mysql -u root -p < database/schema.sql
   ```

2. Open `config/config.php` and set the database credentials for your
   environment.

3. The seeded admin account in the schema does not ship with a working
   password. Generate one before logging in.

   ```
   php -r "echo password_hash('YourNewPassword!', PASSWORD_DEFAULT);"
   ```

   Then update the admin row with the result.

   ```sql
   UPDATE users SET password_hash = 'paste the generated hash here' WHERE username = 'admin';
   ```

4. Serve the `public` folder as the web root.

   ```
   php -S localhost:8000 -t public
   ```

5. Visit `http://localhost:8000` and log in.

If you are running this under XAMPP or another local server, point the
document root at the `public` folder specifically rather than the
project folder as a whole. Keeping `config`, `database` and `includes`
outside the web root is a deliberate security choice, not an accident.
The static assets used to live outside `public` and were reached with a
relative path, which only worked because the whole project folder
happened to be exposed in one particular setup. That has since been
corrected: `assets` now lives inside `public/assets`, so the application
works correctly whichever way the document root is configured.

## Notes on exports, PDF and WhatsApp

CSV export uses PHP's native fputcsv and needs no library. Excel export
serves an HTML table with the correct content type and an xls extension,
which Excel opens as a genuine workbook rather than a plain CSV renamed
to look like one. For a true xlsx binary, PhpSpreadsheet can be added
during a build step with internet access.

PDF export is available in two forms. Invoices and check in confirmations
have a real, dependency free PDF writer built for this project
(`includes/SimplePdf.php`), used for downloads and for the WhatsApp send
feature. A styled print view is also available on invoices for anyone
who prefers printing or saving through the browser. If a more capable
PDF library such as Dompdf becomes available later, it can replace the
built in writer without changing how the rest of the application calls
it.

Sending an invoice on WhatsApp downloads the PDF and opens WhatsApp with
the guest's number and a message already filled in. WhatsApp's own link
format can prefill text but cannot attach a file automatically from a
web page, so the file has to be attached manually in the chat that
opens. Fully automated sending would require WhatsApp's Business API,
which needs a verified business number and API credentials from Meta
and is a separate setup outside the scope of this application. Phone
numbers are converted from local to international format using the
country code set under Settings, which defaults to 234 for Nigeria and
is not hard coded.

## Desktop packaging

Instructions for wrapping the application with PHP Desktop, bundling
MySQL or MariaDB, and building a Windows installer are in
`desktop/packaging-instructions.md`. This has not yet been built or
tested as a finished executable.

## Known limitations

The application has been reviewed carefully but has not been tested
against a live MySQL database end to end. Every workflow should be run
through manually, including exports and role restrictions, before it is
used for real bookings.

Static assets and third party libraries, Tailwind, Chart.js, Lucide and
Motion, are currently loaded from a CDN at runtime. For a fully offline
desktop build these should be downloaded and served locally instead.

Room and menu item images are uploaded and stored on disk rather than
through a service such as Cloudinary. This is sufficient for a single
server deployment but worth revisiting if the system is later run across
multiple servers or needs a content delivery network.

## Roadmap

The core system is functionally complete across reservations, guests,
check in and check out, housekeeping, payments, invoicing, events, the
restaurant, staff, expenses, reporting, notifications, the audit log,
backup and restore, and settings. Remaining work is mostly around
production hardening: full end to end testing, finishing the desktop
build, moving third party libraries to local hosting, and upgrading the
export and PDF handling to use dedicated libraries where a build
pipeline is available to support them.
