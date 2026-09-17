# The Event Phoenix (TEP) — Deployment handoff

Legacy LAMP stack: **PHP 8.2**, **MySQL**, **AngularJS 1.x**. Do not add Node.js, npm app builds, Angular 2+, Workbox, or webpack.

This guide is for the person who copies the `master` branch onto a live Apache host. Product walkthroughs for Miller are in **How to confirm** at the bottom.

## 1. What you are deploying

- GitHub branch: `master` on https://github.com/joemiller805-cloud/TheEventPhoenix
- Document root: the repository root (`index.php`, `common_functions.php`, `data_access/`, `sw.js`)
- GitHub default branch `main` is an older October marketing site. **Do not deploy `main` as the LAMP app.**

## 2. Secrets — never commit them

TEP reads credentials in this order (first defined constant wins):

1. `private/tep_config.php` **outside** the web root (existing production file). Paths tried from `common_functions.php`:
   - `{parent of htdocs}/private/tep_config.php`
   - `{grandparent}/private/tep_config.php`
2. `config/env.php` inside the web tree (gitignored). Copy from `config/env.example.php` and fill in live values, **or** skip this file and use Apache `SetEnv` instead.
3. Process environment / Apache `SetEnv` names listed below (`TEP_DB_HOST`, …).
4. Localhost-only XAMPP fallbacks (`localhost` / `root` / empty password / schema `tep_local`).

`config/env.example.php` is a template only. `config/.htaccess` denies HTTP to that folder. Still prefer `SetEnv` or `private/tep_config.php` so passwords are not on disk under the document root.

### Environment variable names

| Variable | Constant | Purpose |
| --- | --- | --- |
| `TEP_DB_HOST` | `DB_HOST` | MySQL host |
| `TEP_DB_PORT` | `DB_PORT` | MySQL port (default 3306) |
| `TEP_DB_NAME_DEV` | `DB_NAME_DEV` | Schema when the host is not `easy*` |
| `TEP_DB_NAME_PROD` | `DB_NAME_PROD` | Schema when the host starts with `easy` |
| `TEP_DB_USER_LOCAL` | `DB_USER_LOCAL` | User when `HTTP_HOST` is `localhost` |
| `TEP_DB_PASS_LOCAL` | `DB_PASS_LOCAL` | Password when `HTTP_HOST` is `localhost` |
| `TEP_DB_USER_PROD` | `DB_USER_PROD` | User on non-localhost |
| `TEP_DB_PASS` | `DB_PASS` | Password on non-localhost |
| `TEP_DB_CONNECT_TIMEOUT` | `DB_CONNECT_TIMEOUT` | mysqli connect timeout seconds |
| `TEP_ENC_KEY_RAW` | `TEP_ENC_KEY_RAW` | Base64 AES key for `encryptthis` / `decryptthis` |
| `TEP_LEGACY_SALT` | `LEGACY_SALT` | `crypt()` salt; **must match existing password hashes** |
| `TEP_SESSION_NAME` | `TEP_SESSION_NAME` | Session cookie name for `start_secure_session()` (not pages that call `session_start()` first) |
| `TEP_VAPID_PUBLIC_KEY` | `TEP_VAPID_PUBLIC_KEY` | Web Push public key |
| `TEP_BASE_URL` | `BASE_URL` | AngularJS `<base href>` (example: `/`) |
| `TEP_IS_PRODUCTION` | `TEP_IS_PRODUCTION` | `true` / `1` disables account-1000 autologin even on localhost |
| `TEP_LOCAL_DEV_AUTOLOGIN` | `TEP_LOCAL_DEV_AUTOLOGIN` | Local-only skip-login; ignored when `TEP_IS_PRODUCTION` is true |

Apache example (inside the VirtualHost):

```
SetEnv TEP_DB_HOST "127.0.0.1"
SetEnv TEP_DB_PORT "3306"
SetEnv TEP_DB_NAME_DEV "tep_live"
SetEnv TEP_DB_NAME_PROD "tep_live"
SetEnv TEP_DB_USER_PROD "tep_app"
SetEnv TEP_DB_PASS "use-a-real-password"
SetEnv TEP_ENC_KEY_RAW "paste-base64-key"
SetEnv TEP_LEGACY_SALT "paste-existing-salt"
SetEnv TEP_IS_PRODUCTION "true"
PassEnv TEP_DB_PASS
```

If PHP-FPM does not inherit `SetEnv`, add `PassEnv` for each `TEP_*` name or put the same keys in the FPM pool `env[TEP_DB_HOST] = ...` file (outside the web root).

Copy-file example:

1. On the server, copy `config/env.example.php` to `config/env.php`.
2. Replace `replace_me` / empty live passwords with real values.
3. Confirm `config/env.php` is **not** in git (`git status` should not list it).

## 3. Apache / PHP checklist

- PHP **8.2** with `mysqli`, `pdo_mysql`, `openssl`, `json`
- `display_errors` off; `log_errors` on (already set in `common_functions.php`)
- HTTPS so session cookies use `secure` (see `start_secure_session()`)
- Document root points at this repo; `/data_access/` must be reachable for AngularJS `dataSvc`
- Service worker: vanilla `sw.js`. Cache-First for static GET (CSS/JS/fonts/images). Network-First GET `/data_access/` **HTTP 200 only**. Never cache non-GET, PHP HTML, or 404/500.
- After deploy, bump is not required unless `sw.js` `CACHE_VERSION` changed.

## 4. MySQL checklist

- Import the live schema (or restore an Event Snapshot `.sql` from **Event Snapshot → Export Event Snapshot** on an admin session).
- App user needs `SELECT` / `INSERT` / `UPDATE` / `DELETE` on the TEP schema. Local XAMPP demo also runs `CREATE TABLE IF NOT EXISTS` for polls, push, vendor, and a local check-in stub — **disable localhost autologin on live** (`TEP_IS_PRODUCTION=true`).
- `database_connect()` picks `DB_NAME_PROD` when the host (minus `www.`) starts with `easy`; otherwise `DB_NAME_DEV`.

## 5. Production session lock

- `$is_production` defaults to **true**.
- On `localhost` / `127.0.0.1` it flips to false unless `TEP_IS_PRODUCTION` is true.
- Live hosts must keep `TEP_IS_PRODUCTION=true` (or omit localhost) so account `1000` / user `1` is never auto-seeded.

## 6. SQL injection audit (Phase 9)

New poll / check-in / vendor / Event Pulse / snapshot paths use **PDO prepared statements** with bound parameters. `data_access/queries.php` named queries used by `getQueryResults.php` use **mysqli prepared statements** (`?` + bound params) except where noted.

The following files still build SQL with string interpolation (`mysqli_query` + `'{$inputs[...]}'` or concatenated table/column fragments). **Do not treat them as hardened.** Plan a follow-up to convert them to PDO or mysqli prepares. `sanitize_inputs()` only trims; it is not a substitute for bound parameters.

### High — request data in the SQL string

| File | Risk |
| --- | --- |
| `data_access/select_all_table_recs.php` | `SHOW COLUMNS FROM \`{$table}\`` |
| `login_process.php` | Email / account id interpolated into `SELECT` |
| `login_attendee.php` | Email / account id interpolated |
| `sponsor/login_process_sponsor.php` | Username interpolated |
| `login_process_er_acct.php` | Account id interpolated |
| `event.php`, `page.php` | Slug / account id interpolated |
| `set_session_account.php` | Account id interpolated |
| `create_account.php`, `create_demo_account.php`, `changeAcctStatus.php`, `updateAcctFee.php` | Account fields interpolated into INSERT/UPDATE |
| `save_payment.php`, `process_payment.php`, `process_basys_pymt.php` | Payment / account id interpolated |
| `save_signup.php`, `save_registration.php`, `save_registration_ticketing.php` | Event / confirmation interpolated |
| `send_confirmation.php`, `lookup_confirmation.php`, `reset_attendee_password.php` | Email / confirmation interpolated |
| `attendee/signup_print.php` | Confirmation interpolated |

### Also uses `mysqli_query` (review before rewrite)

`mail.php`, `bin/backup_db.php`, `logout.php`, `invoice.php`, `send_email.php`, `payRegistration.php`, `certificateData.php`, `sponsor/home.php`

Prefer the admin **Event Snapshot** (`admin/backup_db.php`, PDO + CSRF) over `bin/backup_db.php`.

### PDO paths that are already bound (not raw user SQL)

`data_access/queries.php` Instant Polling, Web Push save, staff check-in, vendor leads, Event Pulse; `admin/backup_db.php` snapshot; **`data_access/insert_or_update.php` and `delete_record.php`** (table/column identifiers regex + `information_schema`, values bound; `now()` becomes SQL `NOW()`). Local demo `->query()` / `->exec()` strings are constants only (no request data).

## 7. Smoke test after handoff

1. Open the live site in Chrome (HTTPS).
2. Confirm **Loading Event Data** appears and then dismisses.
3. Staff login (not account 1000 unless this is XAMPP).
4. Dashboard cards: events, Instant Polling, Event Pulse, Vendor Operations.
5. Staff: **Event Snapshot → Export Event Snapshot** downloads a `.sql` file.
6. Confirm `config/env.php` is not downloadable (expect 403/404 if you request `/config/env.php`).
7. Confirm Apache error log has no new PHP fatals.

## How to confirm on this XAMPP machine (Miller)

1. Open Chrome and go to `http://localhost/`.
2. **Loading Event Data** should appear and then go away.
3. You do **not** copy `config/env.php` on XAMPP unless you want to override the built-in localhost defaults.
4. Open `config/env.example.php` in Cursor and confirm it has names like `TEP_DB_HOST` and **no real live password**.
