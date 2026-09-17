# Changelog

All notable changes to The Event Phoenix (TEP) are documented in this file in plain English.

## [Sprint 22] — Phase 11 Final Lock and full-schema staging seed — 2026-09-17

### [Added]
- **`sql/tep_staging_seed.sql`:** Idempotent MariaDB staging seed for tenant 1000 (Phoenix Enterprise Events). Creates the full product schema discovered from PHP/JS DML plus live `tep_local` tables, seeds relational dummy rows, bcrypt-hashes dummy logins with `password_hash()`, and adds B-tree indexes on `accountid`, `eventid`, `attendeeid`, and `email`. No payment-processor secrets.
- **`docs/TEP_Executive_Admin_Guide.md`:** Operator paths for account 1000 dashboard (poll, check-in, vendor leads, snapshot) and `/admin.php` menus.
- **`docs/TEP_Master_QA_Guide.md`:** Auto-discovered feature matrix, discount-code math, and human test cases.
- **`tests/e2e/`:** Playwright templates for staff login, check-in, live poll, vendor lead, and public registration discount.

## [Sprint 21] — Sweep B: bcrypt, tenant isolation, branding restore — 2026-09-17

### [Added]
- **`tep_password_hash()` / `tep_password_verify()` / `tep_password_upgrade()`:** New passwords use `password_hash(PASSWORD_BCRYPT)`. Logins accept legacy `crypt()` once, then re-hash the same plaintext to bcrypt and update the tenant-bound row. `erSvc.verifyPassword()` posts `mode=verify` so AngularJS never compares bcrypt hashes in the browser.

### [Refactored]
- **Mail display name:** Outbound From display name is `TEP_PRODUCT_NAME` (`The Event Phoenix`) via `tep_mail_from_name()`. SMTP envelope still uses `SMTP_USER` or `postmaster@{host}` via `tep_mail_from_address()`.
- **CLI backup path:** `bin/backup_db.php` writes to `TEP_BACKUP_DIR` when set; otherwise `/home/easyregpro/private/backups`.
- **Marketing copy:** User-facing titles, footers, invoices, certificates, and email bodies say **The Event Phoenix**. Production hostnames and mailbox identities stay `easyregpro.com`.

### [Security Fix]
- **IDOR:** Payment, invoice, certificate, signup, mail, and confirmation paths bind `events.accountid` (or `sponsors.accountid`) from `tep_session_accountid()`. Attendee and sponsor sessions cannot hop tenants through `set_session_account.php` or `event.php` query strings.
- **Fail closed:** Gateway curl errors, XML parse failures, and PDO exceptions log to `error_log` only. They return generic JSON/text, never `mysqli_error`, PHPMailer `ErrorInfo`, or curl strings.
- **Branding replace rollback:** Case-insensitive PowerShell replace had overwritten mailboxes, the GA host detector, ER support identity, Zoom leave URL, and the CLI backup directory. Those identifiers are restored.
- **Query JSON:** `getQueryResults.php` strips `password` / `pass` columns from every row so hashes never leave the server. Public `checkAttendeeExists` returns `id` only.
- **PII dumps closed:** `commonJs.php` no longer console.logs `$_REQUEST` / session. `sendErrorEmail.php` no longer emails `$_SESSION` to a mailbox. Poll queries bind `tep_session_accountid()` only.

## [Sprint 20] — Sweep A: remaining PDO binds, session rotation, indexes — 2026-09-17

### [Added]
- **`sql/tep_tenant_indexes.sql`:** Lookup indexes on `accountid`, `eventid`, `attendeeid`, and `email` for tenant tables. `tep_ensure_tenant_indexes()` in `data_access/queries.php` applies the same indexes at runtime when a table/column exists and the index name is missing.
- **`tep_session_rotate()`:** Regenerates the session id without changing role. Used after password resets. Logins still call `tep_login_regenerate()`.

### [Refactored]
- **26 PHP files** that still concatenated request data into `mysqli_query` now use bound PDO via `tep_dml_pdo.php` (sponsor login, confirmation lookup, payments, mail, registrations, ER account tools, invoices, CLI backup, `select_all_table_recs.php`).
- **`attendee/signup_print.php`:** Stylesheet is `/css/bootstrap/css/bootstrap.css` (the old `/css/bootstrap.css` path 404ed).
- **Staff/sponsor password reset:** `send_pw_reset_email.php` generates the temp password on the server, updates with a bound `crypt()` hash, and no longer accepts a password from the query string.

### [Security Fix]
- **`er_encrypt.php`:** POST + CSRF + logged-in principal only. Anonymous GET hash oracle is closed. Logged-in `user_details.php` still hashes through `erSvc.encrypt()`.
- **Session rotation:** Sponsor login, confirmation-ticket login, ER tenant impersonation, and password resets call `session_regenerate_id(true)`.
- **HTTP errors:** `save_payment.php`, `save_signup.php`, `mail.php`, and `send_email.php` log exceptions; they no longer echo `mysqli_error`, PHPMailer `ErrorInfo`, or SQL.

## [Sprint 19] — Gateway consolidation: roles, PDO logins, file jail — 2026-09-17

### [Added]
- **`config/bootstrap.php`:** Single gateway include. Starts `start_secure_session()` (HttpOnly, SameSite=Lax, Secure on HTTPS) and binds `?accountid=` only when nobody is logged in. HTTP to `config/` stays denied.

### [Refactored]
- **Staff and attendee login:** `login_process.php` and `login_attendee.php` use PDO bound parameters via `tep_dml_pdo.php`, require POST + `X-CSRF-Token`, and call `session_regenerate_id(true)` on success. Staff table-access mapping is unchanged.
- **Documents:** `deleteDocument.php` is POST-only. `saveDocument.php` and deletes stay under `documents/account{id}`, `img/account{id}`, or `videos/account{id}`. AngularJS callers POST with CSRF. `erSvc.deleteDocument()` is the shared helper.
- **Mail:** Browser mail goes to `send_email.php` (logged-in + CSRF). Staff registration email dialogs keep working. Public vendor-request UI still saves the sponsor row without a browser mail relay.

### [Security Fix]
- **Removed from the web tree:** `create_demo_account.php`, `send_email_simple.php` (open mail relay), `dashboard/phpinfo.php`.
- **Roles:** `TEP_ROLE_STAFF` vs `TEP_ROLE_ATTENDEE` (plus sponsor/support). A confirmation ticket is an attendee, not staff. File uploads require staff, support, or sponsor.
- **`commonJs.php`:** No longer copies `?accountid=` over an active login.
- **Attendee login:** The magic crypt hash bypass is gone. Password must match the bound hash.
- **`login_process_er.php`:** Regenerates the session id and sets the support role after a successful ER login.
- **`database_connect()`:** Sets mysqli charset to utf8mb4 to match PDO.

## [Sprint 18] — Phase 11 forensic H1–H3, GET mutations, XSS, table allowlist — 2026-09-16

### [Refactored]
- **`js/dataAccess.js`:** New `postArray()` POSTs to `getQueryResults.php` with the existing `X-CSRF-Token` header. Dashboard poll vote, check-in toggle, vendor lead save, and push subscription save use `postArray` instead of `getArray`.
- **`index.php`:** `voteOnPoll`, `toggleCheckIn`, `saveVendorLead`, and `savePushSubscription` call `dataSvc.postArray` so AngularJS `$scope` bindings stay the same.

### [Security Fix]
- **`getQueryResults.php`:** Non-public queries require a real principal (`userid` / `attendeeid` / `sponsorid` / `registrationid` / `erSupport`). Session `accountid` alone no longer unlocks staff dumps. Write queries (`submitPollVote`, `checkInAttendee`, `saveVendorLead`, `savePushSubscription`) reject GET with HTTP 405 and require POST plus `tep_require_csrf_token()`.
- **Public query allowlist:** `getRegByEmail`, `extraRegData`, `attendeeMatchQuery`, and `checkAttendeeCredentials` are no longer anonymous. Attendee PII cannot be scraped without a login.
- **`tep_require_csrf_token()`:** Compares `X-CSRF-Token` only (POST body token is not enough).
- **`commonJs.php`:** `json_encode()` of request parameters (and session dumps into `<script>`) uses `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
- **`attendee/signup_print.php`:** Confirmation lookup uses prepared statements. `tep_h()` escapes confirmation and printed fields. Session uses `start_secure_session()`.
- **`select_all_table_recs.php`:** Table names must match an explicit allowed array before `SHOW COLUMNS` or `SELECT`. Unknown identifiers return HTTP 400 even for master.

## [Sprint 17] — Phase 11 session, API 401, XSS/CSRF baseline — 2026-09-16

### [Refactored]
- **`login_er.php`:** Uses `start_secure_session()` before any session cookie. Title, header, and footer say **The Event Phoenix**. Logo is `/css/tep-logo.svg` with text fallback. Enter submits the form.

### [Security Fix]
- **Session cookies:** Super-user login and `data_access/` HTTP endpoints call `start_secure_session()` so HttpOnly, SameSite=Lax, and Secure-on-HTTPS apply.
- **API auth:** Unauthenticated writes (`insert_or_update`, `delete_record`, `runQuery`, `runUserDML`) return HTTP 401 JSON `{"ok":false,"error":"Unauthorized"}`. Reads (`getQueryResults`, `select_all_table_recs`, `runRead`) require a principal or tenant `accountid`, except a public query allowlist (login, landing, ACME, public event pages). `select_all` `allAccess` no longer grants anonymous table dumps. POST/GET mutating APIs check `X-CSRF-Token` / `csrf_token`.
- **`data_access/.htaccess`:** Denies HTTP to `db.php`, `queries.php`, and `tep_dml_pdo.php` (PHP include still works).
- **XSS:** `tep_js_string()` / `tep_h()` wrap request `slug`, `confirmation`, `accountid`, and `id` echoed into JS/HTML on event and attendee views.
- **`get_data.php` / `export_snapshot.php`:** Not present. Snapshot export remains `admin/backup_db.php` (CSRF + admin session). Remaining High SQL interpolation in login/payment PHP is unchanged (see `DEPLOYMENT.md`).

## [Sprint 16] — Login HTTPS cookies, relative assets, TEP branding — 2026-09-16

### [Added]
- **`css/tep-logo.svg`:** Tracked login/header/footer mark. `/img/` is gitignored and empty, so login no longer points at missing `ERP-no-tag.png`.

### [Refactored]
- **`login.php`:** Includes `common_functions.php` and `start_secure_session()` before any `session_start`. CSS/JS/images stay root-relative or HTTPS CDN (no `http://` assets). Footer copyright is **The Event Phoenix**. Broken logos hide via `onerror` and show text.

### [Security Fix]
- **`common_functions.php` `start_secure_session()`:** Sets `session.cookie_samesite=Lax`, HttpOnly, and `Secure` only when `is_https_request()` is true (HTTPS env, port 443, or `REQUEST_SCHEME=https`) so local HTTPS cookies are not dropped. Host-only domain (empty) avoids Chrome rejecting `domain=localhost`.

## [Sprint 15] — DML 2s timeout JSON errors and vendor lead form Enter — 2026-09-16

### [Refactored]
- **Vendor Operations (`index.php`):** Lead fields sit in `<form ng-submit="saveVendorLead()">`. **Save lead** is `type="submit"`. Inputs stay enabled while `vendorOps.busy` so typing focus is not lost; `ng-disabled` stays on the button only. Enter in a field saves without a page reload. No booth still cannot save.

### [Security Fix]
- **`data_access/tep_dml_pdo.php`:** Generic insert/update/delete now uses `tep_pdo_options()` with `PDO::ATTR_TIMEOUT => 2` so a dead MySQL socket cannot stall AngularJS. Connect/parse/execute failures return HTTP 200 JSON `{"ok":false,"error":"Query Execution Error"}` (no stack traces, no credentials). `Not Authorized` stays HTTP 403 JSON.

## [Sprint 14] — Cap DB waits at 2s and bypass SW for query APIs — 2026-09-16

### [Added]
- **`data_access/db.php`:** Shared `tep_pdo_options()` includes `PDO::ATTR_TIMEOUT => 2` (and `PDO::MYSQL_ATTR_READ_TIMEOUT => 2` when mysqlnd defines it) so hung MySQL cannot stall AngularJS `dataSvc`.

### [Refactored]
- **Staff check-in (`index.php`):** The **Name or ticket** box no longer runs `ng-change` debounce search. Search runs only on **Search** / form submit (Enter). The input stays enabled while `checkIn.busy` so focus is not lost mid-string.
- **`data_access/queries.php` `tep_poll_pdo()`:** Uses `db.php` options and sets `PDO::ATTR_TIMEOUT => 2` on the poll/check-in/vendor/Pulse PDO handle.
- **`common_functions.php` `database_connect()`:** Default mysqli connect/read timeout is **2 seconds** (was 5) so `getQueryResults.php` fails fast when MySQL is down; HTTP 200 empty rows still apply.
- **`sw.js` `CACHE_VERSION` `v1.5.0`:** `getQueryResults.php`, `sessionCheck.php`, and all `/data_access/` GETs return without `respondWith` so the browser talks to Apache once. Activate drops `tep-api-v1.4.0`. Static Cache-First and `/offline.html` navigation fallback are unchanged.
- **MySQL host `127.0.0.1`:** XAMPP / missing-config defaults and PDO DSNs use IPv4 loopback instead of `localhost` so Windows does not spend ~2s on IPv6 DNS. `tep_mysql_host()` in `data_access/db.php` also remaps `localhost` / `::1`. `HTTP_HOST` localhost checks are unchanged.

### [Security Fix]
- Query JSON is no longer written to Cache Storage (session cookies / tenant rows must not persist in the PWA API cache).

## [Sprint 13] — Phase 9 environment isolation and deploy handoff — 2026-09-16

Phase 9 isolates database host, credentials, and session keys from source control and documents remaining raw SQL for the next hardening sprint (no Workbox, no Node, no Angular 2+).

### [Added]
- **`config/env.example.php`:** Template for `DB_HOST` / `DB_PORT` / schema / user / password, `TEP_ENC_KEY_RAW`, `LEGACY_SALT`, `TEP_SESSION_NAME`, VAPID, `BASE_URL`, and production flags. Copy to gitignored `config/env.php` or set the same `TEP_*` names as Apache `SetEnv`.
- **`config/.htaccess`:** Denies HTTP to `config/` so a copied `env.php` is not downloadable.
- **`DEPLOYMENT.md`:** Server handoff for `master` (PHP 8.2 / MySQL / AngularJS 1.x), secret load order, Apache/MySQL checklist, production session lock, and the remaining `mysqli_query` interpolation inventory.

### [Refactored]
- **`common_functions.php`:** After `private/tep_config.php`, optionally loads `config/env.php`, then fills any still-undefined constants from `TEP_*` process environment. Empty env values are skipped so XAMPP localhost fallbacks still run. `start_secure_session()` can set `session_name()` from `TEP_SESSION_NAME`. `database_connect()` casts `DB_PORT` to int.

### [Security Fix]
- Live passwords and `LEGACY_SALT` belong in `private/tep_config.php`, Apache `SetEnv`, or gitignored `config/env.php` — not in git. `.gitignore` now lists `/config/env.php`.
- SQL audit (not rewritten this sprint): high-risk interpolation remains in login/payment/registration PHP that concatenates `$inputs` into `mysqli_query`, plus `data_access/select_all_table_recs.php`. Poll / check-in / vendor / Pulse / snapshot / **insert_or_update + delete_record** stay on PDO bound parameters. Full file list is in `DEPLOYMENT.md` §6.

## [Sprint 13b] — Bound PDO for generic create/update/delete — 2026-09-16

### [Refactored]
- **`data_access/insert_or_update.php`:** Parses the existing AngularJS `insertColumns` / `insertValues` / `updateData` / `whereClause` / `table` payload into identifiers + bound PDO parameters. `now()` is emitted as SQL `NOW()`. `WHERE` is `id = :id` only. Sponsor/master/`tableAccess` gates are unchanged.
- **`data_access/delete_record.php`:** `DELETE FROM \`table\` WHERE \`id\` = :id` with regex table names and digit-only ids. Same authorization lists as before.
- **`data_access/tep_dml_pdo.php`:** Shared PDO factory, identifier regex, `information_schema` column whitelist, and value/SET parsers.

### [Security Fix]
- Generic DML no longer concatenates request fragments into `mysqli_query`. Failed statements return `Query Execution Error` without echoing SQL. Unknown table/column names are rejected.

## [Sprint 12] — Query error boundary HTTP 200 empty rows — 2026-09-16

### [Refactored]
- `data_access/getQueryResults.php` catches PHP 8.2 `mysqli_sql_exception` from `database_connect()` (Apache log: connection refused at `common_functions.php:182`, uncaught at `getQueryResults.php:73`) and from prepare/execute. Missing tables, SQL errors, and a down MySQL now return HTTP 200 JSON `{"rows":[]}` so AngularJS `dataSvc` and `hideLoading()` still run. Unknown query names remain 400.
- **M3 local check-in seed:** `tep_checkin_ensure_local_demo` reflects `registrations` columns via bound `information_schema` and INSERTs only whitelist columns that exist (`eventid`, `attendeeid`, `confirmation`, plus optional `deleted` / `checkin` / `checkin_userid` / `registration_typeid` / `userid`). Extra production NOT NULL columns are skipped per-row (`Throwable` log). The whole local demo (events/attendees seed included) is wrapped in `try/catch` so a schema mismatch cannot abort check-in or Event Pulse HTTP 200.
- **Dashboard first paint (`index.php`):** `$scope.loadingData` is set true with “Loading Event Data” and cleared in `hideLoading()`. `getAccountIdFromURL`, `accountInfo`, `currentSeasonPassCount`, `accountEvents`, polls, vendor, Pulse, and the push probe all `.finally(hideLoading)` (early push exits call `hideLoading()` too). `evt.startdate.indexOf` runs only after a null/empty check; missing dates mark `datesPending`.

### [Security Fix]
- PDO connect/execute in `data_access/queries.php` (`tep_poll_pdo` and the poll/check-in/vendor/pulse block) catch `Throwable`. Failures log the exception and still emit HTTP 200 empty `rows` (or `ok:0` for writes). SQL error text is never printed to the browser.

## [Sprint 11] — Phase 8 Event Snapshot SQL backup — 2026-09-16

Phase 8 adds a secure admin SQL snapshot download so staff can export this account’s TEP tables without dumping other tenants (no Workbox, no Node, no Angular 2+).

### [Added]
- **Admin utility (`admin/backup_db.php`):** PDO `information_schema` reflection lists BASE tables in the current TEP schema. Each table name/column is regex-checked before backtick quoting. The response is a timestamped `.sql` download (`tep_event_snapshot_{accountid}_{UTC}.sql`) with `SHOW CREATE TABLE` plus account-scoped `INSERT` rows (`accountid`, or `eventid` via `events`, or `pollid` via `tep_polls`). Tables with no tenant key dump structure only.
- **AngularJS UI (`index.php`):** Added an **Export Event Snapshot** card on `regController` (48px button) shown only when `$scope.tepAdminSession` is true (`accountid` matches `useraccount`, same as `admin.php`). The click uses existing `erPostRedirect` so the request is POST with `csrf_token`. `$applyAsync` / `hideLoading()` keep the dashboard digest and loader lifecycle intact.

### [Security Fix]
- Snapshot export requires POST + CSRF (`require_csrf_request`) and an admin session. GET is 405. Attendee sessions are 403. SQL identifiers are never taken from request parameters. Data selects bind session `accountid` only. The dump is `Cache-Control: no-store` so the service worker does not cache it.
- **H1 (`index.php`):** `accountInfo` only reads `resp[0].name` / `home_pg_msg` when `angular.isArray(resp) && resp[0]`. Empty HTTP 200 `rows` (down MySQL) and dataSvc `"error"` no longer TypeError; polls, vendor, and Event Pulse still load.
- **H2 (`admin/backup_db.php`):** Schema reflection, the table loop, and streaming sit in one `try/catch (Throwable)`. Failures are logged; the download stays a `.sql` file with `-- TEP Event Snapshot backup failed.` comments (no exception text, no 500 HTML in the stream).

## [Sprint 10] — Phase 7 Event Pulse live analytics — 2026-09-16

Phase 7 adds a touch-optimized Event Pulse card on the AngularJS dashboard with real-time check-in percentage, vendor lead totals, and live poll vote breakdowns (no Workbox, no Node, no Angular 2+).

### [Added]
- **PDO aggregates (`query=getEventAnalytics`):** Session-scoped COUNT of registrations (checked-in vs total) with a 0–100 percent, COUNT of `tep_vendor_leads`, and active-poll option tallies from `tep_poll_votes`. Optional bound `eventid` (0 = all events). Empty session returns HTTP 200 zeros instead of leaking other accounts.
- **AngularJS UI (`index.php` Event Pulse card):** `regController` binds `$scope.eventPulse` from HTTP 200 `rows` (`ok`, `checkin_percent`, `checkin_in`, `checkin_total`, `lead_count`, `polls_json`). 48px Refresh Pulse button, large metric tiles, check-in bar. Loads after accountid, on pull-to-refresh, after check-in / lead save / poll vote, and every 20 seconds via `$interval` (`$applyAsync`). Fail-soft message; `hideLoading()` on success and error.

### [Refactored]
- `data_access/getQueryResults.php` treats `getEventAnalytics` like Instant Polling and staff/vendor ops: always HTTP 200 JSON `{"rows":[...]}` so AngularJS `dataSvc.getArray` and the PWA Network-First cache see success. Other queries still use 400/500.

### [Security Fix]
- Event Pulse SQL uses PDO prepared statements with bound `accountid` / `eventid` only (no concatenated request data). Aggregates JOIN `events` so another account’s registrations cannot appear in the percentage.

## [Sprint 9] — Phase 6 Offline Resiliency & Production Security Hardening — 2026-09-10

Phase 6 hardens the vanilla PWA offline path and locks the developer session bypass on live hosts (no Workbox, no Node, no Angular 2+).

### [Refactored]
- **Service worker navigation fallback (`sw.js` `CACHE_VERSION` `v1.4.0`):** Activate drops `tep-static-v1.3.0` / `tep-api-v1.3.0`. Document navigations (`mode=navigate` or `destination=document`) still try live PHP first and never write PHP HTML to Cache Storage. HTTP 404 (missing routes), opaque/status-0 responses, and network throws now receive the precached `/offline.html` shell via `matchOfflineShell()`. Live 200 PHP is unchanged. Live 401/403/500 still pass through from Apache. `/offline.html` copy now covers both offline and unknown URLs.

### [Security Fix]
- **Production flag (`common_functions.php`):** Sets `$is_production = true` as the strict live default. `tep_apply_local_dev_session()` returns immediately when `$is_production === true`, so the developer session bypass (account `1000` / user `1`) cannot run on a live deploy. Host-header `tep_is_local_host()` remains a second gate.
- **`TEP_IS_PRODUCTION` override:** Local XAMPP may set `$is_production = false` only when the host is localhost or 127.0.0.1 **and** `TEP_IS_PRODUCTION` is not defined true in `tep_config.php`. Defining `TEP_IS_PRODUCTION` as `true` keeps the production lock even on a local hostname, so account 1000 autologin cannot be forced on by host-header spoofing when that constant is set.

## [Sprint 8] — Phase 5 Staff & Vendor Operations — 2026-09-10

Phase 5 completes floor operations on the AngularJS dashboard: staff check-in, vendor booth status and lead capture, and a universal loader error boundary so “Loading Event Data” cannot stay stuck (no Workbox, no Node, no Angular 2+).

### [Added]
- **Staff check-in PDO (`query=getAttendeeCheckInStatus`, `query=checkInAttendee`):** Search matches confirmation/ticket or attendee first/last/full name with bound LIKE parameters (wildcards stripped, minimum two characters, max 25 rows, scoped to `events.accountid`). Check-in toggles `registrations.checkin` / `checkin_userid` only when the registration belongs to the session account. Local XAMPP seeds a hidden demo event plus tickets `TEPJANE1` / `TEPJOHN2`.
- **Vendor ops schema (`sql/tep_vendor_ops.sql`):** Added lightweight `tep_vendor_booths` (account/vendor, event, booth, hall, notes) and `tep_vendor_leads` (attendee name, email, company, ticket, notes). Local XAMPP creates the tables on first request and seeds demo vendor **Phoenix Exhibits**, booth **A-12**, Hall 2.
- **Vendor PDO (`query=getVendorStatus`, `query=saveVendorLead`):** Status is scoped to session `accountid` plus `sponsorid` (localhost account `1000` uses demo sponsor `1` so the card can be tried). Leads insert with bound parameters. Invalid name/email return HTTP 200 `ok:0`.
- **AngularJS UI (`index.php`):** Added a Staff check-in card (`regController`): 48px search and check-in/undo buttons, debounced name/ticket search, HTTP 200 `rows` (`ok` / `checked_in`). Added a Vendor Operations card: booth/hall/event, 48px lead form, recent leads. `$applyAsync` updates both cards; events, polls, and notifications stay on screen.

### [Refactored]
- `data_access/getQueryResults.php` treats `getAttendeeCheckInStatus`, `checkInAttendee`, `getVendorStatus`, and `saveVendorLead` like Instant Polling and push-save: always HTTP 200 JSON `{"rows":[...]}` so AngularJS `dataSvc.getArray` and the PWA Network-First cache see success. Other queries still use 400/500.
- **Universal loader lifecycle:** Dashboard AJAX on `index.php` calls `hideLoading()` (via `erSvc.hideLoading`) on both success and error: initial `accountEvents` / `accountInfo` load, staff check-in search/toggle, vendor status/lead save, polls, pull-to-refresh, and push subscribe. `erSvc.hideLoading` closes the jQuery UI “Loading Event Data” dialog and calls `$.unblockUI` when the legacy plugin is present, so that modal cannot remain stuck.

### [Security Fix]
- Check-in and vendor SQL use PDO prepared statements with bound parameters (no concatenated request data). Check-in updates JOIN `events` so another account’s registration cannot be toggled; short searches return an empty list instead of the full roster. Booth and lead rows are limited to the session account and vendor id. Invalid emails are rejected; attendee name must be at least two characters.

## [Sprint 7] — Phase 4 Mobile Polish & App Shortcuts — 2026-09-10

Phase 4 polishes the installed PWA for mobile: app-icon shortcuts, 48px tap targets, and dashboard pull-to-refresh on the existing AngularJS 1.x view (no Workbox, no Node, no Angular 2+).

### [Added]
- **App shortcuts (`manifest.json`):** Added PWA App Shortcuts for long-press / app-icon menus on installed Android/Chrome: Dashboard (`/`), My Events (`/user_events.php`), Admin (`/admin.php`), and Season Passes (`/seasonPasses.php`). Each shortcut uses the existing 192x192 `/pwa/icon-192.png` (Chrome requires at least 96x96). JSON cannot hold comments; this entry is the record of that edit. `sw.js` `CACHE_VERSION` is `v1.3.0` so Cache-First drops the previous `manifest.json`.
- **Pull-to-refresh (`index.php`):** Added touch handling on the AngularJS dashboard (`regController`): `touchstart` / `touchmove` / `touchend` on the dashboard root. A downward pull at scroll-top shows a 48px status row; releasing past 64px calls `refreshDashboard()`, which reloads events, polls, season-pass visibility, and push-toggle state through existing `dataSvc` paths and `$applyAsync` (no `location.reload`, no digest bypass). Denied/busy pulls fail-soft. Desktop mouse scrolling is unchanged.

### [Refactored]
- **48px min tap targets (`index.php`):** Dashboard primary buttons and inputs now have a CSS floor of 48px tap height (`.tep-dashboard .btn-primary` and form controls), including Season Pass and Learn More links that previously used default Bootstrap padding. The page navbar toggler is also 48×48. Poll and notification buttons already met this floor.

### [Security Fix]
- Pull-to-refresh only `preventDefault`s while the page is at scroll-top and the finger has moved down more than 12px, so normal list scrolling still works. Refresh reuses session-scoped `dataSvc` GETs; it does not add new query parameters or concatenate input into SQL.

## [Sprint 6] — Phase 3 Web Push Notification System — 2026-09-10

Phase 3 completes Web Push on the existing vanilla service worker, PDO query API, and AngularJS 1.x dashboard (no Workbox, no Node, no Angular 2+).

### [Added]
- **Service worker listeners (`sw.js`):** Added `push` and `notificationclick` on vanilla `sw.js`. Incoming Web Push payloads show a system notification (title/body/url from JSON, fail-soft to plain text). A click focuses an existing TEP window or opens a new one at a same-origin path (default `/`). Push handling does not write PHP HTML or non-GET responses to Cache Storage.
- **Schema (`sql/tep_push_subscriptions.sql`):** Added the lightweight `tep_local` table `tep_push_subscriptions` for browser PushSubscription rows: account/user, unique HTTPS `endpoint`, `p256dh` and `auth` keys, truncated user-agent, and created/updated timestamps.
- **Backend persistence (`query=savePushSubscription`):** Added a PDO endpoint in `data_access/queries.php` that upserts on unique `endpoint` with bound parameters (`accountid`/`userid` from session, `endpoint`, `p256dh`, `auth`). Local XAMPP creates the table on first save. Invalid or missing HTTPS keys return HTTP 200 `ok:0` instead of 400/500.
- **AngularJS opt-in UI (`index.php` dashboard card):** Added a Notifications card on `regController` with a 48px toggle. It calls `Notification.requestPermission()` and `serviceWorker.ready` → `pushManager.subscribe()`, then sends `endpoint` / `p256dh` / `auth` to `savePushSubscription` through existing `dataSvc.getArray`. Tapping again unsubscribes on this device. Denied, blocked, unsupported, and missing-key paths only update `$scope.pushNotify` (with `$applyAsync`) and never break events or polls. `common_functions.php` exposes `tep_vapid_public_key()` for `applicationServerKey` (`TEP_VAPID_PUBLIC_KEY` from `tep_config.php` in production; localhost falls back to the NIST P-256 generator point so Chrome subscribe() has a valid 65-byte key).

### [Refactored]
- `data_access/getQueryResults.php` treats `savePushSubscription` like the Instant Polling endpoints: always HTTP 200 JSON `{"rows":[...]}` so AngularJS `dataSvc.getArray` and the PWA Network-First cache see success. Other queries still use 400/500.

### [Security Fix]
- Push subscription SQL uses PDO prepared statements with bound parameters (no concatenated request data). Endpoints must be `https://`. `notificationclick` only opens same-origin relative URLs that start with `/` (protocol-relative and off-origin URLs fall back to `/`).
- Dashboard notification permission denied/blocked/unsupported paths only update `$scope.pushNotify` and never throw into the AngularJS digest, so events and polls stay on screen.

## [Sprint 5] — Phase 2 Instant Polling System — 2026-09-10

### [Added]
- Added `sql/tep_polls.sql` with the lightweight `tep_local` schema for Instant Polling: `tep_polls` (account-scoped question, JSON options, optional event, `is_active`) and `tep_poll_votes` (one vote per `voter_key` per poll).
- Added PDO endpoints `query=getActivePoll` and `query=submitPollVote` in `data_access/queries.php`. Active polls are loaded with bound parameters (including `vote_counts_json` per-option tallies). Votes insert with a unique `(pollid, voter_key)` constraint. Local XAMPP creates the tables on first poll request and seeds a demo poll for account `1000`.
- Added an AngularJS live poll widget on the `index.php` dashboard (`regController`): loads `getActivePoll`, shows the question and 48px-tall option buttons, submits `submitPollVote`, and updates `$scope.polls` with `$applyAsync` (no page reload). Hidden when there are no active polls.

### [Refactored]
- `data_access/getQueryResults.php` always returns HTTP 200 JSON `{"rows":[...]}` for `getActivePoll` and `submitPollVote` (empty list or `ok`/`reason` on vote) so AngularJS `dataSvc.getArray` and the PWA Network-First cache see success. Other queries still use 400/500.

### [Security Fix]
- Poll SQL uses PDO prepared statements with bound parameters (no concatenated request data). Votes are scoped to an active poll id; empty session account ids do not list other accounts’ polls.

## [Sprint 4] — PWA icons, manifest validation, and offline shell — 2026-09-10

### [Added]
- Added committed square PWA icons under `/pwa/`: `icon-192.png` (192x192, purpose `any`), `icon-512.png` (512x512, purpose `any`), and `icon-maskable-512.png` (512x512, purpose `maskable`, fire `#D84315` fill with safe-zone padding). These replace the previous install set that pointed at the 800x600 `/img/e.png` landscape mark.
- Added static `/offline.html` as a dedicated offline fallback shell (fire theme colors, no AngularJS, no PHP session). Direct GETs of that file are Cache-First. Document navigations that fail when Apache is unreachable receive this shell instead of the browser’s default error page.

### [Refactored]
- Validated `manifest.json` for installability: `start_url` is `/`, `display` is `standalone`, `theme_color` is `#E65100`, `background_color` is `#D84315`, and the icons array now has real 192x192, 512x512, and maskable PNGs whose `sizes` match the files on disk. The 48x48 favicon entry was removed from the install set.
- `sw.js` `CACHE_VERSION` is `v1.2.0` so activate drops `tep-static-v1.1.0` / `tep-api-v1.1.0` and precaches the new icons plus `/offline.html`. HTML navigations stay Network-First; PHP HTML is still never written to Cache Storage.
- `index.php` apple-touch-icon now points at `/pwa/icon-192.png` so iOS gets the square 192 asset that matches the validated manifest.

### [Security Fix]
- The offline shell is static HTML only. PHP documents, non-GET requests, and HTTP 404/500 bodies remain uncached.

## [Sprint 3] — PWA caching rules and local DB fallbacks — 2026-09-10

### [Added]
- Added a localhost-only auto-login helper in `common_functions.php` (`TEP_LOCAL_DEV_AUTOLOGIN`, default on) that seeds session account `1000` / user `1` so `http://localhost/` skips `landing.php` and shows the event dashboard. Production hosts are never seeded. Set `TEP_LOCAL_DEV_AUTOLOGIN` to false in `tep_config.php` to turn it off.

### [Refactored]
- `query=eventData` with an empty or missing slug no longer 500s: `queries.php` returns a safe empty `rows` array and `getQueryResults.php` encodes HTTP 200 JSON without running the event SQL (avoids PHP 8.2 undefined-slug fatals and missing local `account_fee_structure`).
- `common_functions.php` no longer fatals on PHP 8.2 when `TEP_ENC_KEY_RAW` is missing because `private/tep_config.php` is absent on local XAMPP; it defines an empty placeholder so `index.php` can load. Production still uses the real key when that config file is present.
- Local XAMPP now gets `DB_HOST` / `DB_PORT` / `DB_NAME_DEV` / `DB_USER_LOCAL` (and related) fallbacks in `common_functions.php` so `/data_access/getQueryResults.php` no longer 500s on undefined `DB_NAME_DEV`. Production `tep_config.php` still wins when present. `queries.php` also uses those fallbacks and an empty session `accountid` when the encryption key is missing.
- `sw.js` uses Cache-First for CSS/JS/fonts/images (`tep-static-v1.1.0`) and Network-First for GET `/data_access/` API calls (`tep-api-v1.1.0`). `CACHE_VERSION` is `v1.1.0` so activate deletes outdated `tep-static-*` and `tep-api-*` buckets. HTTP 404/500 responses are never written to Cache Storage. Non-GET and PHP HTML stay network-only. Missing precache files fail-soft; `/sw.js` itself is never cache-firsted.
- `.cursorrules` PWA bullet now matches that Cache-First / Network-First split so later sessions do not treat `/data_access/` GET 200 caching as forbidden.

### [Security Fix]
- The service worker ignores PHP HTML and non-GET requests so login sessions and CSRF posts cannot be served from Cache Storage. GET `/data_access/` is Network-First and only successful HTTP 200 JSON is cached; 404/500 are never stored.

## [Sprint 1] — Official Launch — 2026-09-10

### [Added]
- Created `.cursorrules` so every coding session follows Lead Architect rules for the PHP 8.2 / MySQL / AngularJS 1.x LAMP stack (surgical edits, no Node or Angular upgrades, PDO-only SQL, Eric Transparency, and a 3-point pre-flight plan).
- Created `.cursor/rules/tep-legacy-lamp.mdc` so Cursor always applies those same TEP guardrails in this workspace.
- Added a PWA constraint to `.cursorrules`: vanilla `sw.js` only — no Workbox/Node worker toolchains, and never cache PHP, `/data_access/`, or non-GET responses.
- Created `manifest.json` with TEP identity, standalone display, and fire theme colors (#E65100 theme, #D84315 background), pointing at the existing `/img/e.png` mark.
- Created `sw.js` with static-asset caching and an explicit `CACHE_VERSION = 'v1.0.0'` kill-switch so bumping that string drops stale TEP caches.
- Updated `index.php` to output a `<base href>` from `BASE_URL`, link the web app manifest, set the fire theme-color, and register the service worker asynchronously after load so AngularJS digest is not blocked.

### [Refactored]
- AngularJS modules, `$scope` bindings, and digest calls in `index.php` were left as they were.
- `sw.js` now fail-softs missing precache files, deletes only old `tep-static-*` buckets on activate, and never cache-firsts `/sw.js` itself.
- `index.php` slash-normalizes `BASE_URL` before writing `<base href>` and registers `/sw.js` with `{ scope: '/' }` using a load/`readyState` async path.
- `manifest.json` icon purpose is `any` for both 192 and 512 entries because `/img/e.png` is the existing non-square TEP mark, not a maskable asset.

### [Security Fix]
- The service worker ignores PHP, `/data_access/`, and non-GET requests so login sessions, CSRF posts, and query results cannot be served from Cache Storage.
- `BASE_URL` is HTML-escaped in the `index.php` `<base href>` attribute so a mistyped config value cannot break out of the tag.

## [Added] - 2026-09-10
- Created baseline manifest.json for PWA installation support.
