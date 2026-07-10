{{-- Devices tab. Bots are counted, then held out of the device and browser
     mixes, where they would swamp the real numbers without meaning anything. --}}

<div class="grid gap-4 lg:grid-cols-2">

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Devices</div>
        <div class="panel__sub">{{ number_format($devices['humans']) }} human visitors, bots excluded</div>
      </div>
    </div>
    <div class="panel__body">
      @if($devices['humans'] > 0)
        <div class="mix-bar">
          @foreach(array_keys($devices['devices']) as $i => $name)
            @php $n = $devices['devices'][$name]; @endphp
            <div class="mix-bar__seg" style="flex: {{ $n }}; background: {{ $hues[$i] ?? $hues[5] }};"
                 title="{{ $name }}: {{ number_format($n) }}"></div>
          @endforeach
        </div>

        <div class="mt-4">
          @foreach(array_keys($devices['devices']) as $i => $name)
            <div class="mix-row">
              <span class="mix-row__dot" style="background: {{ $hues[$i] ?? $hues[5] }};"></span>
              <span class="mix-row__name">{{ $name }}</span>
              <span class="mix-row__count">{{ number_format($devices['devices'][$name]) }}</span>
              <span class="mix-row__share">{{ number_format($share($devices['devices'][$name], $devices['humans']), 1) }}%</span>
            </div>
          @endforeach
        </div>
      @else
        <div class="empty">
          <div class="empty__title">No human visitors yet</div>
          <div class="empty__hint">
            {{ number_format($devices['bots']) }} automated {{ Str::plural('visitor', $devices['bots']) }} recorded.
          </div>
        </div>
      @endif
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Browsers</div>
        <div class="panel__sub">Ranked by human visitors, bots excluded</div>
      </div>
    </div>
    <div class="panel__body">
      @if($devices['humans'] > 0 && count($devices['browsers']) > 0)
        @php $maxBrowser = max(1, ...array_values($devices['browsers'])); @endphp
        @foreach($devices['browsers'] as $name => $n)
          <div class="barline">
            <div class="barline__head">
              <span class="barline__name">{{ $name }}</span>
              <span class="barline__val">
                {{ number_format($n) }}
                <span class="barline__sub">{{ number_format($share($n, $devices['humans']), 1) }}%</span>
              </span>
            </div>
            <div class="barline__track">
              <div class="barline__fill" style="width: {{ max(round($n / $maxBrowser * 100), 1) }}%"></div>
            </div>
          </div>
        @endforeach
      @else
        <div class="empty">
          <div class="empty__title">Nothing to break down</div>
          <div class="empty__hint">Browsers are parsed from each visitor's user agent.</div>
        </div>
      @endif
    </div>
  </div>
</div>

<div class="mt-4 panel">
  <div class="panel__head">
    <div>
      <div class="panel__title">Automated traffic</div>
      <div class="panel__sub">Crawlers, link previewers and scripted clients</div>
    </div>
  </div>
  <div class="panel__body">
    @php $totalSeen = $devices['humans'] + $devices['bots']; @endphp
    <div class="meter">
      <span class="meter__name">Bots</span>
      <span class="meter__val">{{ number_format($devices['bots']) }} <span>· {{ number_format($share($devices['bots'], $totalSeen), 1) }}%</span></span>
      <div class="meter__track">
        <div class="meter__fill" style="width: {{ $devices['bots'] > 0 ? max(round($share($devices['bots'], $totalSeen)), 1) : 0 }}%"></div>
      </div>
    </div>
    <div class="meter">
      <span class="meter__name">Humans</span>
      <span class="meter__val">{{ number_format($devices['humans']) }} <span>· {{ number_format($share($devices['humans'], $totalSeen), 1) }}%</span></span>
      <div class="meter__track">
        <div class="meter__fill" style="width: {{ $devices['humans'] > 0 ? max(round($share($devices['humans'], $totalSeen)), 1) : 0 }}%"></div>
      </div>
    </div>
  </div>
</div>
