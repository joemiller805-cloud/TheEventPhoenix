# Changelog

All notable changes to The Event Phoenix (TEP) are documented in this file in plain English.

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
