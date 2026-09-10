# Changelog

All notable changes to The Event Phoenix (TEP) are documented in this file in plain English.

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