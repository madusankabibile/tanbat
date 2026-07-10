@extends('admin.layout')
@section('title', 'Statistics')
@section('breadcrumb', 'Overview')
@section('heading', 'Statistics')

@push('head')
<style>
  /* Page-local vocabulary shared by every tab partial. The Ledger classes
     (.strip/.kpi/.panel/.badge/.table-card/.pager/.trend/.flag-img) come from
     the layout; everything below is scoped to this page and mirrors the
     dashboard's naming so the two read as one system. */

  .chart-wrap { position: relative; height: 264px; width: 100%; }
  .chart-wrap--short { height: 208px; }

  /* ── Tab bar ─────────────────────────────────────────── */
  /* Real links, not JS panels: each tab is its own request, so only the open
     tab's queries run and every tab is deep-linkable. */
  .tabs {
    display: flex; gap: .125rem;
    border-bottom: 1px solid var(--rule);
    overflow-x: auto; scrollbar-width: none;
  }
  .tabs::-webkit-scrollbar { display: none; }
  .tab {
    flex: 0 0 auto;
    padding: .625rem .875rem;
    margin-bottom: -1px;
    border-bottom: 2px solid transparent;
    font-size: .8125rem; font-weight: 500; color: var(--ink-3);
    white-space: nowrap;
  }
  .tab:hover { color: var(--ink); background: #F4F5F6; }
  .tab[aria-current="page"] { color: var(--pine-d); border-bottom-color: var(--pine); font-weight: 600; }
  .tab:focus-visible { outline: 2px solid var(--pine); outline-offset: -2px; }

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

  /* Compact people / visitor rows. */
  .row-item {
    display: flex; align-items: center; gap: .6875rem;
    padding: .625rem 1.125rem; border-top: 1px solid var(--rule-soft);
  }
  .row-item:first-child { border-top: 0; }
  .row-item:hover { background: #FAFBFB; }
  .row-item__body { min-width: 0; flex: 1; }
  .row-item__name { display: block; font-size: .8125rem; font-weight: 500; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .row-item__name:hover { color: var(--pine-d); }
  .row-item__meta { margin-top: .125rem; font-size: .6875rem; color: var(--ink-3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
  /* The flag sits inline ahead of the country name in both the barline panels
     and the log table; .flag-img itself carries no margin so it stays reusable. */
  .barline__name > .flag-img,
  .barline__name > .flag-unknown { margin-right: .4375rem; }

  .country-cell { display: inline-flex; align-items: center; gap: .4375rem; white-space: nowrap; }

  /* ── World map (choropleth) ──────────────────────────── */
  .map { position: relative; }
  .map__canvas svg { display: block; width: 100%; height: auto; }
  /* Every country starts as "no data" and is lifted by a level class. */
  .map__canvas svg path {
    fill: #F1F2F4;
    stroke: #FFFFFF;
    stroke-width: .4;
    vector-effect: non-scaling-stroke;
    transition: fill .12s ease;
  }
  .map__canvas svg path.lvl-1 { fill: #E7F2EE; }
  .map__canvas svg path.lvl-2 { fill: #C2DED5; }
  .map__canvas svg path.lvl-3 { fill: #8FC2B3; }
  .map__canvas svg path.lvl-4 { fill: #4E9C86; }
  .map__canvas svg path.lvl-5 { fill: #07775A; }
  .map__canvas svg path[data-v]:hover { stroke: var(--ink); stroke-width: 1.2; }
  .map__canvas svg path[data-v] { cursor: default; }

  .map__status { padding: 3rem 1.125rem; text-align: center; font-size: .8125rem; color: var(--ink-4); }

  .map__tip {
    position: fixed; z-index: 60; pointer-events: none;
    padding: .4375rem .5625rem; border-radius: 6px;
    background: #14161A; color: #fff;
    font-family: var(--mono); font-size: .6875rem; line-height: 1.5;
    font-variant-numeric: tabular-nums;
    opacity: 0; transition: opacity .1s ease;
  }
  .map__tip b { font-weight: 600; }

  .map__legend { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
  .map__ramp { display: flex; gap: 2px; }
  .map__swatch { width: 26px; height: 10px; border-radius: 2px; }
  .map__legend-label { font-family: var(--mono); font-size: .6875rem; color: var(--ink-4); font-variant-numeric: tabular-nums; }
  .map__key { display: inline-flex; align-items: center; gap: .375rem; font-size: .6875rem; color: var(--ink-4); }
  .map__key i { width: 10px; height: 10px; border-radius: 2px; background: #F1F2F4; border: 1px solid var(--rule); }

  .empty { padding: 2rem 1.125rem; text-align: center; }
  .empty__title { font-size: .8125rem; font-weight: 500; color: var(--ink-2); }
  .empty__hint  { margin-top: .25rem; font-size: .75rem; color: var(--ink-4); }
</style>
@endpush

{{-- Chart.js + shared chart defaults. Pushed here, ahead of @section('content'),
     so the per-tab init scripts a partial pushes land after this in the stack. --}}
@if(in_array($tab, ['traffic', 'members'], true))
  @push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
  window.Ledger = (function () {
    var MONO = "'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace";
    var TICK = '#6E7480';  // --ink-4: 4.7:1 on white, readable as axis text
    var GRID = '#EDEEF1';

    if (typeof Chart !== 'undefined') {
      Chart.defaults.font.family = MONO;
      Chart.defaults.font.size = 10;
    }

    return {
      PINE:  '#07775A',
      BRASS: '#B45309',

      /** YYYY-MM-DD -> "Jul 3" */
      label: function (dateStr) {
        var d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      },

      /** A line dataset with the house styling; `extra` overrides anything. */
      line: function (extra) {
        return Object.assign({
          borderWidth: 2,
          tension: .3,
          pointRadius: 0,
          pointHoverRadius: 4,
          pointHoverBorderColor: '#fff',
          pointHoverBorderWidth: 2,
        }, extra);
      },

      options: function () {
        return {
          responsive: true,
          maintainAspectRatio: false,
          animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : undefined,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: false },  // rendered as HTML chips in the panel head
            tooltip: {
              backgroundColor: '#14161A', padding: 10, cornerRadius: 6,
              displayColors: true, boxWidth: 8, boxHeight: 8, boxPadding: 4,
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
      },
    };
  })();
  </script>
  @endpush
@endif

@section('content')

@php
  /* Colour ramp for categorical mixes. Four hues max, then grey — beyond four
     categories a spread of colours stops carrying meaning, so the tail is muted. */
  $hues = ['var(--pine)', 'var(--brass)', 'var(--blue)', 'var(--plum)', 'var(--faint)', '#C3C7CE'];
  $share = fn ($n, $d) => $d > 0 ? round($n / $d * 100, 1) : 0.0;

  /* Tabs that read the 30-day-capped `visitors` table rather than the 90-day
     `visitor_page_views` counters, and so can't honour a 90-day window. */
  $visitorTableTabs = ['countries', 'map', 'referrers', 'devices', 'log'];
@endphp

{{-- ───── Tab bar ───── --}}
<nav class="tabs" aria-label="Statistics sections">
  @foreach($tabs as $slug => $label)
    <a class="tab"
       href="{{ route('admin.statistics.index', $slug === 'today' ? ['tab' => $slug] : ['tab' => $slug, 'days' => $days]) }}"
       @if($slug === $tab) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>

{{-- ───── Window selector ───── --}}
<div class="mt-4 mb-4 flex flex-wrap items-center justify-between gap-3">
  @if($showRangeSelector)
    <div class="range">
      <span class="range__label">Window</span>
      <div class="range__group">
        @foreach($ranges as $r)
          <a href="{{ route('admin.statistics.index', ['tab' => $tab, 'days' => $r]) }}"
             class="range__opt" aria-current="{{ $r === $days ? 'true' : 'false' }}">{{ $r }}d</a>
        @endforeach
      </div>
    </div>
    <div class="section-head__note">
      {{ $since->format('M j, Y') }} – {{ now()->format('M j, Y') }}
    </div>
  @else
    {{-- The Today tab always means today, so there is no window to choose. --}}
    <span></span>
    <div class="section-head__note">{{ now()->format('l, M j, Y') }}</div>
  @endif
</div>

<div id="tabpanel">
  @include('admin.statistics.tabs._' . $tab)

  @if($visitorWindowClamped && in_array($tab, $visitorTableTabs, true))
    <div class="note">
      This tab reads the <code>visitors</code> table, which is pruned after
      {{ $visitorTableDays }} days — so it shows the last {{ $visitorTableDays }} days even
      though a {{ $days }}-day window is selected. The Traffic tab honours the full window.
    </div>
  @endif
</div>

@endsection
