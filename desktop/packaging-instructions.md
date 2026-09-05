# Packaging GMT Hotel Management System as a Windows Desktop App

This app is a standard PHP + MySQL application under `public/` as the web root.
To ship it as `GMT-Hotel-Management-System.exe` with no browser/localhost step for
the end user, wrap it with **PHP Desktop** (Chromium + PHP embedded).

## Steps

1. **Download PHP Desktop** (chrome-based build) from the official project releases.
   It ships `php-desktop.exe`, a `php` runtime folder, and a `www` folder.

2. **Copy this project into `www/`**
   - Point PHP Desktop's document root at this project's `public/` folder
     (edit `settings.json` → `"web_folder": "www/public"`), so `config/`,
     `includes/`, and `database/` stay outside the served web root.

3. **Bundle MySQL**
   - Easiest: install **MySQL locally** and set `config/database.php` credentials
     to match, then instruct the installer to run `database/schema.sql` on first launch.
   - For a fully self-contained build, bundle **MariaDB portable** and start it
     alongside `php-desktop.exe` via a small launcher script (`start.bat`) that:
     1. Starts `mariadb/bin/mysqld.exe --datadir=data`
     2. Waits for the socket to be ready
     3. Launches `php-desktop.exe`

4. **Rename & icon**
   - Rename `php-desktop.exe` → `GMT-Hotel-Management-System.exe`
   - Replace `php-desktop.ico` with the GMT brand mark

5. **First-run setup**
   - On first launch, if `settings` table is empty, redirect to a `/install.php`
     wizard that: creates the database, imports `schema.sql`, and prompts the
     admin to set their own password (replacing the placeholder hash in the seed).

6. **Build the installer**
   - Use Inno Setup or NSIS to package the PHP Desktop folder into a single
     Windows installer that places everything in `Program Files\GMT Hotel\`
     and creates a desktop shortcut to the renamed `.exe`.

## Required PHP extensions
`pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd` (for image handling),
`fileinfo`. PHP Desktop builds typically bundle these — verify with
`phpinfo()` before shipping.

## Libraries to add for exports (Section 20 of the spec)
- Excel: `phpoffice/phpspreadsheet`
- PDF: `dompdf/dompdf` or `mpdf/mpdf`
- CSV: native `fputcsv()` is sufficient, no library needed

Install via Composer during the build step (not at runtime on the client
machine) and vendor the `vendor/` folder into the packaged app.
