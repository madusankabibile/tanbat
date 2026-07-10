{{-- Traffic tab. The only tab backed entirely by `visitor_page_views`, so it is
     the only one that can honour a full 90-day window. --}}

<div class="strip">
  <div class="kpi">
    <div class="kpi-label">Page views</div>
    <div class="kpi-value">{{ number_format($traffic['views']) }}</div>
    <div class="kpi-foot">@include('admin.partials._trend', ['t' => $traffic['views_trend'], 'window' => $days])</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Unique visitors</div>
    <div class="kpi-value">{{ number_format($traffic['uniques']) }}</div>
    <div class="kpi-foot">@include('admin.partials._trend', ['t' => $traffic['uniques_trend'], 'window' => $days])</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Views per visitor</div>
    <div class="kpi-value">{{ number_format($traffic['views_per_visitor'], 1) }}</div>
    <div class="kpi-foot"><span>pages seen per unique visitor</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Countries</div>
    <div class="kpi-value">{{ number_format($traffic['countries_count']) }}</div>
    <div class="kpi-foot"><span>reached in the last {{ $visitorTableDays }} days</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Automated traffic</div>
    <div class="kpi-value">{{ number_format($traffic['bot_share'], 1) }}%</div>
    <div class="kpi-foot">
      <span class="kpi-figure">{{ number_format($traffic['bot_visitors']) }}</span>
      <span>of {{ number_format($traffic['total_visitors']) }} visitors are bots</span>
    </div>
  </div>
</div>

<div class="mt-4 panel">
  <div class="panel__head">
    <div>
      <div class="panel__title">Traffic</div>
      <div class="panel__sub">Page views and unique visitors per day, last {{ $days }} days</div>
    </div>
    <div class="legend">
      <span class="legend__item">
        <span class="legend__key" style="border-top: 2px solid var(--pine);"></span>
        <span class="legend__name">Views</span>
        <span class="legend__total">{{ number_format($traffic['views']) }}</span>
      </span>
      <span class="legend__item">
        <span class="legend__key" style="border-top: 2px dashed var(--brass);"></span>
        <span class="legend__name">Uniques</span>
        <span class="legend__total">{{ number_format($traffic['uniques']) }}</span>
      </span>
    </div>
  </div>
  <div class="panel__body">
    <div class="chart-wrap"><canvas id="chartTraffic"></canvas></div>
  </div>
</div>

<div class="mt-4 panel">
  <div class="panel__head">
    <div>
      <div class="panel__title">Top pages</div>
      <div class="panel__sub">Views and unique visitors per path, last {{ $days }} days</div>
    </div>
  </div>
  <div class="panel__body">
    @php $maxPageViews = collect($topPages)->max('views') ?: 1; @endphp
    @forelse($topPages as $page)
      <div class="barline">
        <div class="barline__head">
          <span class="barline__name"><span class="mono">{{ $page['path'] }}</span></span>
          <span class="barline__val">
            {{ number_format($page['views']) }}
            <span class="barline__sub">/ {{ number_format($page['uniques']) }} uniq</span>
          </span>
        </div>
        <div class="barline__track">
          <div class="barline__fill" style="width: {{ max(round($page['views'] / $maxPageViews * 100), 1) }}%"></div>
        </div>
      </div>
    @empty
      <div class="empty">
        <div class="empty__title">No page views recorded yet</div>
        <div class="empty__hint">Views are counted from the next public page load.</div>
      </div>
    @endforelse
  </div>
</div>

@push('scripts')
<script>
(function () {
  var canvas = document.getElementById('chartTraffic');
  if (!canvas || typeof Chart === 'undefined' || !window.Ledger) return;

  var series = @json($trafficSeries);

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: series.map(function (p) { return Ledger.label(p.date); }),
      datasets: [
        Ledger.line({
          label: 'Views',
          data: series.map(function (p) { return p.views; }),
          borderColor: Ledger.PINE,
          backgroundColor: Ledger.PINE,
        }),
        // Dashed, so the two series stay distinguishable without relying on hue
        // alone (colour-vision deficiency, greyscale printing).
        Ledger.line({
          label: 'Uniques',
          data: series.map(function (p) { return p.uniques; }),
          borderColor: Ledger.BRASS,
          backgroundColor: Ledger.BRASS,
          borderDash: [4, 3],
        }),
      ],
    },
    options: Ledger.options(),
  });
})();
</script>
@endpush
