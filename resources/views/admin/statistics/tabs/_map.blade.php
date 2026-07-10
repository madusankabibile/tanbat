{{-- Map tab: a choropleth of visitors by country.

     The SVG is self-hosted (public/maps/world.svg) and fetched on demand rather
     than inlined — it is ~1.2MB of path data, which has no business sitting in
     the HTML of the other seven tabs. Countries are shaded by a five-step pine
     ramp; a country with no visitors keeps the neutral "no data" fill.

     The Countries tab is the exact, screen-reader-friendly equivalent of this
     view: a map communicates distribution, a table communicates values. --}}

@php
  /* Only real ISO countries can be shaded. 'XX' (unknown) has no shape on the
     map, so it is reported beneath it rather than silently dropped. */
  $shaded  = collect($mapCountries)->filter(fn ($c) => \App\Services\CountryFlag::exists($c['code']));
  $unknown = collect($mapCountries)->reject(fn ($c) => \App\Services\CountryFlag::exists($c['code']));

  $mapData = $shaded->mapWithKeys(fn ($c) => [strtolower($c['code']) => [
      'n' => $c['name'],
      'v' => $c['visitors'],
      'h' => $c['hits'],
  ]])->all();

  $maxVisitors    = $shaded->max('visitors') ?: 0;
  $unknownVisitors = (int) $unknown->sum('visitors');
@endphp

<div class="panel">
  <div class="panel__head">
    <div>
      <div class="panel__title">Where visitors are</div>
      <div class="panel__sub">
        {{ number_format($shaded->sum('visitors')) }} visitors across
        {{ number_format($shaded->count()) }} {{ Str::plural('country', $shaded->count()) }},
        last {{ $visitorTableDays }} days
      </div>
    </div>
    <a href="{{ route('admin.statistics.index', ['tab' => 'countries', 'days' => $days]) }}" class="panel__link">See table</a>
  </div>

  <div class="panel__body">
    @if($shaded->isEmpty())
      <div class="empty">
        <div class="empty__title">No countries to plot</div>
        <div class="empty__hint">Countries are resolved from each visitor's IP address.</div>
      </div>
    @else
      {{-- HEX_APOS/HEX_QUOT keep a name like "Côte d'Ivoire" from closing the
           single-quoted attribute; HEX_TAG/HEX_AMP keep it out of HTML parsing. --}}
      <div class="map"
           id="worldMap"
           data-src="{{ asset('maps/world.svg') }}"
           data-max="{{ $maxVisitors }}"
           data-countries='@json($mapData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)'>
        <div class="map__canvas"></div>
        <div class="map__status" role="status">Loading map…</div>
      </div>

      <div class="mt-4 map__legend">
        <span class="map__legend-label">1</span>
        <span class="map__ramp">
          <span class="map__swatch" style="background: #E7F2EE;"></span>
          <span class="map__swatch" style="background: #C2DED5;"></span>
          <span class="map__swatch" style="background: #8FC2B3;"></span>
          <span class="map__swatch" style="background: #4E9C86;"></span>
          <span class="map__swatch" style="background: #07775A;"></span>
        </span>
        <span class="map__legend-label">{{ number_format($maxVisitors) }}</span>
        <span class="map__legend-label" style="margin-left:.25rem;">visitors</span>
        <span class="map__key" style="margin-left:1rem;"><i></i> No visitors</span>
      </div>

      @if($unknownVisitors > 0)
        <div class="note">
          {{ number_format($unknownVisitors) }}
          {{ Str::plural('visitor', $unknownVisitors) }}
          could not be resolved to a country (private/local IPs, or a lookup that failed)
          and {{ $unknownVisitors === 1 ? 'is' : 'are' }} not drawn on the map.
        </div>
      @endif

      <div class="note">
        Shading is on a square-root scale, so a single dominant country does not flatten
        every other one into the lightest band. Map outlines:
        <a href="https://github.com/VictorCazanave/svg-maps" rel="noopener noreferrer" target="_blank">svg-maps</a>
        (CC BY 4.0).
      </div>
    @endif
  </div>
</div>

@push('scripts')
<script>
(function () {
  var root = document.getElementById('worldMap');
  if (!root) return;

  var canvas = root.querySelector('.map__canvas');
  var status = root.querySelector('.map__status');
  var data   = JSON.parse(root.dataset.countries);
  var max    = parseInt(root.dataset.max, 10) || 0;

  /* Square-root scale: visitor counts are heavily skewed (one country often has
     most of the traffic), and a linear ramp would push everything else into the
     lightest band. Levels are 1..5, matching .lvl-N in the stylesheet. */
  function level(value) {
    if (value <= 0 || max <= 0) return 0;
    var step = Math.ceil(Math.sqrt(value / max) * 5);
    return Math.min(5, Math.max(1, step));
  }

  function fail(message) {
    status.textContent = message;
  }

  fetch(root.dataset.src, { credentials: 'same-origin' })
    .then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text();
    })
    .then(function (svg) {
      canvas.innerHTML = svg;
      status.remove();

      var el = canvas.querySelector('svg');
      if (!el) throw new Error('no <svg> in response');
      el.setAttribute('preserveAspectRatio', 'xMidYMid meet');
      // Decorative: the Countries tab carries the same numbers as real text.
      el.setAttribute('role', 'img');
      el.setAttribute('aria-label', 'World map shaded by visitors per country');

      Object.keys(data).forEach(function (code) {
        // Country ids in world.svg are lowercase ISO 3166-1 alpha-2, the same
        // codes VisitorGeo stores, so no mapping table is needed.
        var path = el.querySelector('#' + CSS.escape(code));
        if (!path) return;

        var row = data[code];
        path.classList.add('lvl-' + level(row.v));
        path.setAttribute('data-v', row.v);
        path.setAttribute('data-n', row.n);
        path.setAttribute('data-h', row.h);
      });

      attachTooltip(el);
    })
    .catch(function (e) {
      fail('Map could not be loaded (' + e.message + '). The Countries tab has the same data.');
    });

  function attachTooltip(el) {
    var tip = document.createElement('div');
    tip.className = 'map__tip';
    document.body.appendChild(tip);

    function hide() { tip.style.opacity = '0'; }

    el.addEventListener('mousemove', function (e) {
      var path = e.target.closest('path[data-v]');
      if (!path) { hide(); return; }

      tip.innerHTML = '<b>' + path.getAttribute('data-n') + '</b><br>'
        + path.getAttribute('data-v') + ' visitors · '
        + path.getAttribute('data-h') + ' hits';
      tip.style.opacity = '1';

      // Keep the tip inside the viewport near the right/bottom edges.
      var box = tip.getBoundingClientRect();
      var x = e.clientX + 14;
      var y = e.clientY + 14;
      if (x + box.width  > window.innerWidth  - 8) x = e.clientX - box.width  - 14;
      if (y + box.height > window.innerHeight - 8) y = e.clientY - box.height - 14;
      tip.style.left = x + 'px';
      tip.style.top  = y + 'px';
    });

    el.addEventListener('mouseleave', hide);
    window.addEventListener('scroll', hide, { passive: true });
  }
})();
</script>
@endpush
