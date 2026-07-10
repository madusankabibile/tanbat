@extends('admin.layout')
@section('title', 'Dashboard')
@section('breadcrumb', 'Overview')
@section('heading', 'Dashboard')

@push('head')
<style>
  /* ── Chart frame ─────────────────────────────────────── */
  .chart-wrap { position: relative; height: 264px; width: 100%; }

  /* Legend chips. The swatch repeats the line's own stroke style (solid vs
     dashed), so the two series stay tellable apart without relying on hue. */
  .legend { display: flex; align-items: center; gap: 1.125rem; }
  .legend__item { display: flex; align-items: baseline; gap: .4375rem; }
  .legend__key { width: 16px; height: 0; border-top-width: 2px; align-self: center; }
  .legend__name { font-size: .75rem; color: var(--ink-2); }
  .legend__total { font-family: var(--mono); font-size: .75rem; font-weight: 500; font-variant-numeric: tabular-nums; color: var(--ink); }

  /* ── Content mix: one composition bar + a labelled key ── */
  .mix-bar { display: flex; gap: 2px; height: 10px; border-radius: 3px; overflow: hidden; }
  .mix-bar__seg { min-width: 3px; }

  .mix-row {
    display: grid; grid-template-columns: 10px 1fr auto auto;
    align-items: center; gap: .625rem;
    padding: .5625rem 0;
    border-top: 1px solid var(--rule-soft);
  }
  .mix-row:first-of-type { border-top: 0; }
  .mix-row__dot { width: 10px; height: 10px; border-radius: 2px; }
  .mix-row__name { font-size: .8125rem; color: var(--ink-2); }
  .mix-row__count { font-family: var(--mono); font-size: .8125rem; font-weight: 500; font-variant-numeric: tabular-nums; color: var(--ink); }
  .mix-row__share { min-width: 3.25rem; text-align: right; font-family: var(--mono); font-size: .75rem; font-variant-numeric: tabular-nums; color: var(--ink-4); }

  /* ── Ranked list (top posts) ─────────────────────────── */
  .rank {
    display: grid; grid-template-columns: 1.5rem 1fr auto;
    align-items: center; gap: .875rem;
    padding: .75rem 1.125rem;
    border-top: 1px solid var(--rule-soft);
  }
  .rank:first-child { border-top: 0; }
  .rank:hover { background: #FAFBFB; }
  .rank__no { font-family: var(--mono); font-size: .75rem; color: var(--ink-4); font-variant-numeric: tabular-nums; }
  .rank__title { display: block; font-size: .8125rem; font-weight: 500; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rank__title:hover { color: var(--pine-d); }
  .rank__meta { margin-top: .25rem; display: flex; align-items: center; gap: .375rem; font-size: .6875rem; color: var(--ink-3); }
  .rank__stats { display: flex; gap: .875rem; }
  .rank__stat { text-align: right; }
  .rank__stat b { display: block; font-family: var(--mono); font-size: .8125rem; font-weight: 500; font-variant-numeric: tabular-nums; color: var(--ink); }
  .rank__stat span { font-family: var(--mono); font-size: .5625rem; letter-spacing: .1em; text-transform: uppercase; color: var(--ink-4); }

  /* ── Magnitude bars (top categories) — one series, one hue ── */
  .barline { padding: .5625rem 0; border-top: 1px solid var(--rule-soft); }
  .barline:first-child { border-top: 0; padding-top: 0; }
  .barline__head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
  .barline__name { font-size: .8125rem; color: var(--ink-2); }
  .barline__val { font-family: var(--mono); font-size: .8125rem; font-weight: 500; font-variant-numeric: tabular-nums; color: var(--ink); }
  .barline__track { margin-top: .4375rem; height: 6px; border-radius: 3px; background: #F1F2F4; overflow: hidden; }
  .barline__fill { height: 100%; border-radius: 3px; background: var(--pine); }

  /* ── Compact people / post rows ──────────────────────── */
  .row-item {
    display: flex; align-items: center; gap: .6875rem;
    padding: .625rem 1.125rem;
    border-top: 1px solid var(--rule-soft);
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

  /* A plain figure in the KPI foot — a rate, not a movement. */
  .kpi-figure { font-family: var(--mono); font-size: .6875rem; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--ink-2); }

  /* ── Empty states: an invitation, not an apology ─────── */
  .empty { padding: 2rem 1.125rem; text-align: center; }
  .empty__title { font-size: .8125rem; font-weight: 500; color: var(--ink-2); }
  .empty__hint  { margin-top: .25rem; font-size: .75rem; color: var(--ink-4); }
  .empty__hint a { color: var(--pine-d); border-bottom: 1px solid var(--pine-tint); }
  .empty__hint a:hover { border-bottom-color: var(--pine); }
</style>
@endpush

@section('content')

{{-- ───── Ledger strip: the five numbers that describe the platform ───── --}}
<div class="strip">
  <div class="kpi">
    <div class="kpi-label">Members</div>
    <div class="kpi-value">{{ number_format($stats['users_total']) }}</div>
    <div class="kpi-foot">@include('admin.partials._trend', ['t' => $trends['users']])</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Posts</div>
    <div class="kpi-value">{{ number_format($stats['posts_total']) }}</div>
    <div class="kpi-foot">@include('admin.partials._trend', ['t' => $trends['posts']])</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Comments</div>
    <div class="kpi-value">{{ number_format($stats['comments_total']) }}</div>
    <div class="kpi-foot">@include('admin.partials._trend', ['t' => $trends['comments']])</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Likes</div>
    <div class="kpi-value">{{ number_format($stats['likes_total']) }}</div>
    <div class="kpi-foot">@include('admin.partials._trend', ['t' => $trends['likes']])</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Live ads</div>
    <div class="kpi-value">{{ number_format($stats['ads_active']) }}</div>
    <div class="kpi-foot">
      <span class="kpi-figure">{{ number_format($stats['ad_ctr'], 2) }}%</span>
      <span>click-through · {{ number_format($stats['ad_clicks']) }} clicks</span>
    </div>
  </div>
</div>

{{-- ───── Activity over time + what the library is made of ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">

  <div class="panel lg:col-span-2">
    <div class="panel__head">
      <div>
        <div class="panel__title">Activity</div>
        <div class="panel__sub">New members and posts per day, last 30 days</div>
      </div>
      <div class="legend">
        <span class="legend__item">
          <span class="legend__key" style="border-top: 2px solid var(--pine);"></span>
          <span class="legend__name">Members</span>
          <span class="legend__total">{{ number_format($trends['users']['current']) }}</span>
        </span>
        <span class="legend__item">
          <span class="legend__key" style="border-top: 2px dashed var(--brass);"></span>
          <span class="legend__name">Posts</span>
          <span class="legend__total">{{ number_format($trends['posts']['current']) }}</span>
        </span>
      </div>
    </div>
    <div class="panel__body">
      <div class="chart-wrap"><canvas id="chartActivity"></canvas></div>
    </div>
  </div>

  @php
    $typeOrder  = ['status' => 'var(--pine)', 'image' => 'var(--brass)', 'video' => 'var(--blue)', 'article' => 'var(--plum)'];
    $typeTotal  = array_sum(array_map(fn ($t) => (int) ($postTypes[$t] ?? 0), array_keys($typeOrder)));
  @endphp
  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Content mix</div>
        <div class="panel__sub">Share of all {{ number_format($typeTotal) }} posts by type</div>
      </div>
    </div>
    <div class="panel__body">
      @if($typeTotal > 0)
        <div class="mix-bar">
          @foreach($typeOrder as $type => $color)
            @php $n = (int) ($postTypes[$type] ?? 0); @endphp
            @if($n > 0)
              <div class="mix-bar__seg" style="flex: {{ $n }}; background: {{ $color }};" title="{{ ucfirst($type) }}: {{ number_format($n) }}"></div>
            @endif
          @endforeach
        </div>

        <div class="mt-4">
          @foreach($typeOrder as $type => $color)
            @php $n = (int) ($postTypes[$type] ?? 0); @endphp
            <div class="mix-row">
              <span class="mix-row__dot" style="background: {{ $color }};"></span>
              <span class="mix-row__name">{{ ucfirst($type) }}</span>
              <span class="mix-row__count">{{ number_format($n) }}</span>
              <span class="mix-row__share">{{ number_format($n / $typeTotal * 100, 1) }}%</span>
            </div>
          @endforeach
        </div>
      @else
        <div class="empty">
          <div class="empty__title">No posts yet</div>
          <div class="empty__hint">The mix appears once members start publishing.</div>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- ───── Top posts + top categories ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-2">

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Top posts</div>
        <div class="panel__sub">Ranked by likes and comments combined</div>
      </div>
      <a href="{{ route('admin.posts.index') }}" class="panel__link">All posts</a>
    </div>
    <div class="panel__body--flush">
      @forelse($topPosts as $i => $p)
        <div class="rank">
          <span class="rank__no">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
          <div class="min-w-0">
            <a href="{{ route('admin.posts.show', $p) }}" class="rank__title">
              {{ $p->title ?: \Illuminate\Support\Str::limit(strip_tags($p->status_text ?: $p->description ?: 'Untitled'), 56) }}
            </a>
            <div class="rank__meta">
              <span class="badge badge-type">{{ $p->type }}</span>
              <span>{{ $p->user?->name ?? 'Deleted member' }}</span>
              @if($p->category)<span>· {{ $p->category->name }}</span>@endif
            </div>
          </div>
          <div class="rank__stats">
            <div class="rank__stat"><b>{{ number_format($p->likes_count) }}</b><span>Likes</span></div>
            <div class="rank__stat"><b>{{ number_format($p->comments_count) }}</b><span>Replies</span></div>
          </div>
        </div>
      @empty
        <div class="empty">
          <div class="empty__title">No posts yet</div>
          <div class="empty__hint">Engagement rankings appear after the first post.</div>
        </div>
      @endforelse
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Top categories</div>
        <div class="panel__sub">Posts filed under each category</div>
      </div>
      <a href="{{ route('admin.categories.index') }}" class="panel__link">Manage</a>
    </div>
    <div class="panel__body">
      @forelse($topCategories as $cat)
        @php
          $max = max(1, $topCategories->max('posts_count'));
          $pct = round(($cat->posts_count / $max) * 100);
        @endphp
        <div class="barline">
          <div class="barline__head">
            <span class="barline__name">{{ $cat->name }}</span>
            <span class="barline__val">{{ number_format($cat->posts_count) }}</span>
          </div>
          <div class="barline__track">
            <div class="barline__fill" style="width: {{ max($pct, 1) }}%"></div>
          </div>
        </div>
      @empty
        <div class="empty">
          <div class="empty__title">No categories yet</div>
          <div class="empty__hint">
            <a href="{{ route('admin.categories.index') }}" class="panel__link">Create the first one</a>
          </div>
        </div>
      @endforelse
    </div>
  </div>
</div>

{{-- ───── Who is here, who is writing, what just landed ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Newest members</div>
        <div class="panel__sub">Most recent signups</div>
      </div>
      <a href="{{ route('admin.users.index') }}" class="panel__link">All users</a>
    </div>
    <div class="panel__body--flush">
      @forelse($recentUsers as $ru)
        <div class="row-item">
          @if($ru->profile_picture)
            <img class="avatar-circle" src="{{ asset('storage/'.$ru->profile_picture) }}" alt="">
          @else
            <span class="avatar-circle">{{ strtoupper(mb_substr($ru->name, 0, 1)) }}</span>
          @endif
          <div class="row-item__body">
            <a href="{{ route('admin.users.edit', $ru) }}" class="row-item__name">{{ $ru->name }}</a>
            <div class="row-item__meta"><span class="mono">{{ '@' . $ru->username }}</span> · {{ $ru->created_at?->diffForHumans() }}</div>
          </div>
          <span class="badge {{ $ru->role === 'admin' ? 'badge-admin' : 'badge-user' }}">{{ $ru->role }}</span>
        </div>
      @empty
        <div class="empty">
          <div class="empty__title">No members yet</div>
          <div class="empty__hint">Signups will show up here.</div>
        </div>
      @endforelse
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Most active</div>
        <div class="panel__sub">Members by posts published</div>
      </div>
      <a href="{{ route('admin.users.index') }}" class="panel__link">All users</a>
    </div>
    <div class="panel__body--flush">
      @forelse($topUsers as $tu)
        <div class="row-item">
          @if($tu->profile_picture)
            <img class="avatar-circle" src="{{ asset('storage/'.$tu->profile_picture) }}" alt="">
          @else
            <span class="avatar-circle">{{ strtoupper(mb_substr($tu->name, 0, 1)) }}</span>
          @endif
          <div class="row-item__body">
            <a href="{{ route('admin.users.edit', $tu) }}" class="row-item__name">{{ $tu->name }}</a>
            <div class="row-item__meta">{{ number_format($tu->comments_count) }} comments</div>
          </div>
          <div class="row-item__num">
            <b>{{ number_format($tu->posts_count) }}</b>
            <span>Posts</span>
          </div>
        </div>
      @empty
        <div class="empty">
          <div class="empty__title">No members yet</div>
          <div class="empty__hint">Activity rankings need at least one member.</div>
        </div>
      @endforelse
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Latest posts</div>
        <div class="panel__sub">Newest content across the site</div>
      </div>
      <a href="{{ route('admin.posts.index') }}" class="panel__link">All posts</a>
    </div>
    <div class="panel__body--flush">
      @forelse($recentPosts as $rp)
        <div class="row-item">
          <div class="row-item__body">
            <a href="{{ route('admin.posts.show', $rp) }}" class="row-item__name">
              {{ $rp->title ?: \Illuminate\Support\Str::limit(strip_tags($rp->status_text ?: $rp->description ?: 'Untitled'), 44) }}
            </a>
            <div class="row-item__meta">
              <span class="badge badge-type">{{ $rp->type }}</span>
              <span class="mono">{{ $rp->user?->username ? '@'.$rp->user->username : 'deleted' }}</span>
              · {{ $rp->created_at?->diffForHumans() }}
            </div>
          </div>
          <a href="{{ route('admin.posts.edit', $rp) }}" class="btn-xs">Edit</a>
        </div>
      @empty
        <div class="empty">
          <div class="empty__title">No posts yet</div>
          <div class="empty__hint">New content lands here as it is published.</div>
        </div>
      @endforelse
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  var canvas = document.getElementById('chartActivity');
  if (!canvas || typeof Chart === 'undefined') return;

  var userSeries = @json($userSeries);
  var postSeries = @json($postSeries);

  var MONO  = "'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace";
  var PINE  = '#07775A';
  var BRASS = '#B45309';
  var TICK  = '#6E7480'; // --ink-4: 4.7:1 on white, readable as axis text
  var GRID  = '#EDEEF1';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var labels = userSeries.map(function (p) {
    var d = new Date(p.date + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  });

  Chart.defaults.font.family = MONO;
  Chart.defaults.font.size = 10;

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Members',
          data: userSeries.map(function (p) { return p.count; }),
          borderColor: PINE,
          backgroundColor: PINE,
          borderWidth: 2,
          tension: .3,
          pointRadius: 0,
          pointHoverRadius: 4,
          pointHoverBorderColor: '#fff',
          pointHoverBorderWidth: 2,
        },
        {
          // Dashed, so the two series stay distinguishable without relying on
          // hue alone (colour-vision deficiency, greyscale printing).
          label: 'Posts',
          data: postSeries.map(function (p) { return p.count; }),
          borderColor: BRASS,
          backgroundColor: BRASS,
          borderWidth: 2,
          borderDash: [4, 3],
          tension: .3,
          pointRadius: 0,
          pointHoverRadius: 4,
          pointHoverBorderColor: '#fff',
          pointHoverBorderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: reduceMotion ? false : undefined,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false }, // rendered as HTML chips in the panel head
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
    },
  });
})();
</script>
@endpush
