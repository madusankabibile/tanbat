{{-- Countries tab: the accessible, exact companion to the Map tab. --}}

<div class="panel">
  <div class="panel__head">
    <div>
      <div class="panel__title">Countries</div>
      <div class="panel__sub">Visitors seen in the last {{ $visitorTableDays }} days</div>
    </div>
    <a href="{{ route('admin.statistics.index', ['tab' => 'map', 'days' => $days]) }}" class="panel__link">See map</a>
  </div>
  <div class="panel__body">
    @php $maxCountry = collect($countries)->max('visitors') ?: 1; @endphp
    @forelse($countries as $c)
      <div class="barline">
        <div class="barline__head">
          <span class="barline__name">
            @include('admin.partials._flag', ['code' => $c['code'], 'label' => $c['name']])
            {{ $c['name'] }} <span class="flag">{{ $c['code'] }}</span>
          </span>
          <span class="barline__val">
            {{ number_format($c['visitors']) }}
            <span class="barline__sub">/ {{ number_format($c['hits']) }} hits</span>
          </span>
        </div>
        <div class="barline__track">
          <div class="barline__fill" style="width: {{ max(round($c['visitors'] / $maxCountry * 100), 1) }}%"></div>
        </div>
      </div>
    @empty
      <div class="empty">
        <div class="empty__title">No visitors yet</div>
        <div class="empty__hint">Countries are resolved from the visitor's IP address.</div>
      </div>
    @endforelse

    <div class="note">
      <b>hits</b> is each visitor's all-time view count, not their views inside this window,
      so this ranks countries by engagement rather than by traffic.
    </div>
  </div>
</div>
