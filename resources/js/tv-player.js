/* ─────────────────────────────────────────────────────────────────────────────
   TV player — /tv/{slug}

   Two jobs:

   1. PLAY. The page ships with no stream URL in it. We POST for a playback
      session, get back a signed proxy manifest, and hand that to hls.js (or to
      the browser's native HLS on Safari/iOS). Everything hls.js then requests —
      variants, segments, keys — is a same-origin proxy URL, because the server
      rewrote the manifest before we ever saw it.

   2. DISCOURAGE CAPTURE. Devtools shortcuts and the context menu are swallowed,
      and playback is curtained when devtools looks open.

      Be clear-eyed about #2: it is a speed bump, not a lock. Anyone who can run
      JS in the page — or who opens devtools before the script binds, or disables
      JS entirely — gets past it. The real protection is server-side: the origin
      m3u8 never leaves the server, and the proxy URLs expire and are bound to
      one session. This layer just stops the one-click "grab this stream"
      extensions, which read the DOM and network log for an .m3u8 and find only
      a blob: URL and short-lived proxy paths.
   ──────────────────────────────────────────────────────────────────────────── */

const root = document.querySelector('[data-tv-player]');

if (root) {
  const video    = root.querySelector('[data-tv-video]');
  const overlay  = root.querySelector('[data-tv-overlay]');
  const statusEl = root.querySelector('[data-tv-status]');
  const spinner  = root.querySelector('[data-tv-spinner]');
  const retryBtn = root.querySelector('[data-tv-retry]');
  const playBtn  = root.querySelector('[data-tv-play]');
  const liveDot  = document.querySelector('[data-tv-live]');

  const sessionUrl = root.dataset.session;
  const csrf       = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const protect    = {
    shortcuts: root.dataset.blockShortcuts === '1',
    devtools:  root.dataset.detectDevtools === '1',
    debugger:  root.dataset.debuggerTrap === '1',
  };

  let hls = null;
  let destroyed = false;

  /* ───────── status chrome ───────── */

  function setStatus(text, { busy = false, retry = false, play = false } = {}) {
    if (statusEl) statusEl.textContent = text || '';
    if (spinner)  spinner.hidden = !busy;
    if (retryBtn) retryBtn.hidden = !retry;
    if (playBtn)  playBtn.hidden  = !play;
    if (overlay)  overlay.hidden = !text;
  }

  function clearStatus() {
    setStatus('');
  }

  function markLive(on) {
    liveDot?.classList.toggle('is-live', !!on);
  }

  // Autoplay with sound is blocked until the viewer interacts. Ask for that one
  // click explicitly — the overlay is on top of the native controls, so telling
  // the viewer to "press play" without giving them a button is a dead end.
  function promptPlay() {
    markLive(false);
    setStatus('Press play to start watching.', { play: true });
  }

  /* ───────── session + playback ───────── */

  /** Ask the server for a fresh, signed, session-bound manifest URL. */
  async function requestSource() {
    const res = await fetch(sessionUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    });

    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      throw new Error(body.message || `Playback unavailable (${res.status}).`);
    }
    return (await res.json()).src;
  }

  /** Wait for the hls.js CDN bundle, which is loaded with `defer`. */
  function whenHlsReady(timeoutMs = 8000) {
    if (window.Hls) return Promise.resolve(window.Hls);

    return new Promise((resolve) => {
      const started = Date.now();
      const tick = setInterval(() => {
        if (window.Hls || Date.now() - started > timeoutMs) {
          clearInterval(tick);
          resolve(window.Hls || null);
        }
      }, 60);
    });
  }

  async function start() {
    if (destroyed) return;
    setStatus('Connecting to the channel…', { busy: true });

    let src;
    try {
      src = await requestSource();
    } catch (e) {
      setStatus(e.message, { retry: true });
      return;
    }

    const Hls = await whenHlsReady();

    // Safari/iOS play HLS natively and hls.js won't run there — the src is our
    // proxy URL either way, so the origin still never appears.
    if ((!Hls || !Hls.isSupported()) && video.canPlayType('application/vnd.apple.mpegurl')) {
      video.src = src;
      video.play().catch(promptPlay);
      return;
    }

    if (!Hls || !Hls.isSupported()) {
      setStatus('This browser can’t play live streams.', {});
      return;
    }

    hls?.destroy();
    hls = new Hls({
      // Live defaults: start near the edge, keep the buffer modest so a
      // reconnect doesn't replay minutes of stale video.
      lowLatencyMode: true,
      backBufferLength: 30,
      maxBufferLength: 20,
      manifestLoadingMaxRetry: 4,
      levelLoadingMaxRetry: 4,
      fragLoadingMaxRetry: 6,
      // Same-origin proxy — send the session cookie so token binding resolves.
      xhrSetup: (xhr) => { xhr.withCredentials = true; },
    });

    hls.on(Hls.Events.MANIFEST_PARSED, () => {
      clearStatus();
      markLive(true);
      video.play().catch(promptPlay);
    });

    hls.on(Hls.Events.ERROR, async (_evt, data) => {
      if (!data.fatal) return;

      // An expired or rejected token surfaces as a 403 on the manifest. Mint a
      // new session and reload rather than surfacing an error to the viewer.
      const status = data.response?.code;
      if (data.type === Hls.ErrorTypes.NETWORK_ERROR && (status === 403 || status === 419)) {
        try {
          hls.loadSource(await requestSource());
          hls.startLoad();
          return;
        } catch { /* fall through to the generic handling below */ }
      }

      switch (data.type) {
        case Hls.ErrorTypes.NETWORK_ERROR:
          markLive(false);
          setStatus('Reconnecting…', { busy: true });
          hls.startLoad();
          break;
        case Hls.ErrorTypes.MEDIA_ERROR:
          hls.recoverMediaError();
          break;
        default:
          markLive(false);
          setStatus('This channel is not responding right now.', { retry: true });
          hls.destroy();
          hls = null;
      }
    });

    hls.loadSource(src);
    hls.attachMedia(video);
  }

  retryBtn?.addEventListener('click', () => start());

  // The click that starts playback has to come from inside the overlay: it
  // covers the whole frame, so the native play control underneath is not
  // reachable while a message is showing.
  playBtn?.addEventListener('click', () => {
    clearStatus();
    video.play().then(() => markLive(true)).catch(promptPlay);
  });

  // Only surface "Buffering…" if the stall actually lasts. A live stream dips
  // in and out of `waiting` constantly, and flashing a dark overlay on every
  // one of those reads as breakage rather than as buffering.
  let bufferTimer = null;
  video.addEventListener('waiting', () => {
    clearTimeout(bufferTimer);
    bufferTimer = setTimeout(() => setStatus('Buffering…', { busy: true }), 700);
  });
  video.addEventListener('playing', () => {
    clearTimeout(bufferTimer);
    clearStatus();
    markLive(true);
  });
  video.addEventListener('error', () => markLive(false));

  // Free the upstream connection when the tab is closed or navigated away, so
  // we aren't proxying segments for a player nobody is watching.
  window.addEventListener('pagehide', () => { destroyed = true; hls?.destroy(); });

  start();

  /* ───────── capture deterrents ───────── */

  // No "Save video as…" / "Copy video address" on the player. (With MSE the
  // address is a blob: URL anyway, but the menu invites the attempt.)
  root.addEventListener('contextmenu', (e) => e.preventDefault());

  if (protect.shortcuts) {
    document.addEventListener('keydown', (e) => {
      const k = (e.key || '').toLowerCase();
      const devtools =
        e.key === 'F12' ||
        (e.ctrlKey && e.shiftKey && ['i', 'j', 'c'].includes(k)) ||
        (e.metaKey && e.altKey && ['i', 'j', 'c'].includes(k)) ||   // macOS
        (e.ctrlKey && ['u', 's'].includes(k));                       // view-source / save

      if (devtools) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);
  }

  if (protect.devtools) {
    // Heuristic: docked devtools steal viewport from the window, so a large,
    // sustained gap between outer and inner size means a panel is open. It has
    // false negatives (undocked windows) and we tolerate them — this is a
    // deterrent, and a false POSITIVE that curtains a legitimate viewer is the
    // more costly mistake, hence the generous threshold.
    const GAP = 180;
    let curtained = false;
    // Only resume what we paused — a viewer who hit pause themselves should
    // still be paused when the curtain lifts.
    let pausedByUs = false;

    const check = () => {
      const wide = window.outerWidth  - window.innerWidth  > GAP;
      const tall = window.outerHeight - window.innerHeight > GAP;
      const open = wide || tall;

      if (open === curtained) return;
      curtained = open;

      root.classList.toggle('is-curtained', open);
      if (open) {
        pausedByUs = !video.paused;
        video.pause();
        setStatus('Playback paused. Close developer tools to continue watching.', {});
      } else {
        clearStatus();
        if (pausedByUs) video.play().catch(() => {});
        pausedByUs = false;
      }
    };

    setInterval(check, 1200);
    window.addEventListener('resize', check);
  }

  if (protect.debugger) {
    // The classic trap: a `debugger` statement costs microseconds normally and
    // blocks indefinitely with devtools open. Off by default — it makes the tab
    // unusable for anyone who has devtools open for unrelated reasons.
    setInterval(() => {
      const t0 = performance.now();
      // eslint-disable-next-line no-debugger
      debugger;
      if (performance.now() - t0 > 120) {
        video.pause();
        root.classList.add('is-curtained');
        setStatus('Playback paused. Close developer tools to continue watching.', {});
      }
    }, 2000);
  }
}
