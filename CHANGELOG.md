# Changelog

All notable changes to The Event Phoenix (TEP) are documented in this file in plain English.

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
