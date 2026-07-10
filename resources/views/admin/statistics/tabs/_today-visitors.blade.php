{{-- Body of the "Visitors today" panel. Rendered both inside the Today tab and,
     on its own, by StatisticsController@live for the poller. --}}
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
