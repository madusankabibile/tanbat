{{-- ─────────────────────────────────────────────────────────────────────────
     AdShield — Anti-Redirect, Anti-Popunder & Anti-History-Hijack Guard
     Blocks rogue ad networks from opening popunders on button clicks or
     hijacking the browser Back button, while allowing normal banner display.
──────────────────────────────────────────────────────────────────────────── --}}
<script>
(function () {
  'use strict';

  if (window.__AD_SHIELD_INSTALLED__) return;
  window.__AD_SHIELD_INSTALLED__ = true;

  // 1. List of known ad redirect networks, popunder brokers, and smartlink domains
  var BLOCKED_PATTERNS = [
    'effectivecpmnetwork.com',
    'highperformanceformat.com',
    'highperformancecpm.com',
    'cpmgate.com',
    'profitablegatecpm.com',
    'alwingulla.com',
    'adsterra.com',
    'propellerads.com',
    'popads.net',
    'trafficjunky',
    'monetag.com',
    'hilltopads',
    'onclickprediction',
    'onclickperformance',
    'onclickgateway',
    'syndication.exoclick',
    'exoclick.com',
    'popcash.net',
    'adcash.com',
    'zeroredirect',
    'smartlink',
    'directlink',
    'delivery'
  ];

  // Whitelisted social sharing and auth popups
  var ALLOWED_POPUP_HOSTS = [
    'twitter.com',
    'x.com',
    'facebook.com',
    'linkedin.com',
    'pinterest.com',
    'whatsapp.com',
    'telegram.me',
    't.me',
    'reddit.com',
    'accounts.google.com',
    'github.com'
  ];

  function isBlockedUrl(url) {
    if (!url) return false;
    var str = String(url).toLowerCase();
    for (var i = 0; i < BLOCKED_PATTERNS.length; i++) {
      if (str.indexOf(BLOCKED_PATTERNS[i]) !== -1) return true;
    }
    return false;
  }

  function isAllowedShare(url) {
    if (!url) return false;
    var str = String(url).toLowerCase();
    for (var i = 0; i < ALLOWED_POPUP_HOSTS.length; i++) {
      if (str.indexOf(ALLOWED_POPUP_HOSTS[i]) !== -1) return true;
    }
    return false;
  }

  // Track the most recently clicked DOM element and timestamp
  var lastClickedElement = null;
  var lastClickTime = 0;

  document.addEventListener('pointerdown', function (e) {
    lastClickedElement = e.target;
    lastClickTime = Date.now();
  }, { capture: true, passive: true });

  document.addEventListener('click', function (e) {
    lastClickedElement = e.target;
    lastClickTime = Date.now();
  }, { capture: true, passive: true });

  // ──────────────────────────────────────────────────────────────────────────
  // A. BLOCK UNAUTHORIZED WINDOW.OPEN (POPUNDERS ON BUTTON CLICKS)
  // ──────────────────────────────────────────────────────────────────────────
  var nativeWindowOpen = window.open;

  window.open = function (url, target, features) {
    var rawUrl = String(url || '').trim();

    // 1. Block empty or javascript: URLs
    if (!rawUrl || rawUrl.indexOf('javascript:') === 0) {
      console.warn('[AdShield] Blocked empty/javascript window.open');
      return null;
    }

    // 2. Block known ad redirect domains
    if (isBlockedUrl(rawUrl)) {
      console.warn('[AdShield] Blocked ad redirect/popunder window.open to:', rawUrl);
      return null;
    }

    // 3. Allow legitimate social share popups
    if (isAllowedShare(rawUrl)) {
      return nativeWindowOpen.call(window, url, target, features);
    }

    // 4. Allow same-origin URLs
    try {
      var parsed = new URL(rawUrl, window.location.origin);
      if (parsed.origin === window.location.origin) {
        return nativeWindowOpen.call(window, url, target, features);
      }
    } catch (e) {}

    // 5. Allow explicit user clicks on <a> with target="_blank"
    if (lastClickedElement) {
      var anchor = lastClickedElement.closest ? lastClickedElement.closest('a[target="_blank"]') : null;
      if (anchor && anchor.href && anchor.href === rawUrl) {
        // If anchor href is itself an ad redirect, block it
        if (isBlockedUrl(anchor.href)) {
          console.warn('[AdShield] Blocked ad anchor target="_blank":', anchor.href);
          return null;
        }
        return nativeWindowOpen.call(window, url, target, features);
      }
    }

    // 6. Allow legitimate countdown download buttons or messenger attachment clicks
    if (lastClickedElement && lastClickedElement.closest) {
      if (lastClickedElement.closest('[data-countdown]') || lastClickedElement.closest('.msg-attachment')) {
        return nativeWindowOpen.call(window, url, target, features);
      }
    }

    // 7. Check if user clicked an interactive application button or widget
    // Ad scripts attach click listeners to intercept button clicks and fire window.open()
    if (lastClickedElement && lastClickedElement.closest) {
      var isAppElement = lastClickedElement.closest(
        'button, .btn, .btn-primary, .btn-outline, .btn-ghost, input, textarea, select, [role="button"], .post-card, .comment, .nav-link, .tab-btn, .msg-input-bar, .modal-close'
      );
      if (isAppElement) {
        console.warn('[AdShield] Blocked popunder triggered during application button click:', rawUrl);
        return null;
      }
    }

    // 8. If click was within 400ms but URL is unknown cross-origin, block by default
    var elapsed = Date.now() - lastClickTime;
    if (elapsed < 400) {
      console.warn('[AdShield] Blocked unverified third-party popup:', rawUrl);
      return null;
    }

    return nativeWindowOpen.call(window, url, target, features);
  };

  // ──────────────────────────────────────────────────────────────────────────
  // B. BLOCK BACK-BUTTON HIJACKING (HISTORY TRAPS & POPSTATE REDIRECTS)
  // ──────────────────────────────────────────────────────────────────────────
  var isHandlingPopState = false;

  window.addEventListener('popstate', function () {
    isHandlingPopState = true;
    setTimeout(function () {
      isHandlingPopState = false;
    }, 800);
  }, { capture: true });

  window.addEventListener('hashchange', function () {
    isHandlingPopState = true;
    setTimeout(function () {
      isHandlingPopState = false;
    }, 800);
  }, { capture: true });

  // Guard window.location during popstate / back button navigation
  var nativeAssign = window.location.assign;
  var nativeReplace = window.location.replace;

  window.location.assign = function (url) {
    if (isHandlingPopState && isBlockedUrl(url)) {
      console.warn('[AdShield] Blocked back-button hijack via location.assign:', url);
      return;
    }
    if (isBlockedUrl(url)) {
      console.warn('[AdShield] Blocked ad redirect via location.assign:', url);
      return;
    }
    return nativeAssign.call(window.location, url);
  };

  window.location.replace = function (url) {
    if (isHandlingPopState && isBlockedUrl(url)) {
      console.warn('[AdShield] Blocked back-button hijack via location.replace:', url);
      return;
    }
    if (isBlockedUrl(url)) {
      console.warn('[AdShield] Blocked ad redirect via location.replace:', url);
      return;
    }
    return nativeReplace.call(window.location, url);
  };

  // Prevent history.pushState flood (anti-back-button trap)
  var nativePushState = history.pushState;
  var pushHistoryTimes = [];

  history.pushState = function (state, unused, url) {
    var now = Date.now();
    pushHistoryTimes = pushHistoryTimes.filter(function (t) { return now - t < 1500; });
    pushHistoryTimes.push(now);

    // If a third-party script tries to spam pushState to trap the back button (> 4 in 1.5s)
    if (pushHistoryTimes.length > 4) {
      console.warn('[AdShield] Blocked history.pushState spam (back-button trap)');
      return;
    }

    if (url && isBlockedUrl(url)) {
      console.warn('[AdShield] Blocked ad domain in history.pushState:', url);
      return;
    }

    return nativePushState.call(history, state, unused, url);
  };

  // Intercept EventTarget.prototype.addEventListener for 'popstate'
  var nativeAddEventListener = EventTarget.prototype.addEventListener;
  EventTarget.prototype.addEventListener = function (type, listener, options) {
    if (type === 'popstate' && typeof listener === 'function') {
      var safePopStateListener = function (event) {
        // Run safely without letting ad scripts trigger cross-origin location changes
        var savedHref = window.location.href;
        try {
          listener.call(this, event);
        } catch (err) {
          console.warn('[AdShield] Handled error in popstate listener:', err);
        }
      };
      return nativeAddEventListener.call(this, type, safePopStateListener, options);
    }

    return nativeAddEventListener.call(this, type, listener, options);
  };

  console.info('[AdShield] Active: popunders, back-button hijacking & redirect ads are blocked.');
})();
</script>
