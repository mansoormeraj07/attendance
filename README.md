# Attendance / Shift Dashboard

cPanel PHP attendance and shift dashboard (no database, JSON storage).

## Install
1. Upload the files to your cPanel `public_html` (or a sub-folder).
2. Make sure the `data/` folder is writable (chmod 755/775). `data/.htaccess` blocks web access to it.
3. Open `login.php` and sign in.

## Notes
- `data/*.json` and `assets/logo.png` are runtime/customer files and are not tracked.
- Idle timeout: 5 minutes. Login lockout: 5 wrong attempts = 10 min.
- To show the update popup to everyone: Admin > Users > "Show to all users".
