// Adblock detector — runs on every page via the app layout. If we believe an
// extension is suppressing our ads, lock the page behind a modal asking the
// user to disable the blocker and reload. We re-check on visibility-change so
// disabling + switching back without a manual reload also clears the modal.
(function () {
  const STORAGE_DISMISSED_KEY = '__tanbat_adblock_dismissed_v1';

  // ─────────── Detection ───────────
  // Bait element using the heaviest concentration of class names every major
  // filter list (EasyList, EasyPrivacy, uBO defaults, ABP defaults) collapses
  // on sight. We also give it an id and data-ad-* attributes that hit element
  // hiding cosmetic rules.
  function baitBlocked() {
    const bait = document.createElement('div');
    bait.id = 'AdContainer';
    bait.className = 'adsbox ad-banner ad-placement ad-unit ads ad-slot adsbygoogle ad-zone pub_300x250 pub_300x250m pub_728x90 text-ad textAd text_ad text-ads text-ad-links advertisement banner_ad';
    bait.setAttribute('data-ad-client', 'ca-pub-0000000000000000');
    bait.setAttribute('data-ad-slot',   '0000000000');
    bait.setAttribute('aria-hidden', 'true');
    bait.style.cssText = 'position:absolute!important;left:-9999px!important;top:-9999px!important;width:300px!important;height:250px!important;display:block!important;visibility:visible!important;';
    bait.innerHTML = '&nbsp;';
    document.body.appendChild(bait);

    return new Promise((resolve) => {
      // Give the blocker a generous tick to act on the element. 120ms was too
      // tight for AdBlock Plus on first paint; 350ms catches it reliably.
      setTimeout(() => {
        const cs = window.getComputedStyle(bait);
        const blocked =
          !bait.isConnected ||
          bait.offsetParent === null ||
          bait.offsetHeight === 0 ||
          bait.clientHeight === 0 ||
          bait.offsetWidth  === 0 ||
          cs.display === 'none' ||
          cs.visibility === 'hidden' ||
          cs.opacity === '0';
        bait.remove();
        resolve(blocked);
      }, 350);
    });
  }

  // Network probe against a URL universally present in every blocker's default
  // filter set — Google's pagead/show_ads.js. ABP, uBO, AdGuard and Brave all
  // drop this regardless of Acceptable Ads settings.
  function networkBlocked(url, timeoutMs = 2500) {
    return new Promise((resolve) => {
      const probe = document.createElement('script');
      probe.src = url;
      probe.async = true;
      let settled = false;
      const done = (blocked) => {
        if (settled) return;
        settled = true;
        probe.remove();
        resolve(blocked);
      };
      probe.onload  = () => done(false);
      probe.onerror = () => done(true);
      document.head.appendChild(probe);
      // Timeout is inconclusive, not proof of blocking — a slow network or a
      // busy ad server shouldn't get a legitimate visitor locked out.
      setTimeout(() => done(false), timeoutMs);
    });
  }

  // Fetch-based probe — some blockers (esp. uBO with strict network rules)
  // cancel fetch() to ad domains before they ever hit the wire. This catches
  // them even when the <script> probe slips through.
  async function fetchBlocked(url, timeoutMs = 2500) {
    try {
      const ctrl = new AbortController();
      const t = setTimeout(() => ctrl.abort(), timeoutMs);
      await fetch(url, { method: 'HEAD', mode: 'no-cors', cache: 'no-store', signal: ctrl.signal });
      clearTimeout(t);
      // no-cors gives us an opaque response; reaching here at all means the
      // network call was not blocked at the extension layer.
      return false;
    } catch (_) {
      return true;
    }
  }

  async function detect() {
    // Run every probe concurrently so the detection budget is the slowest one,
    // not the sum.
    const signals = await Promise.all([
      baitBlocked(),
      networkBlocked('https://pagead2.googlesyndication.com/pagead/show_ads.js'),
      networkBlocked('https://securepubads.g.doubleclick.net/tag/js/gpt.js'),
      fetchBlocked('https://static.doubleclick.net/instream/ad_status.js'),
    ]);
    // Require agreement from at least TWO independent signals before locking the
    // page. A single positive is too aggressive — a privacy browser quirk, a
    // flaky probe or a cosmetic-only extension can trip one on its own. A real
    // ad-blocker trips three or four of these at once.
    return signals.filter(Boolean).length >= 2;
  }

  // ─────────── Modal ───────────
  function showBanner() {
    if (document.getElementById('adblock-banner')) return;
    const wrap = document.createElement('div');
    wrap.id = 'adblock-banner';
    wrap.setAttribute('role', 'dialog');
    wrap.setAttribute('aria-modal', 'true');
    wrap.setAttribute('aria-labelledby', 'adblock-banner-title');
    wrap.innerHTML = `
      <div class="adblock-backdrop"></div>
      <div class="adblock-card">
        <div class="adblock-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 9v4"/><path d="M12 17h.01"/>
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          </svg>
        </div>
        <h2 id="adblock-banner-title" class="adblock-title">Please disable your ad-blocker</h2>
        <p class="adblock-text">
          We run this platform for free, supported by ads. To keep it that way,
          please disable your ad-blocker for this site and reload the page.
        </p>
        <button type="button" class="adblock-btn" id="adblock-reload">Reload page</button>
        <p class="adblock-hint">Thank you for supporting Tanbat.</p>
      </div>
    `;
    document.body.appendChild(wrap);
    document.body.classList.add('adblock-locked');
    document.getElementById('adblock-reload')?.addEventListener('click', () => {
      location.reload();
    });
  }

  function hideBanner() {
    document.getElementById('adblock-banner')?.remove();
    document.body.classList.remove('adblock-locked');
  }

  // ─────────── Boot ───────────
  async function run() {
    try {
      if (sessionStorage.getItem(STORAGE_DISMISSED_KEY) === '1') return;
    } catch (_) {}

    if (await detect()) {
      showBanner();
    }

    document.addEventListener('visibilitychange', async () => {
      if (document.visibilityState !== 'visible') return;
      if (!document.getElementById('adblock-banner')) return;
      if (!(await detect())) hideBanner();
    });
  }

  // Only run after the page has fully loaded, plus a short grace period so our
  // own ad slots and probes have a chance to settle first. Running during the
  // initial paint raced real ad-network requests and produced false positives.
  const GRACE_MS = 2000;
  function boot() {
    setTimeout(run, GRACE_MS);
  }
  if (document.readyState === 'complete') {
    boot();
  } else {
    window.addEventListener('load', boot, { once: true });
  }
})();
