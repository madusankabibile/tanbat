@extends('admin.layout')
@section('title', 'Statistics')
@section('breadcrumb', 'Overview')
@section('heading', 'Statistics')

@push('head')
<style>
  /* Page-local vocabulary. The shared Ledger classes (.strip/.kpi/.panel/.badge/
     .table-card/.pager/.trend) come from the layout; everything below is scoped
     to this page and mirrors the dashboard's naming so the two read as one system. */

  .chart-wrap { position: relative; height: 264px; width: 100%; }
  .chart-wrap--short { height: 208px; }

  /* Range selector: pills, current window pressed. */
  .range { display: flex; align-items: center; gap: .5rem; }
  .range__label {
    font-family: var(--mono); font-size: .625rem; font-weight: 500;
    letter-spacing: .12em; text-transform: uppercase; color: var(--ink-3);
  }
  .range__group { display: inline-flex; border: 1px solid var(--rule); border-radius: var(--r); overflow: hidden; }
  .range__opt {
    padding: .375rem .75rem;
    font-family: var(--mono); font-size: .75rem; font-variant-numeric: tabular-nums;
    color: var(--ink-3); background: var(--surface);
    border-left: 1px solid var(--rule-soft);
  }
  .range__opt:first-child { border-left: 0; }
  .range__opt:hover { background: #F4F5F6; color: var(--ink); }
  .range__opt[aria-current="true"] { background: var(--pine-tint); color: var(--pine-d); font-weight: 600; }

  /* A section rule, so the visitor half and the member half are legibly separate. */
  .section-head { margin: 2rem 0 1rem; display: flex; align-items: baseline; gap: .75rem; }
  .section-head__title {
    font-family: var(--mono); font-size: .6875rem; font-weight: 600;
    letter-spacing: .14em; text-transform: uppercase; color: var(--ink);
  }
  .section-head__rule { flex: 1; height: 1px; background: var(--rule); }
  .section-head__note { font-size: .6875rem; color: var(--ink-4); }

  /* A caveat printed where the caveat applies, not in a footnote. */
  .note {
    margin-top: .75rem; padding: .5rem .625rem;
    background: #FAFBFB; border: 1px solid var(--rule-soft); border-radius: 5px;
    font-size: .6875rem; line-height: 1.5; color: var(--ink-3);
  }

  .legend { display: flex; align-items: center; gap: 1.125rem; }
  .legend__item { display: flex; align-items: baseline; gap: .4375rem; }
  .legend__key { width: 16px; height: 0; border-top-width: 2px; align-self: center; }
  .legend__name { font-size: .75rem; color: var(--ink-2); }
  .legend__total { font-family: var(--mono); font-size: .75rem; font-weight: 500; font-variant-numeric: tabular-nums; color: var(--ink); }

  .kpi-figure { font-family: var(--mono); font-size: .6875rem; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--ink-2); }

  /* One composition bar + a labelled key. */
  .mix-bar { display: flex; gap: 2px; height: 10px; border-radius: 3px; overflow: hidden; }
  .mix-bar__seg { min-width: 3px; }
  .mix-row {
    display: grid; grid-template-columns: 10px 1fr auto auto;
    align-items: center; gap: .625rem;
    padding: .5625rem 0; border-top: 1px solid var(--rule-soft);
  }
  .mix-row:first-of-type { border-top: 0; }
  .mix-row__dot { width: 10px; height: 10px; border-radius: 2px; }
  .mix-row__name { font-size: .8125rem; color: var(--ink-2); }
  .mix-row__count { font-family: var(--mono); font-size: .8125rem; font-weight: 500; font-variant-numeric: tabular-nums; color: var(--ink); }
  .mix-row__share { min-width: 3.25rem; text-align: right; font-family: var(--mono); font-size: .75rem; font-variant-numeric: tabular-nums; color: var(--ink-4); }

  /* Magnitude bars — one series, one hue. */
  .barline { padding: .5625rem 0; border-top: 1px solid var(--rule-soft); }
  .barline:first-child { border-top: 0; padding-top: 0; }
  .barline__head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
  .barline__name { font-size: .8125rem; color: var(--ink-2); min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .barline__name .mono { font-family: var(--mono); font-size: .78125rem; }
  .barline__val { flex: 0 0 auto; font-family: var(--mono); font-size: .8125rem; font-weight: 500; font-variant-numeric: tabular-nums; color: var(--ink); }
  .barline__sub { flex: 0 0 auto; font-family: var(--mono); font-size: .6875rem; font-variant-numeric: tabular-nums; color: var(--ink-4); }
  .barline__track { margin-top: .4375rem; height: 6px; border-radius: 3px; background: #F1F2F4; overflow: hidden; }
  .barline__fill { height: 100%; border-radius: 3px; background: var(--pine); }
  .barline__fill--brass { background: var(--brass); }

  /* Compact people rows. */
  .row-item {
    display: flex; align-items: center; gap: .6875rem;
    padding: .625rem 1.125rem; border-top: 1px solid var(--rule-soft);
  }
  .row-item:first-child { border-top: 0; }
  .row-item:hover { background: #FAFBFB; }
  .row-item__body { min-width: 0; flex: 1; }
  .row-item__name { display: block; font-size: .8125rem; font-weight: 500; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .row-item__name:hover { color: var(--pine-d); }
  .row-item__meta { margin-top: .125rem; font-size: .6875rem; color: var(--ink-3); }
  .row-item__meta .mono { font-family: var(--mono); }
  .row-item__num { flex: 0 0 auto; text-align: right; }
  .row-item__num b { display: block; font-family: var(--mono); font-size: .8125rem; font-weight: 500; font-variant-numeric: tabular-nums; color: var(--ink); }
  .row-item__num span { font-family: var(--mono); font-size: .5625rem; letter-spacing: .1em; text-transform: uppercase; color: var(--ink-4); }

  /* Health rows: a count, a share, and a hairline meter. */
  .meter { display: grid; grid-template-columns: 1fr auto; gap: .375rem 1rem; padding: .625rem 0; border-top: 1px solid var(--rule-soft); }
  .meter:first-child { border-top: 0; padding-top: 0; }
  .meter__name { font-size: .8125rem; color: var(--ink-2); }
  .meter__val { font-family: var(--mono); font-size: .8125rem; font-weight: 500; font-variant-numeric: tabular-nums; color: var(--ink); }
  .meter__val span { color: var(--ink-4); font-weight: 400; }
  .meter__track { grid-column: 1 / -1; height: 4px; border-radius: 2px; background: #F1F2F4; overflow: hidden; }
  .meter__fill { height: 100%; border-radius: 2px; background: var(--faint); }

  /* Monospaced cells in the visitor log. */
  .cell-mono { font-family: var(--mono); font-size: .75rem; font-variant-numeric: tabular-nums; color: var(--ink-2); }
  .cell-path {
    display: block; max-width: 20rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    font-family: var(--mono); font-size: .75rem; color: var(--ink-2);
  }
  .cell-ua {
    display: block; max-width: 13rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    font-size: .6875rem; color: var(--ink-3);
  }
  .flag {
    font-family: var(--mono); font-size: .625rem; font-weight: 500; letter-spacing: .06em;
    color: var(--ink-4);
  }

  .empty { padding: 2rem 1.125rem; text-align: center; }
  .empty__title { font-size: .8125rem; font-weight: 500; color: var(--ink-2); }
  .empty__hint  { margin-top: .25rem; font-size: .75rem; color: var(--ink-4); }
</style>
@endpush

@section('content')

@php
  /* Colour ramp for categorical mixes. Four hues max, then grey — beyond four
     categories a pie of colours stops carrying meaning, so the tail is muted. */
  $hues = ['var(--pine)', 'var(--brass)', 'var(--blue)', 'var(--plum)', 'var(--faint)', '#C3C7CE'];
  $share = fn ($n, $d) => $d > 0 ? round($n / $d * 100, 1) : 0.0;
@endphp

{{-- ───── Window selector ───── --}}
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <div class="range">
    <span class="range__label">Window</span>
    <div class="range__group">
      @foreach($ranges as $r)
        <a href="{{ route('admin.statistics.index', ['days' => $r]) }}"
           class="range__opt" aria-current="{{ $r === $days ? 'true' : 'false' }}">{{ $r }}d</a>
      @endforeach
    </div>
  </div>
  <div class="section-head__note">
    {{ $since->format('M j, Y') }} – {{ now()->format('M j, Y') }}
  </div>
</div>

{{-- ══════════════════════ VISITORS ══════════════════════ --}}
<div class="section-head">
  <span class="section-head__title">Visitors</span>
  <span class="section-head__rule"></span>
  <span class="section-head__note">By IP address + user agent</span>
</div>

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

{{-- ───── Traffic over time ───── --}}
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

{{-- ───── Top pages + countries ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-2">

  <div class="panel">
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

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Countries</div>
        <div class="panel__sub">Visitors seen in the last {{ $visitorTableDays }} days</div>
      </div>
    </div>
    <div class="panel__body">
      @php $maxCountry = collect($countries)->max('visitors') ?: 1; @endphp
      @forelse($countries as $c)
        <div class="barline">
          <div class="barline__head">
            <span class="barline__name">
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
</div>

{{-- ───── Referrers + devices ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-2">

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Referrers</div>
        <div class="panel__sub">External sites sending visitors here</div>
      </div>
    </div>
    <div class="panel__body">
      @php $maxRef = collect($referrers)->max('visitors') ?: 1; @endphp
      @forelse($referrers as $ref)
        <div class="barline">
          <div class="barline__head">
            <span class="barline__name"><span class="mono">{{ $ref['host'] }}</span></span>
            <span class="barline__val">{{ number_format($ref['visitors']) }}</span>
          </div>
          <div class="barline__track">
            <div class="barline__fill barline__fill--brass" style="width: {{ max(round($ref['visitors'] / $maxRef * 100), 1) }}%"></div>
          </div>
        </div>
      @empty
        <div class="empty">
          <div class="empty__title">No external referrers</div>
          <div class="empty__hint">Every visitor so far arrived directly or from within the site.</div>
        </div>
      @endforelse
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Devices &amp; browsers</div>
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

        <div class="mt-5 pt-4" style="border-top: 1px solid var(--rule-soft);">
          @php $maxBrowser = max(1, ...array_values($devices['browsers'])); @endphp
          @foreach($devices['browsers'] as $name => $n)
            <div class="barline">
              <div class="barline__head">
                <span class="barline__name">{{ $name }}</span>
                <span class="barline__val">{{ number_format($n) }}</span>
              </div>
              <div class="barline__track">
                <div class="barline__fill" style="width: {{ max(round($n / $maxBrowser * 100), 1) }}%"></div>
              </div>
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
</div>

{{-- ───── Raw visitor log ───── --}}
<div class="panel mt-4" id="visitor-log">
  <div class="panel__head">
    <div>
      <div class="panel__title">Visitor log</div>
      <div class="panel__sub">
        One row per visitor (IP + user agent), last {{ $visitorTableDays }} days ·
        {{ number_format($visitorLog->total()) }} matching
      </div>
    </div>
  </div>

  <div class="panel__body">
    <form method="GET" action="{{ route('admin.statistics.index') }}#visitor-log" class="flex flex-wrap items-end gap-3">
      <input type="hidden" name="days" value="{{ $days }}">
      <div class="flex-1 min-w-[220px]">
        <label class="label">Search</label>
        <input type="text" name="q" value="{{ $logFilters['q'] }}" placeholder="IP, page, country or referrer" class="input">
      </div>
      <div>
        <label class="label">Country</label>
        <select name="country" class="input">
          <option value="">All</option>
          @foreach($countries as $c)
            <option value="{{ $c['code'] }}" @selected($logFilters['country'] === $c['code'])>{{ $c['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="label">Traffic</label>
        <select name="traffic" class="input">
          <option value="all"   @selected($logFilters['traffic'] === 'all')>All</option>
          <option value="human" @selected($logFilters['traffic'] === 'human')>Humans</option>
          <option value="bot"   @selected($logFilters['traffic'] === 'bot')>Bots</option>
        </select>
      </div>
      <div>
        <label class="label">Sort</label>
        <select name="sort" class="input">
          <option value="recent" @selected($logFilters['sort'] === 'recent')>Last seen</option>
          <option value="first"  @selected($logFilters['sort'] === 'first')>First seen</option>
          <option value="hits"   @selected($logFilters['sort'] === 'hits')>Most hits</option>
        </select>
      </div>
      <div class="flex gap-2">
        <button class="btn-primary">Filter</button>
        <a href="{{ route('admin.statistics.index', ['days' => $days]) }}" class="btn-outline">Reset</a>
      </div>
    </form>
  </div>

  <div class="table-card" style="border: 0; border-top: 1px solid var(--rule); border-radius: 0;">
    <table>
      <thead>
        <tr>
          <th>IP address</th>
          <th>Country</th>
          <th>Last page</th>
          <th>Referrer</th>
          <th>Client</th>
          <th class="text-right">Hits</th>
          <th>First seen</th>
          <th>Last seen</th>
        </tr>
      </thead>
      <tbody>
        @forelse($visitorLog as $v)
          @php $ua = \App\Services\UserAgentParser::parse($v->user_agent); @endphp
          <tr>
            <td class="cell-mono">{{ $v->ip_address ?? '—' }}</td>
            <td>
              <span class="text-slate-600">{{ $v->country_name ?: 'Unknown' }}</span>
              @if($v->country_code)<span class="flag"> {{ $v->country_code }}</span>@endif
            </td>
            <td><span class="cell-path" title="{{ $v->page }}">{{ $v->page ?: '—' }}</span></td>
            <td>
              @if($v->referrer)
                <span class="cell-path" title="{{ $v->referrer }}">{{ parse_url($v->referrer, PHP_URL_HOST) ?: $v->referrer }}</span>
              @else
                <span class="text-slate-400 text-xs">Direct</span>
              @endif
            </td>
            <td>
              @if($ua['is_bot'])
                <span class="badge badge-user">bot</span>
              @else
                <span class="badge badge-type">{{ $ua['device'] }}</span>
              @endif
              <span class="cell-ua" title="{{ $v->user_agent }}">
                {{ $ua['is_bot'] ? Str::limit($v->user_agent, 40) : $ua['browser'] . ' · ' . $ua['platform'] }}
              </span>
            </td>
            <td class="text-right font-semibold">{{ number_format($v->hits) }}</td>
            <td class="text-slate-500 text-xs">{{ $v->created_at?->format('M j, H:i') }}</td>
            <td class="text-slate-500 text-xs">{{ $v->updated_at?->diffForHumans() }}</td>
          </tr>
        @empty
          <tr><td colspan="8" class="py-8 text-center text-sm text-slate-500">No visitors match these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($visitorLog->hasPages())
    <div class="panel__body">
      <div class="pager flex items-center gap-1.5">
        {{ $visitorLog->links() }}
      </div>
    </div>
  @endif
</div>

@if($visitorWindowClamped)
  <div class="note">
    The visitor-level panels above (countries, referrers, devices, log) only reach back
    {{ $visitorTableDays }} days — <code>visitors</code> rows are pruned past that. Page views
    and the traffic chart honour the full {{ $days }}-day window.
  </div>
@endif

{{-- ══════════════════════ MEMBERS ══════════════════════ --}}
<div class="section-head">
  <span class="section-head__title">Members</span>
  <span class="section-head__rule"></span>
  <span class="section-head__note">Registered accounts</span>
</div>

<div class="strip">
  <div class="kpi">
    <div class="kpi-label">Members</div>
    <div class="kpi-value">{{ number_format($members['total']) }}</div>
    <div class="kpi-foot">@include('admin.partials._trend', ['t' => $members['new_trend'], 'window' => $days])</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">New signups</div>
    <div class="kpi-value">{{ number_format($members['new']) }}</div>
    <div class="kpi-foot"><span>in the last {{ $days }} days</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Active</div>
    <div class="kpi-value">{{ number_format($members['active']) }}</div>
    <div class="kpi-foot">
      <span class="kpi-figure">{{ number_format($members['active_share'], 1) }}%</span>
      <span>posted or commented</span>
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Verified email</div>
    <div class="kpi-value">{{ number_format($members['verified_share'], 1) }}%</div>
    <div class="kpi-foot">
      <span class="kpi-figure">{{ number_format($members['verified']) }}</span>
      <span>confirmed addresses</span>
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Never posted</div>
    <div class="kpi-value">{{ number_format($members['never_posted_share'], 1) }}%</div>
    <div class="kpi-foot">
      <span class="kpi-figure">{{ number_format($members['never_posted']) }}</span>
      <span>lurkers</span>
    </div>
  </div>
</div>

{{-- ───── Signups + gender ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">

  <div class="panel lg:col-span-2">
    <div class="panel__head">
      <div>
        <div class="panel__title">Signups</div>
        <div class="panel__sub">New members per day, last {{ $days }} days</div>
      </div>
      <a href="{{ route('admin.users.index') }}" class="panel__link">All users</a>
    </div>
    <div class="panel__body">
      <div class="chart-wrap chart-wrap--short"><canvas id="chartSignups"></canvas></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Gender</div>
        <div class="panel__sub">Self-reported at signup</div>
      </div>
    </div>
    <div class="panel__body">
      @php $genderTotal = array_sum($demographics['gender']); @endphp
      @if($genderTotal > 0)
        <div class="mix-bar">
          @foreach(array_keys($demographics['gender']) as $i => $g)
            <div class="mix-bar__seg" style="flex: {{ $demographics['gender'][$g] }}; background: {{ $hues[$i] ?? $hues[5] }};"
                 title="{{ Str::headline($g) }}"></div>
          @endforeach
        </div>
        <div class="mt-4">
          @foreach(array_keys($demographics['gender']) as $i => $g)
            <div class="mix-row">
              <span class="mix-row__dot" style="background: {{ $hues[$i] ?? $hues[5] }};"></span>
              <span class="mix-row__name">{{ Str::headline($g) }}</span>
              <span class="mix-row__count">{{ number_format($demographics['gender'][$g]) }}</span>
              <span class="mix-row__share">{{ number_format($share($demographics['gender'][$g], $genderTotal), 1) }}%</span>
            </div>
          @endforeach
        </div>
      @else
        <div class="empty"><div class="empty__title">No members yet</div></div>
      @endif
    </div>
  </div>
</div>

{{-- ───── Age + country + health ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Age</div>
        <div class="panel__sub">{{ number_format($demographics['age_total']) }} members with an age on file</div>
      </div>
    </div>
    <div class="panel__body">
      @php $maxAge = max(1, ...array_values($demographics['ages'])); @endphp
      @foreach($demographics['ages'] as $band => $n)
        <div class="barline">
          <div class="barline__head">
            <span class="barline__name">{{ $band }}</span>
            <span class="barline__val">
              {{ number_format($n) }}
              <span class="barline__sub">{{ number_format($share($n, max(1, $demographics['age_total'])), 1) }}%</span>
            </span>
          </div>
          <div class="barline__track">
            <div class="barline__fill" style="width: {{ $n > 0 ? max(round($n / $maxAge * 100), 1) : 0 }}%"></div>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Member countries</div>
        <div class="panel__sub">{{ number_format($demographics['country_known']) }} of {{ number_format($members['total']) }} have a country set</div>
      </div>
    </div>
    <div class="panel__body">
      @php $maxUserCountry = collect($demographics['countries'])->max('count') ?: 1; @endphp
      @forelse($demographics['countries'] as $c)
        <div class="barline">
          <div class="barline__head">
            <span class="barline__name">{{ $c['name'] }} <span class="flag">{{ $c['code'] }}</span></span>
            <span class="barline__val">{{ number_format($c['count']) }}</span>
          </div>
          <div class="barline__track">
            <div class="barline__fill barline__fill--brass" style="width: {{ max(round($c['count'] / $maxUserCountry * 100), 1) }}%"></div>
          </div>
        </div>
      @empty
        <div class="empty"><div class="empty__title">No countries recorded</div></div>
      @endforelse

      @if($demographics['country_unknown'] > 0)
        <div class="note">
          {{ number_format($demographics['country_unknown']) }} members
          ({{ number_format($share($demographics['country_unknown'], max(1, $members['total'])), 1) }}%)
          have no country on file and are excluded from this ranking.
        </div>
      @endif
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Account health</div>
        <div class="panel__sub">Share of all {{ number_format($members['total']) }} members</div>
      </div>
    </div>
    <div class="panel__body">
      @foreach($health as $h)
        <div class="meter">
          <span class="meter__name">{{ $h['label'] }}</span>
          <span class="meter__val">{{ number_format($h['count']) }} <span>· {{ number_format($h['share'], 1) }}%</span></span>
          <div class="meter__track">
            <div class="meter__fill" style="width: {{ $h['count'] > 0 ? max(round($h['share']), 1) : 0 }}%"></div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>

{{-- ───── Leaderboards ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Top authors</div>
        <div class="panel__sub">Members by posts published</div>
      </div>
    </div>
    <div class="panel__body--flush">
      @forelse($leaders['byPosts'] as $u)
        @include('admin.partials._leader-row', ['u' => $u, 'value' => $u->posts_count, 'unit' => 'Posts'])
      @empty
        <div class="empty"><div class="empty__title">Nobody has posted yet</div></div>
      @endforelse
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Top commenters</div>
        <div class="panel__sub">Members by comments written</div>
      </div>
    </div>
    <div class="panel__body--flush">
      @forelse($leaders['byComments'] as $u)
        @include('admin.partials._leader-row', ['u' => $u, 'value' => $u->comments_count, 'unit' => 'Replies'])
      @empty
        <div class="empty"><div class="empty__title">No comments yet</div></div>
      @endforelse
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Most liked</div>
        <div class="panel__sub">Likes received across all their posts</div>
      </div>
    </div>
    <div class="panel__body--flush">
      @forelse($leaders['byLikes'] as $row)
        @include('admin.partials._leader-row', ['u' => $row['user'], 'value' => $row['total'], 'unit' => 'Likes'])
      @empty
        <div class="empty"><div class="empty__title">No likes yet</div></div>
      @endforelse
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') return;

  var MONO  = "'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace";
  var PINE  = '#07775A';
  var BRASS = '#B45309';
  var TICK  = '#6E7480'; // --ink-4: 4.7:1 on white, readable as axis text
  var GRID  = '#EDEEF1';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  Chart.defaults.font.family = MONO;
  Chart.defaults.font.size = 10;

  function label(dateStr) {
    var d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }

  var baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: reduceMotion ? false : undefined,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#14161A',
        padding: 10,
        cornerRadius: 6,
        displayColors: true,
        boxWidth: 8,
        boxHeight: 8,
        boxPadding: 4,
        titleFont: { family: MONO, size: 10, weight: '500' },
        bodyFont: { family: MONO, size: 11 },
      },
    },
    scales: {
      x: {
        border: { color: '#E2E4E8' },
        grid: { display: false },
        ticks: { color: TICK, maxTicksLimit: 8, maxRotation: 0, autoSkipPadding: 12 },
      },
      y: {
        beginAtZero: true,
        border: { display: false },
        grid: { color: GRID, drawTicks: false },
        ticks: { color: TICK, precision: 0, maxTicksLimit: 5, padding: 8 },
      },
    },
  };

  function line(extra) {
    return Object.assign({
      borderWidth: 2,
      tension: .3,
      pointRadius: 0,
      pointHoverRadius: 4,
      pointHoverBorderColor: '#fff',
      pointHoverBorderWidth: 2,
    }, extra);
  }

  var traffic = @json($trafficSeries);
  var trafficCanvas = document.getElementById('chartTraffic');
  if (trafficCanvas) {
    new Chart(trafficCanvas, {
      type: 'line',
      data: {
        labels: traffic.map(function (p) { return label(p.date); }),
        datasets: [
          line({
            label: 'Views',
            data: traffic.map(function (p) { return p.views; }),
            borderColor: PINE,
            backgroundColor: PINE,
          }),
          // Dashed, so the two series stay distinguishable without relying on
          // hue alone (colour-vision deficiency, greyscale printing).
          line({
            label: 'Uniques',
            data: traffic.map(function (p) { return p.uniques; }),
            borderColor: BRASS,
            backgroundColor: BRASS,
            borderDash: [4, 3],
          }),
        ],
      },
      options: baseOptions,
    });
  }

  var signups = @json($signupSeries);
  var signupCanvas = document.getElementById('chartSignups');
  if (signupCanvas) {
    new Chart(signupCanvas, {
      type: 'line',
      data: {
        labels: signups.map(function (p) { return label(p.date); }),
        datasets: [line({
          label: 'Signups',
          data: signups.map(function (p) { return p.count; }),
          borderColor: PINE,
          backgroundColor: PINE,
          fill: false,
        })],
      },
      options: baseOptions,
    });
  }
})();
</script>
@endpush
