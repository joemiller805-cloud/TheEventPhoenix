/* TEP Sprint 1: root service worker — bump CACHE_VERSION to kill stale caches */
const CACHE_VERSION = 'v1.4.0'; // TEP kill-switch: bump so activate drops v1.3.0 and picks up 404 navigation → offline.html
const STATIC_CACHE = 'tep-static-' + CACHE_VERSION; // Versioned bucket for CSS/JS/fonts/images (Cache-First)
const API_CACHE = 'tep-api-' + CACHE_VERSION; // Versioned bucket for /data_access/ GET JSON (Network-First)
const OFFLINE_URL = '/offline.html'; // Static shell for document navigations when Apache is unreachable

const PRECACHE_URLS = [ // Small static set; never list PHP HTML or POST endpoints here
  '/manifest.json', // TEP PWA identity file
  '/favicon.ico', // Existing site favicon
  '/img/e.png', // Existing TEP mark used by the offline shell
  '/pwa/icon-192.png', // Manifest 192x192 any icon
  '/pwa/icon-512.png', // Manifest 512x512 any icon
  '/pwa/icon-maskable-512.png', // Manifest maskable icon
  '/offline.html', // Dedicated offline fallback; static HTML is allowed (PHP documents stay uncached)
  '/css/bootstrap/css/bootstrap.css', // Core stylesheet already loaded by commonStyles.php
  '/css/easyreg.css', // Existing app stylesheet
  '/js/jquery.js', // Existing jQuery; do not replace with a Node bundle
  '/js/angular1_7_2.js' // Existing AngularJS 1.x runtime — cache the file, do not upgrade it
];

function isStaticAsset(url) { // Cache-First allow-list: CSS, JS, fonts, images (and PWA json/ico)
  return /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|eot|json)$/i.test(url.pathname); // Extension allow-list; PHP HTML stays out
}

function isDataAccessRequest(url) { // Network-First target: AngularJS dataSvc GET /data_access/*.php
  return url.pathname.indexOf('/data_access/') === 0; // Prefix match so subpaths stay in the API strategy
}

function isSuccessfulGetResponse(response) { // Never cache 404/500 or opaques — only same-origin HTTP 200
  return !!(response && response.status === 200 && response.type === 'basic'); // 4xx/5xx/opaques are returned, not stored
}

function putInCache(cacheName, request, response) { // Write-through only after the success check
  if (!isSuccessfulGetResponse(response)) { // Skip 404/500 and non-basic responses
    return; // Failed bodies must not land in Cache Storage
  }
  const copy = response.clone(); // Clone because a Response body can be read once
  caches.open(cacheName).then(function (cache) { // Open the versioned bucket for this strategy
    cache.put(request, copy); // Store the successful GET for later Cache-First / Network-First fallback
  });
}

function cacheFirst(request) { // Static assets: memory/disk cache, then network
  return caches.match(request).then(function (cached) { // Look up the current STATIC_CACHE via match
    if (cached) { // Hit — serve without waiting on Apache
      return cached; // No AngularJS digest impact; CSS/JS/images only
    }
    return fetch(request).then(function (response) { // Miss — go to network
      putInCache(STATIC_CACHE, request, response); // Store only HTTP 200; 404/500 pass through uncached
      return response; // Give the page the live network body even when we do not cache it
    }).catch(function () { // Offline miss — no invented CSS/JS; let the request fail
      return Response.error(); // Static gaps stay errors; only document routes use the offline shell
    });
  });
}

function networkFirst(request) { // /data_access/: live JSON first, cached 200 only if the network fails
  return fetch(request).then(function (response) { // Always try Apache/PHP first so $scope gets fresh rows
    putInCache(API_CACHE, request, response); // Cache GET 200 only; never stash 404/500 query failures
    return response; // Return the network result (success or error) to dataSvc
  }).catch(function () { // Offline or network throw — fall back to last good JSON
    return caches.match(request).then(function (cached) { // Only previously successful 200s exist in API_CACHE
      return cached || Response.error(); // No stale 500s; empty cache yields a failed Response
    });
  });
}

function isHtmlNavigation(request) { // Document loads (/, /index.php, missing paths) — not XHR/dataSvc
  return request.mode === 'navigate' || request.destination === 'document'; // Navigate + document destination; AngularJS $http stays off this path
}

function matchOfflineShell() { // Precached /offline.html for failed or missing document routes
  return caches.match(OFFLINE_URL).then(function (cached) { // STATIC_CACHE entry from install
    return cached || Response.error(); // Fail closed if install skipped offline.html
  });
}

function networkThenOfflineShell(request) { // Live PHP first; never cache PHP HTML; shell for offline + missing routes
  return fetch(request).then(function (response) { // Try Apache/PHP without storing the document
    if (response && response.ok) { // HTTP 200–299 live PHP (index, landing, admin)
      return response; // Do not putInCache — PHP documents must not enter Cache Storage
    }
    if (response && response.status === 404) { // Missing navigation route (unknown path / missing PHP)
      return matchOfflineShell(); // Dedicated shell instead of Apache 404 or the browser error page
    }
    if (response && response.type === 'basic' && response.status > 0) { // Live 401/403/500 — keep Apache
      return response; // Auth and server errors stay network-only; not the offline copy
    }
    return matchOfflineShell(); // Opaque / status 0 / failed Response — treat as unavailable
  }).catch(function () { // Completely offline / network throw — not an HTTP error body
    return matchOfflineShell(); // Same precached shell as a missing route
  });
}

self.addEventListener('install', function (event) { // Precache static shell on first install / CACHE_VERSION bump
  event.waitUntil( // Hold install until precache finishes
    caches.open(STATIC_CACHE).then(function (cache) { // Seed the Cache-First bucket
      return Promise.all(PRECACHE_URLS.map(function (url) { // Add each file so one 404 cannot abort install
        return cache.add(url).catch(function () { /* Skip missing static files; keep CACHE_VERSION install alive */ }); // Fail-soft precache
      })); // Wait for every precache attempt (success or skip)
    }).then(function () { // Activate the new worker immediately when CACHE_VERSION changes
      return self.skipWaiting(); // Kill-switch takes effect without a second reload
    })
  );
});

self.addEventListener('activate', function (event) { // Drop cache buckets that do not match CACHE_VERSION
  event.waitUntil( // Finish cleanup before claiming clients
    caches.keys().then(function (keys) { // Inspect every Cache Storage bucket on this origin
      return Promise.all(keys.map(function (key) { // Decide keep vs delete per name
        const isTepCache = (key.indexOf('tep-static-') === 0 || key.indexOf('tep-api-') === 0); // Only TEP versioned buckets
        const isCurrent = (key === STATIC_CACHE || key === API_CACHE); // Keep this CACHE_VERSION's two buckets
        if (isTepCache && !isCurrent) { // Outdated tep-static-* or tep-api-* from a prior kill-switch
          return caches.delete(key); // Activation cleanup — remove stale versions
        }
      }));
    }).then(function () { // Take control of open pages so the new strategies are live
      return self.clients.claim(); // No AngularJS rewrite; pages keep their existing $scope
    })
  );
});

self.addEventListener('fetch', function (event) { // Route GET traffic: API Network-First, static Cache-First
  const request = event.request; // Local alias for readability
  if (request.method !== 'GET') { // Never intercept POST/PUT (CSRF, payments, logins)
    return; // Mutating requests always hit Apache uncached
  }
  const url = new URL(request.url); // Parse path for strategy selection
  if (url.origin !== self.location.origin) { // Do not cache CDN/third-party (gtag, jsdelivr)
    return; // Avoid mixing cross-origin bodies into TEP Cache Storage
  }
  if (url.pathname === '/sw.js') { // Never cache-first the worker file itself
    return; // Browser update checks must see a live sw.js after CACHE_VERSION bumps
  }
  if (isDataAccessRequest(url)) { // AngularJS dataSvc GET /data_access/getQueryResults.php
    event.respondWith(networkFirst(request)); // Network-First; cache only HTTP 200 JSON
    return; // Do not also run Cache-First on API URLs
  }
  if (url.pathname === OFFLINE_URL) { // Direct GET of the static shell (not PHP)
    event.respondWith(cacheFirst(request)); // Precached; available when Apache is unreachable
    return; // Do not treat this file as an unknown HTML type
  }
  if (isHtmlNavigation(request)) { // /, *.php, and unknown document URLs — Network then offline.html
    event.respondWith(networkThenOfflineShell(request)); // Live PHP 200 stays Apache; 404 and offline use /offline.html
    return; // Skip Cache-First so index.php is never stored
  }
  if (/\.php$/i.test(url.pathname)) { // Non-navigation PHP (legacy script GETs) stays network-only
    return; // Preserves AngularJS first paint and PHP sessions from live Apache
  }
  if (!isStaticAsset(url)) { // Unknown types stay network-only (navigations already handled above)
    return; // Do not Cache-First documents that carry $scope bootstraps
  }
  event.respondWith(cacheFirst(request)); // Cache-First for CSS, JS, fonts, and images
});

function tepSafeNotificationUrl(rawUrl) { // Block open redirects from push payloads
  var url = (rawUrl && String(rawUrl)) || '/'; // Default: dashboard
  if (url.charAt(0) !== '/') { // Only in-app relative paths
    return '/'; // Ignore https://evil.example payloads
  }
  if (url.indexOf('//') === 0) { // Protocol-relative still leaves the origin
    return '/'; // Keep the click on TEP
  }
  return url; // e.g. / or /index.php
}

self.addEventListener('push', function (event) { // Incoming Web Push — show a system notification
  var title = 'The Event Phoenix'; // TEP identity; payload may override
  var notifyOptions = { // Vanilla Notification options; no Workbox
    body: 'You have a new TEP update.', // Fallback body when the payload is empty
    icon: '/pwa/icon-192.png', // Square 192 from the validated manifest set
    badge: '/pwa/icon-192.png', // Compact badge uses the same mark
    data: { url: '/' } // notificationclick focuses/opens this path
  };
  if (event.data) { // PushMessageData is optional
    try {
      var payload = event.data.json(); // Expected { title, body, url }
      if (payload.title) { // Sender-supplied title
        title = String(payload.title); // Coerce so showNotification always gets a string
      }
      if (payload.body) { // Sender-supplied body
        notifyOptions.body = String(payload.body); // Coerce
      }
      if (payload.url) { // Deep-link for the click handler
        notifyOptions.data.url = tepSafeNotificationUrl(payload.url); // Same-origin relative only
      }
    } catch (parseErr) { // Non-JSON payload
      var asText = event.data.text(); // Plain-text body
      if (asText) { // Use the raw text when JSON parse fails
        notifyOptions.body = asText; // Still show a notification
      }
    }
  }
  event.waitUntil(self.registration.showNotification(title, notifyOptions)); // Display until the user dismisses or clicks
});

self.addEventListener('notificationclick', function (event) { // Focus an existing TEP window or open a new one
  event.notification.close(); // Dismiss the notification chrome
  var targetUrl = tepSafeNotificationUrl(event.notification.data && event.notification.data.url); // Same-origin path
  event.waitUntil( // Keep the SW alive until focus/open finishes
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) { // Include pages not yet claimed
      for (var i = 0; i < windowClients.length; i++) { // Prefer an already-open dashboard tab
        var client = windowClients[i]; // WindowClient
        if (client && 'focus' in client) { // Desktop browsers expose focus()
          return client.focus().then(function (focused) { // Bring TEP to the foreground
            if (focused && typeof focused.navigate === 'function' && targetUrl !== '/') { // Optional deep-link
              return focused.navigate(targetUrl); // Stay in the existing AngularJS tab
            }
            return focused; // Dashboard already visible
          });
        }
      }
      if (clients.openWindow) { // No existing tab — open a new one
        return clients.openWindow(targetUrl); // Loads index.php / PWA start_url
      }
    })
  );
});
