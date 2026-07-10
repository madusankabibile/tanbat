{{-- Body of the "Today's pages" panel. Rendered both inside the Today tab and,
     on its own, by StatisticsController@live so the poller swaps in exactly the
     markup the server would have produced on a full page load. --}}
@php $maxToday = collect($todayTopPages)->max('views') ?: 1; @endphp

@forelse($todayTopPages as $page)
  <div class="barline">
    <div class="barline__head">
      <span class="barline__name"><span class="mono">{{ $page['path'] }}</span></span>
      <span class="barline__val">
        {{ number_format($page['views']) }}
        <span class="barline__sub">/ {{ number_format($page['uniques']) }} uniq</span>
      </span>
    </div>
    <div class="barline__track">
      <div class="barline__fill" style="width: {{ max(round($page['views'] / $maxToday * 100), 1) }}%"></div>
    </div>
  </div>
@empty
  <div class="empty">
    <div class="empty__title">Nothing viewed yet today</div>
    <div class="empty__hint">The first public page load will appear here.</div>
  </div>
@endforelse
