{{-- Today tab. Everything here is scoped to the current calendar day, and it is
     the only tab that refreshes itself: its numbers change minute to minute,
     while every other tab is a multi-day aggregate that does not.

     There is deliberately no hourly chart: `visitor_page_views` buckets by day,
     so an hour-by-hour line would be invented rather than measured. What can be
     answered exactly is who is on the site right now, from `visitors.updated_at`.

     The poller swaps in HTML rendered by the same partials the server used, so
     there is no second copy of this markup living in JavaScript. --}}

<div class="live" id="liveBar"
     data-endpoint="{{ route('admin.statistics.live') }}"
     data-interval="15000">
  <button type="button" class="live__toggle" id="liveToggle" aria-pressed="true">
    <span class="live__dot" aria-hidden="true"></span>
    <span class="live__word">Live</span>
  </button>
  <span class="live__stamp" id="liveStamp" aria-live="polite">updated just now</span>
  <noscript><span class="live__stamp">enable JavaScript to refresh automatically</span></noscript>
</div>

<div class="strip mt-2">
  <div class="kpi">
    <div class="kpi-label">Page views</div>
    <div class="kpi-value" data-live-value="views">{{ number_format($today['views']) }}</div>
    <div class="kpi-foot" data-live-html="views_trend">
      @include('admin.partials._trend', [
        't' => $today['views_trend'], 'currentText' => 'today', 'compareText' => 'vs yesterday',
      ])
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Unique visitors</div>
    <div class="kpi-value" data-live-value="uniques">{{ number_format($today['uniques']) }}</div>
    <div class="kpi-foot" data-live-html="uniques_trend">
      @include('admin.partials._trend', [
        't' => $today['uniques_trend'], 'currentText' => 'today', 'compareText' => 'vs yesterday',
      ])
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">New visitors</div>
    <div class="kpi-value" data-live-value="new_visitors">{{ number_format($today['new_visitors']) }}</div>
    <div class="kpi-foot"><span>first seen today</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Returning</div>
    <div class="kpi-value" data-live-value="returning">{{ number_format($today['returning']) }}</div>
    <div class="kpi-foot"><span>seen here before today</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Here now</div>
    <div class="kpi-value" data-live-value="live">{{ number_format($today['live']) }}</div>
    <div class="kpi-foot"><span>active in the last {{ $today['live_minutes'] }} minutes</span></div>
  </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Today's pages</div>
        <div class="panel__sub">Views and unique visitors per path, today only</div>
      </div>
    </div>
    <div class="panel__body" data-live-html="pages">
      @include('admin.statistics.tabs._today-pages')
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Visitors today</div>
        <div class="panel__sub">Most recently active first</div>
      </div>
      <a href="{{ route('admin.statistics.index', ['tab' => 'log', 'days' => $days]) }}" class="panel__link">Full log</a>
    </div>
    <div class="panel__body--flush" data-live-html="visitors">
      @include('admin.statistics.tabs._today-visitors')
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var bar = document.getElementById('liveBar');
  if (!bar || !window.fetch) return;

  var endpoint = bar.dataset.endpoint;
  var baseWait = parseInt(bar.dataset.interval, 10) || 15000;
  var MAX_WAIT = 120000;   // error backoff ceiling

  var toggle = document.getElementById('liveToggle');
  var stamp  = document.getElementById('liveStamp');

  var wait      = baseWait;
  var timer     = null;
  var inFlight  = null;
  var lastFetch = Date.now();
  var paused    = localStorage.getItem('adminStatsLivePaused') === '1';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ── Painting ─────────────────────────────────────────── */

  function flash(el) {
    if (reduceMotion) return;
    el.classList.remove('is-fresh');
    void el.offsetWidth;          // restart the animation
    el.classList.add('is-fresh');
  }

  function paint(payload) {
    Object.keys(payload.values || {}).forEach(function (key) {
      var el = bar.parentNode.querySelector('[data-live-value="' + key + '"]');
      if (el && el.textContent.trim() !== payload.values[key]) {
        el.textContent = payload.values[key];
        flash(el);
      }
    });

    Object.keys(payload.html || {}).forEach(function (key) {
      var el = bar.parentNode.querySelector('[data-live-html="' + key + '"]');
      // Only touch the DOM when the markup actually changed: a blind innerHTML
      // write on every tick would kill text selection inside these panels.
      if (el && el.innerHTML.trim() !== payload.html[key].trim()) {
        el.innerHTML = payload.html[key];
      }
    });
  }

  /* ── Status line ──────────────────────────────────────── */

  function ago() {
    var secs = Math.round((Date.now() - lastFetch) / 1000);
    if (paused) return 'paused';
    if (secs < 5)  return 'updated just now';
    if (secs < 60) return 'updated ' + secs + 's ago';
    return 'updated ' + Math.round(secs / 60) + 'm ago';
  }

  function render() {
    stamp.textContent = ago();
    toggle.setAttribute('aria-pressed', paused ? 'false' : 'true');
    toggle.querySelector('.live__word').textContent = paused ? 'Paused' : 'Live';
    bar.classList.toggle('is-paused', paused);
  }

  /* ── Polling ──────────────────────────────────────────── */

  function schedule() {
    clearTimeout(timer);
    if (paused) return;
    timer = setTimeout(poll, wait);
  }

  function poll() {
    // Never stack requests: a slow response must not queue another behind it.
    if (inFlight) inFlight.abort();
    inFlight = new AbortController();

    fetch(endpoint, {
      signal: inFlight.signal,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (payload) {
        paint(payload);
        lastFetch = Date.now();
        wait = baseWait;             // recovered — back to the normal cadence
        bar.classList.remove('is-stale');
        render();
        schedule();
      })
      .catch(function (e) {
        if (e.name === 'AbortError') return;
        // Back off rather than hammer a server that is already unhappy.
        wait = Math.min(wait * 2, MAX_WAIT);
        bar.classList.add('is-stale');
        stamp.textContent = 'reconnecting…';
        schedule();
      })
      .finally(function () { inFlight = null; });
  }

  /* ── Controls ─────────────────────────────────────────── */

  toggle.addEventListener('click', function () {
    paused = !paused;
    localStorage.setItem('adminStatsLivePaused', paused ? '1' : '0');
    render();
    if (paused) { clearTimeout(timer); if (inFlight) inFlight.abort(); }
    else poll();
  });

  // A backgrounded tab should not poll. Coming back, refresh immediately so the
  // reader never looks at a number that went stale while they were away.
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { clearTimeout(timer); return; }
    if (!paused) poll();
  });

  setInterval(function () { if (!bar.classList.contains('is-stale')) stamp.textContent = ago(); }, 5000);

  render();
  schedule();
})();
</script>
@endpush
