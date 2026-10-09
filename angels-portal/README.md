# Angel's Attendance Portal (read-only Google Sheets connection)

This is a standalone PHP/XAMPP dashboard that reads the EXISTING Google Sheets tracker without changing Apps Script, attendance, payroll calculations, or history. It is NOT a complete two-way sync yet. No live account connection is possible until the owner supplies credentials and grants access.

## Installation
1. Back up your original Google Sheet: File > Make a copy. Back up the Apps Script code too.
2. Start Apache in XAMPP. Copy `angels-portal` to `C:\xampp\htdocs\angels-portal`.
3. In Google Cloud Console, create/select a project, enable **Google Sheets API**, create a **service account**, and download its JSON key. Keep the JSON key **outside `htdocs`** (e.g. `C:\private\angels-service-account.json`). Never send the key in chat, commit it to Git, or put it in the website folder. Limit access to the server account.
4. In your Google Sheet, click Share and add the service account `client_email` as **Viewer**. Do not enable public link sharing.
5. Copy `config/settings.example.php` to `config/settings.php`. Paste your spreadsheet ID (from `/spreadsheets/d/ID/edit` in the Sheet URL) and the full private JSON key path. Keep the real `settings.php` private.
6. Generate a password hash from a terminal with PHP: `C:\xampp\php\php.exe -r "echo password_hash('CHANGE_TO_A_LONG_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"` and paste the result into `admin_password_hash` in settings.php. Set `admin_username` as desired. Do not put the plain password in the PHP file.
7. Confirm PHP extensions `curl` and `openssl` are enabled in XAMPP's `php.ini`. Restart Apache.
8. Visit `http://localhost/angels-portal/login.php`, sign in, and click Refresh to fetch the current Google Sheets values.

## Important notes
- The spreadsheet is the source of truth. This dashboard reads the `ATTENDANCE TRACKER` sheet, B4:X200, with employee rows starting at row 8. If the original layout changes, update `lib/sheets.php`.
- Payroll is read as **formatted text**, not recalculated here. It may include Philippine peso symbols and commas.
- API writes do not trigger Google Apps Script simple `onEdit(e)` triggers. To safely edit attendance from PHP later, a carefully authenticated Apps Script endpoint must call the appropriate existing processing logic and be tested on a copy. Do not directly write status/time cells through the Sheets API and assume payroll will recalculate.
- Google service account keys and payroll information are sensitive. This demo is suitable for local use with a strong admin password; before public hosting add HTTPS, rate limiting, stronger authentication, and proper deployment security.
- If Google Sheets API reports 403, verify service account Viewer sharing, API enablement, and project configuration.
- This project does not require MySQL because it reads existing Google Sheets data. Your earlier PHP/MySQL app remains separate and untouched.

## Features
Admin login, forgot password (recovery key), dashboard, weekly tracker, employee directory and profile, settings. View-only: all edits stay in Google Sheets.

## Deploying from GitHub
GitHub Pages **cannot** run PHP. Use GitHub to store the code and a PHP host (shared hosting such as Hostinger, or a VPS) to run it.

1. Create a **private** repo and push this folder. `.gitignore` already excludes `config/settings.php`, `config/local.json`, and any service-account `*.json` key. Check `git status` before every commit.
2. On the host (PHP 8+, `curl` and `openssl` enabled, HTTPS on), upload or `git pull` the `angels-portal` folder.
3. Upload the service-account key **outside** the public web folder and note its absolute path.
4. Create `config/settings.php` from `config/settings.example.php` (spreadsheet ID, key path, admin hash). Make sure `config/` is writable by PHP so Settings can write `config/local.json`.
5. Confirm `config/.htaccess` is honored (Apache) or block `/config` in your server config (nginx).
6. Share the Google Sheet with the service account as **Viewer** and set general access to **Restricted**.
7. Generate a new recovery key for forgot-password: set `recovery_hash` in `config/local.json` to `password_hash('YOUR-KEY', PASSWORD_DEFAULT)`.
8. Change the default admin password, and delete unused service-account keys in Google Cloud.

If a key or password was ever committed, rotate it (new key, new password) - deleting the file from Git history is not enough.
