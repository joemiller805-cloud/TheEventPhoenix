/* TEP Sprint 1: root service worker - static assets only; bump CACHE_VERSION to kill stale caches */
const CACHE_VERSION = 'v1.0.0'; // TEP kill-switch: change this string to drop every previous TEP cache
const CACHE_NAME = 'tep-static-' + CACHE_VERSION; // TEP Sprint 1: versioned cache bucket tied to the kill-switch

const PRECACHE_URLS = [ // TEP Sprint 1: small static set; never list PHP or data_access URLs here
  '/manifest.json', // TEP Sprint 1: PWA identity file
  '/favicon.ico', // TEP Sprint 1: existing site favicon
  '/img/e.png', // TEP Sprint 1: existing TEP mark used by the manifest
  '/css/bootstrap/css/bootstrap.css', // TEP Sprint 1: core stylesheet already loaded by commonStyles.php
  '/css/easyreg.css', // TEP Sprint 1: existing app stylesheet
  '/js/jquery.js', // TEP Sprint 1: existing jQuery; do not replace with a Node bundle
  '/js/angular1_7_2.js' // TEP Sprint 1: existing AngularJS 1.x runtime — cache the file, do not upgrade it
];

function isStaticAsset(url) { // TEP Sprint 1: only cache fonts/css/js/images/manifest — never session HTML
  return /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|eot|json)$/i.test(url.pathname); // TEP Sprint 1: extension allow-list
}

self.addEventListener('install', function (event) { // TEP Sprint 1: precache static shell on first install
  event.waitUntil( // TEP Sprint 1: hold install until precache finishes
    caches.open(CACHE_NAME).then(function (cache) { // TEP Sprint 1: open the versioned static cache
      return Promise.all(PRECACHE_URLS.map(function (url) { // TEP Sprint 1: add each file so one 404 cannot abort install
        return cache.add(url).catch(function () { /* TEP Sprint 1: skip missing static files; keep CACHE_VERSION install alive */ }); // TEP Sprint 1: fail-soft precache
      })); // TEP Sprint 1: wait for every precache attempt (success or skip)
    }).then(function () { // TEP Sprint 1: activate the new worker immediately when CACHE_VERSION changes
      return self.skipWaiting(); // TEP Sprint 1: kill-switch takes effect without a second reload
    })
  );
});

self.addEventListener('activate', function (event) { // TEP Sprint 1: drop caches that do not match CACHE_VERSION
  event.waitUntil( // TEP Sprint 1: finish cleanup before claiming clients
    caches.keys().then(function (keys) { // TEP Sprint 1: inspect every Cache Storage bucket
      return Promise.all(keys.map(function (key) { // TEP Sprint 1: inspect each bucket name
        if (key.indexOf('tep-static-') === 0 && key !== CACHE_NAME) { // TEP Sprint 1: drop old TEP caches only; leave unrelated origin caches
          return caches.delete(key); // TEP Sprint 1: kill-switch — remove stale tep-static-* when CACHE_VERSION changes
        }
      }));
    }).then(function () { // TEP Sprint 1: take control of open pages so the new version is live
      return self.clients.claim(); // TEP Sprint 1: no AngularJS rewrite; pages keep their existing scopes
    })
  );
});

self.addEventListener('fetch', function (event) { // TEP Sprint 1: intercept GET static files only
  const request = event.request; // TEP Sprint 1: local alias for readability
  if (request.method !== 'GET') { // TEP Sprint 1: never cache POST/PUT (CSRF, payments, logins)
    return; // TEP Sprint 1: let the network handle mutating requests
  }
  const url = new URL(request.url); // TEP Sprint 1: parse path for allow/deny checks
  if (url.origin !== self.location.origin) { // TEP Sprint 1: do not cache CDN/third-party (gtag, jsdelivr)
    return; // TEP Sprint 1: avoid mixing cross-origin bodies into TEP Cache Storage
  }
  if (url.pathname.indexOf('/data_access/') === 0 || /\.php$/i.test(url.pathname)) { // TEP Sprint 1: skip PHP/API
    return; // TEP Sprint 1: session-backed PHP must always hit Apache
  }
  if (url.pathname === '/sw.js') { // TEP Sprint 1: never cache-first the worker file itself
    return; // TEP Sprint 1: browser update checks must see a live sw.js after CACHE_VERSION bumps
  }
  if (!isStaticAsset(url)) { // TEP Sprint 1: HTML navigations stay network-only
    return; // TEP Sprint 1: preserves AngularJS first paint from live index.php
  }
  event.respondWith( // TEP Sprint 1: cache-first for allowed static assets
    caches.match(request).then(function (cached) { // TEP Sprint 1: return cached file when present
      if (cached) { // TEP Sprint 1: serve from CACHE_VERSION bucket
        return cached; // TEP Sprint 1: no digest impact — binary/static only
      }
      return fetch(request).then(function (response) { // TEP Sprint 1: network fallback then stash
        if (!response || response.status !== 200 || response.type !== 'basic') { // TEP Sprint 1: cache only OK same-origin
          return response; // TEP Sprint 1: do not store errors or opaques
        }
        const copy = response.clone(); // TEP Sprint 1: clone because the body can be read once
        caches.open(CACHE_NAME).then(function (cache) { // TEP Sprint 1: write-through to the versioned cache
          cache.put(request, copy); // TEP Sprint 1: remember this static GET for later
        });
        return response; // TEP Sprint 1: give the page the live network body
      });
    })
  );
});
