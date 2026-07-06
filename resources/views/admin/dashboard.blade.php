@extends('admin.layout')
@section('title', 'Dashboard')
@section('breadcrumb', 'Overview')
@section('heading', 'Dashboard')

@section('content')

{{-- ───── KPI cards ───── --}}
<div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
  <div class="kpi">
    <div class="kpi-label">Users</div>
    <div class="kpi-value">{{ number_format($stats['users_total']) }}</div>
    <div class="kpi-foot">+{{ number_format($stats['users_new_30d']) }} in last 30 days</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Posts</div>
    <div class="kpi-value">{{ number_format($stats['posts_total']) }}</div>
    <div class="kpi-foot">+{{ number_format($stats['posts_new_30d']) }} in last 30 days</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Comments</div>
    <div class="kpi-value">{{ number_format($stats['comments_total']) }}</div>
    <div class="kpi-foot">All time</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Likes</div>
    <div class="kpi-value">{{ number_format($stats['likes_total']) }}</div>
    <div class="kpi-foot">All time</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Active Ads</div>
    <div class="kpi-value">{{ number_format($stats['ads_active']) }}</div>
    <div class="kpi-foot">CTR {{ $stats['ad_ctr'] }}% · {{ number_format($stats['ad_clicks']) }} clicks</div>
  </div>
</div>

{{-- ───── Growth chart + post-type donut ───── --}}
<div class="mt-6 grid gap-4 lg:grid-cols-3">
  <div class="card lg:col-span-2 p-5">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-sm font-bold text-slate-900">Growth (last 30 days)</div>
        <div class="text-xs text-slate-500">New users vs new posts per day</div>
      </div>
      <div class="flex items-center gap-3 text-xs">
        <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-3 rounded bg-brand-500"></span>Users</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-3 rounded bg-accent-500"></span>Posts</span>
      </div>
    </div>
    <div class="relative mt-3 h-[260px] w-full">
      <canvas id="chartGrowth"></canvas>
    </div>
  </div>

  <div class="card p-5">
    <div class="text-sm font-bold text-slate-900">Post types</div>
    <div class="text-xs text-slate-500">Distribution of content</div>
    <div class="relative mt-3 h-[260px] w-full">
      <canvas id="chartTypes"></canvas>
    </div>
  </div>
</div>

{{-- ───── Top categories + top posts ───── --}}
<div class="mt-6 grid gap-4 lg:grid-cols-2">

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-sm font-bold text-slate-900">Top categories</div>
        <div class="text-xs text-slate-500">By number of posts</div>
      </div>
      <a href="{{ route('admin.categories.index') }}" class="text-xs font-semibold text-brand-600 hover:underline">Manage →</a>
    </div>
    <div class="mt-3 space-y-2.5">
      @forelse($topCategories as $cat)
        @php $max = max(1, $topCategories->max('posts_count')); $pct = round(($cat->posts_count / $max) * 100); @endphp
        <div>
          <div class="flex items-center justify-between text-sm">
            <span class="font-semibold text-slate-800">{{ $cat->name }}</span>
            <span class="text-slate-500">{{ $cat->posts_count }}</span>
          </div>
          <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full bg-gradient-to-r from-brand-400 to-brand-600" style="width: {{ $pct }}%"></div>
          </div>
        </div>
      @empty
        <p class="text-sm text-slate-500">No categories yet.</p>
      @endforelse
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-sm font-bold text-slate-900">Top posts</div>
        <div class="text-xs text-slate-500">Likes + comments combined</div>
      </div>
      <a href="{{ route('admin.posts.index') }}" class="text-xs font-semibold text-brand-600 hover:underline">All posts →</a>
    </div>
    <div class="mt-3 divide-y divide-slate-100">
      @forelse($topPosts as $p)
        <div class="flex items-center justify-between gap-3 py-2.5">
          <div class="min-w-0">
            <a href="{{ route('admin.posts.show', $p) }}" class="block truncate text-sm font-semibold text-slate-800 hover:text-brand-600">
              {{ $p->title ?: \Illuminate\Support\Str::limit(strip_tags($p->status_text ?: $p->description ?: '(no text)'), 60) }}
            </a>
            <div class="mt-0.5 text-xs text-slate-500">
              <span class="badge badge-type">{{ $p->type }}</span>
              · by {{ $p->user?->name ?? 'deleted' }}
              @if($p->category) · {{ $p->category->name }} @endif
            </div>
          </div>
          <div class="text-right text-xs text-slate-500 shrink-0">
            <div><span class="font-bold text-slate-800">{{ $p->likes_count }}</span> ♥</div>
            <div><span class="font-bold text-slate-800">{{ $p->comments_count }}</span> 💬</div>
          </div>
        </div>
      @empty
        <p class="py-3 text-sm text-slate-500">No posts yet.</p>
      @endforelse
    </div>
  </div>
</div>

{{-- ───── Recent activity ───── --}}
<div class="mt-6 grid gap-4 lg:grid-cols-2">

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-sm font-bold text-slate-900">Recent signups</div>
        <div class="text-xs text-slate-500">Newest users on the platform</div>
      </div>
      <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-brand-600 hover:underline">All users →</a>
    </div>
    <div class="mt-3 divide-y divide-slate-100">
      @foreach($recentUsers as $ru)
        <div class="flex items-center gap-3 py-2.5">
          @if($ru->profile_picture)
            <img class="avatar-circle" src="{{ asset('storage/'.$ru->profile_picture) }}" alt="">
          @else
            <span class="avatar-circle">{{ strtoupper(mb_substr($ru->name,0,1)) }}</span>
          @endif
          <div class="min-w-0 flex-1">
            <a href="{{ route('admin.users.edit', $ru) }}" class="block truncate text-sm font-semibold text-slate-800 hover:text-brand-600">{{ $ru->name }}</a>
            <div class="text-xs text-slate-500">{{ '@' . $ru->username }} · {{ $ru->created_at?->diffForHumans() }}</div>
          </div>
          <span class="badge {{ $ru->role === 'admin' ? 'badge-admin' : 'badge-user' }}">{{ $ru->role }}</span>
        </div>
      @endforeach
    </div>
  </div>

  <div class="card p-5">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-sm font-bold text-slate-900">Recent posts</div>
        <div class="text-xs text-slate-500">Latest content added</div>
      </div>
      <a href="{{ route('admin.posts.index') }}" class="text-xs font-semibold text-brand-600 hover:underline">All posts →</a>
    </div>
    <div class="mt-3 divide-y divide-slate-100">
      @foreach($recentPosts as $rp)
        <div class="flex items-center justify-between gap-3 py-2.5">
          <div class="min-w-0 flex-1">
            <a href="{{ route('admin.posts.show', $rp) }}" class="block truncate text-sm font-semibold text-slate-800 hover:text-brand-600">
              {{ $rp->title ?: \Illuminate\Support\Str::limit(strip_tags($rp->status_text ?: $rp->description ?: '(no text)'), 60) }}
            </a>
            <div class="text-xs text-slate-500">
              <span class="badge badge-type">{{ $rp->type }}</span>
              · {{ $rp->user?->username ? '@'.$rp->user->username : 'deleted' }}
              · {{ $rp->created_at?->diffForHumans() }}
            </div>
          </div>
          <a href="{{ route('admin.posts.edit', $rp) }}" class="btn-xs">Edit</a>
        </div>
      @endforeach
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  const userSeries = @json($userSeries);
  const postSeries = @json($postSeries);
  const postTypes  = @json($postTypes);

  const labels = userSeries.map(p => p.date.slice(5)); // MM-DD
  const usersData = userSeries.map(p => p.count);
  const postsData = postSeries.map(p => p.count);

  const ctx1 = document.getElementById('chartGrowth');
  new Chart(ctx1, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Users', data: usersData, borderColor: '#6C63FF',
          backgroundColor: 'rgba(108,99,255,.12)', fill: true, tension: .35,
          pointRadius: 0, borderWidth: 2.5,
        },
        {
          label: 'Posts', data: postsData, borderColor: '#FF6584',
          backgroundColor: 'rgba(255,101,132,.12)', fill: true, tension: .35,
          pointRadius: 0, borderWidth: 2.5,
        },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#94a3b8', maxTicksLimit: 10 } },
        y: { grid: { color: '#eef0ff' }, ticks: { color: '#94a3b8', precision: 0 } },
      },
    },
  });

  const types = ['status', 'image', 'video', 'article'];
  const typeColors = ['#6C63FF', '#FF6584', '#22D3EE', '#F59E0B'];
  const counts = types.map(t => postTypes[t] || 0);

  const ctx2 = document.getElementById('chartTypes');
  new Chart(ctx2, {
    type: 'doughnut',
    data: {
      labels: types.map(t => t.charAt(0).toUpperCase() + t.slice(1)),
      datasets: [{ data: counts, backgroundColor: typeColors, borderWidth: 0 }],
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '64%',
      plugins: { legend: { position: 'bottom', labels: { color: '#475569', font: { size: 11 } } } },
    },
  });
})();
</script>
@endpush
