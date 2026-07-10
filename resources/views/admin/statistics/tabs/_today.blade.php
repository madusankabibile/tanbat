{{-- Today tab. Everything here is scoped to the current calendar day.

     There is deliberately no hourly chart: `visitor_page_views` buckets by day,
     so an hour-by-hour line would be invented rather than measured. What can be
     answered exactly is who is on the site right now, from `visitors.updated_at`. --}}

<div class="strip">
  <div class="kpi">
    <div class="kpi-label">Page views</div>
    <div class="kpi-value">{{ number_format($today['views']) }}</div>
    <div class="kpi-foot">
      @include('admin.partials._trend', [
        't' => $today['views_trend'], 'currentText' => 'today', 'compareText' => 'vs yesterday',
      ])
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Unique visitors</div>
    <div class="kpi-value">{{ number_format($today['uniques']) }}</div>
    <div class="kpi-foot">
      @include('admin.partials._trend', [
        't' => $today['uniques_trend'], 'currentText' => 'today', 'compareText' => 'vs yesterday',
      ])
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">New visitors</div>
    <div class="kpi-value">{{ number_format($today['new_visitors']) }}</div>
    <div class="kpi-foot"><span>first seen today</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Returning</div>
    <div class="kpi-value">{{ number_format($today['returning']) }}</div>
    <div class="kpi-foot"><span>seen here before today</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Here now</div>
    <div class="kpi-value">{{ number_format($today['live']) }}</div>
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
    <div class="panel__body">
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
    <div class="panel__body--flush">
      @forelse($liveVisitors as $v)
        @php $ua = \App\Services\UserAgentParser::parse($v->user_agent); @endphp
        <div class="row-item">
          @include('admin.partials._flag', ['code' => $v->country_code, 'label' => $v->country_name ?: 'Unknown'])
          <div class="row-item__body">
            <span class="row-item__name"><span class="mono">{{ $v->ip_address ?? '—' }}</span></span>
            <div class="row-item__meta">
              {{ $v->country_name ?: 'Unknown' }} ·
              <span class="mono">{{ $v->page ?: '—' }}</span>
            </div>
          </div>
          @if($ua['is_bot'])
            <span class="badge badge-user">bot</span>
          @else
            <span class="badge badge-type">{{ $ua['device'] }}</span>
          @endif
          <div class="row-item__num">
            <b>{{ $v->updated_at?->diffForHumans(null, true, true) }}</b>
            <span>Ago</span>
          </div>
        </div>
      @empty
        <div class="empty">
          <div class="empty__title">No visitors today</div>
          <div class="empty__hint">Visitors appear here as soon as a public page is loaded.</div>
        </div>
      @endforelse
    </div>
  </div>
</div>
