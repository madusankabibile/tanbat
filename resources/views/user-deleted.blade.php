@extends('layouts.app')
@section('title', 'Account not available — Tanbat')

@section('content')
@auth
  @include('partials.navbar')
@else
  @include('partials.guest-bar')
@endauth

<div class="lost-shell mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  {{-- ─────────── LEFT RAIL ─────────── --}}
  <aside class="lost-left">
    <div class="sticky top-[88px] space-y-4">
      <section class="panel brand-panel">
        <span class="brand-orb">T</span>
        <div class="brand-text">
          <strong>Tanbat</strong>
          <span>Connect &amp; share with people worldwide.</span>
        </div>
      </section>

      <nav class="panel quick-links">
        <a href="{{ url('/') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Home</a>
        <a href="{{ url('/discover/people') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Discover people</a>
        <a href="{{ url('/books') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>Library</a>
        <a href="{{ url('/assistant') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 1 4 4v1a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M5 22v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/></svg>Assistant</a>
      </nav>

      <div class="left-footer">
        <span>© Tanbat</span> · <a href="{{ url('/privacy') }}">Privacy</a> · <a href="#">Terms</a>
      </div>
    </div>
  </aside>

  {{-- ─────────── CENTER ─────────── --}}
  <main class="lost-center">

    {{-- Deleted-account notice --}}
    <section class="deleted-hero panel">
      <span class="dh-orb">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="23" y2="14"/><line x1="23" y1="8" x2="17" y2="14"/></svg>
      </span>
      <h1 class="dh-title">This account is no longer on Tanbat</h1>
      <p class="dh-sub">
        The account <b>&#64;{{ $username }}</b> was deleted from our servers, so its
        profile, photos and videos are no longer available. It happens — but there
        are plenty of active people to connect with instead.
      </p>
    </section>

    {{-- Suggested-people heading --}}
    <div class="section-head mt-6 mb-3">
      <h2 class="text-base font-extrabold text-slate-900 sm:text-lg">People you can connect with</h2>
      <span id="geoNote" class="geo-note hidden"></span>
    </div>

    {{-- Filters --}}
    <section class="filters-card">
      <div class="filters-row">
        <div class="filter-search">
          <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input id="peopleSearch" type="search" placeholder="Search name or @username…" autocomplete="off">
          <button type="button" id="peopleSearchClear" class="search-clear hidden" aria-label="Clear">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>

        <select id="filterCountry" class="filter-select">
          <option value="all">All countries</option>
          @foreach($countries as $c)
            <option value="{{ $c }}">{{ $c }}</option>
          @endforeach
        </select>

        <select id="filterGender" class="filter-select">
          <option value="all">Any gender</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
          <option value="other">Other</option>
        </select>

        <div class="filter-age">
          <span class="filter-age-label">Age</span>
          <input id="ageMin" type="number" min="0" max="120" placeholder="Min">
          <span class="filter-age-dash">–</span>
          <input id="ageMax" type="number" min="0" max="120" placeholder="Max">
        </div>

        <select id="filterSort" class="filter-select">
          <option value="suggested">Suggested for you</option>
          <option value="recent">Most recent</option>
          <option value="newest">Newest members</option>
          <option value="oldest">Oldest members</option>
          <option value="age_desc">Age (high → low)</option>
          <option value="age_asc">Age (low → high)</option>
          <option value="name">Name (A → Z)</option>
        </select>

        <button type="button" id="filterReset" class="filter-reset">Reset</button>
      </div>
    </section>

    {{-- Cards grid --}}
    <div id="peopleGrid" class="people-grid-full mt-4"></div>

    <div id="peopleLoading" class="hidden py-10 text-center text-sm text-slate-500">
      <span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent align-middle"></span>
      <span class="ml-2">Loading…</span>
    </div>

    <div id="peopleEmpty" class="hidden py-16 text-center">
      <div class="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-500">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      </div>
      <div class="text-sm font-semibold text-slate-700">Nobody matches that filter</div>
      <div class="text-xs text-slate-500">Try widening your search or resetting the filters.</div>
    </div>

    <div class="mt-6 text-center">
      <button type="button" id="peopleLoadMore"
              class="hidden inline-flex items-center gap-2 rounded-full bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-soft hover:bg-brand-600">
        Show more
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>

  </main>

  {{-- ─────────── RIGHT RAIL (2 ads + statistics) ─────────── --}}
  <aside class="lost-right">
    <div class="sticky top-[88px] space-y-4 right-scroll">

      <section class="panel ad-card">
        <span class="ad-tag">Sponsored</span>
        @include('partials.ad-banner')
      </section>

      <section class="panel stats-card">
        <div class="panel-head">
          <h4>Tanbat at a glance</h4>
          <span class="head-sub">community</span>
        </div>
        <div class="stats-grid">
          <div class="stat-tile">
            <span class="stat-icon" style="background:#EEF2FF;color:#4338CA"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg></span>
            <span class="stat-val">{{ number_format($stats['users']) }}</span>
            <span class="stat-lbl">Members</span>
          </div>
          <div class="stat-tile">
            <span class="stat-icon" style="background:#ECFEFF;color:#0E7490"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
            <span class="stat-val">{{ number_format($stats['posts']) }}</span>
            <span class="stat-lbl">Posts</span>
          </div>
          <div class="stat-tile">
            <span class="stat-icon" style="background:#FEF2F2;color:#BE123C"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg></span>
            <span class="stat-val">{{ number_format($stats['likes']) }}</span>
            <span class="stat-lbl">Reactions</span>
          </div>
          <div class="stat-tile">
            <span class="stat-icon" style="background:#FEFCE8;color:#A16207"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg></span>
            <span class="stat-val">{{ number_format($stats['comments']) }}</span>
            <span class="stat-lbl">Comments</span>
          </div>
        </div>
      </section>

      <section class="panel ad-card">
        <span class="ad-tag">Sponsored</span>
        @include('partials.ad-banner')
      </section>

    </div>
  </aside>

</div>

<style>
/* ─────────── Layout grid (mirrors .home-shell) ─────────── */
.lost-shell { display: grid; grid-template-columns: 1fr; gap: 20px; }
.lost-left, .lost-right { display: none; }
.lost-center { min-width: 0; grid-column: 1; grid-row: 1; }
@media (min-width: 1024px) {
  .lost-shell { grid-template-columns: 280px minmax(0, 1fr); }
  .lost-left   { display: block; grid-column: 1; grid-row: 1; }
  .lost-center { grid-column: 2; grid-row: 1; }
}
@media (min-width: 1280px) {
  .lost-shell { grid-template-columns: 280px minmax(0, 1fr) 320px; }
  .lost-right  { display: block; grid-column: 3; grid-row: 1; }
}
.right-scroll { max-height: calc(100vh - 88px - 20px); overflow-y: auto; scrollbar-width: none; }
.right-scroll::-webkit-scrollbar { display: none; }

.panel { background:#fff; border:1px solid #E5E7EB; border-radius:12px; box-shadow:0 1px 2px rgba(20,20,50,.04); overflow:hidden; }
.panel-head { display:flex; align-items:baseline; justify-content:space-between; padding:14px 16px 8px; }
.panel-head h4 { font-size:14px; font-weight:700; color:#1E1B4B; }
.panel-head .head-sub { font-size:11px; color:#94A3B8; }

/* Left rail */
.brand-panel { display:flex; align-items:center; gap:12px; padding:14px 16px; }
.brand-orb { height:40px; width:40px; border-radius:12px; flex-shrink:0; display:grid; place-items:center; color:#fff; font-weight:800; font-size:18px; background:linear-gradient(135deg,#6C63FF,#FF6584); box-shadow:0 6px 16px rgba(108,99,255,.28); }
.brand-text { display:flex; flex-direction:column; line-height:1.3; }
.brand-text strong { font-size:15px; font-weight:800; color:#1E1B4B; }
.brand-text span { font-size:11.5px; color:#6B7280; margin-top:2px; }
.quick-links { padding:6px; }
.quick-links a { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; font-size:13.5px; font-weight:600; color:#334155; text-decoration:none; transition:.15s; }
.quick-links a:hover { background:#EEF2FF; color:#5A52D5; }
.quick-links svg { width:17px; height:17px; color:#94A3B8; }
.quick-links a:hover svg { color:#6C63FF; }
.left-footer { font-size:11px; color:#94A3B8; padding:8px 4px 0; line-height:1.7; }
.left-footer a { color:#94A3B8; }
.left-footer a:hover { color:#6C63FF; }

/* Deleted hero */
.deleted-hero { padding:28px 24px; text-align:center; background:linear-gradient(180deg,#FFFFFF 0%,#F7F8FF 100%); }
.dh-orb { display:inline-grid; place-items:center; height:64px; width:64px; border-radius:9999px; color:#fff; background:linear-gradient(135deg,#6C63FF,#5A52D5); box-shadow:0 10px 26px rgba(108,99,255,.3); }
.dh-orb svg { width:30px; height:30px; }
.dh-title { margin-top:16px; font-size:22px; font-weight:800; color:#1E1B4B; letter-spacing:-.01em; }
.dh-sub { margin:8px auto 0; max-width:520px; font-size:13.5px; color:#475569; line-height:1.6; }
.dh-sub b { color:#1E1B4B; }

.section-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.geo-note {
  display:inline-flex; align-items:center; gap:6px;
  font-size:11.5px; font-weight:700; color:#5A52D5;
  background:#EEF2FF; border:1px solid #DDD9FF; border-radius:9999px; padding:4px 10px;
}
.geo-note.hidden { display:none; }

/* Filters */
.filters-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:12px; box-shadow:0 1px 2px rgba(20,20,50,.04); }
.filters-row { display:grid; grid-template-columns:1fr; gap:8px; }
@media (min-width:700px) { .filters-row { grid-template-columns:2fr 1fr 1fr; } }
@media (min-width:1024px) { .filters-row { grid-template-columns:1.8fr 1fr 1fr auto 1.1fr auto; align-items:center; } }
.filter-search { position:relative; display:flex; align-items:center; }
.filter-search input { width:100%; border-radius:9999px; border:1px solid #E5E7EB; background:#F8FAFC; padding:9px 36px 9px 38px; font-size:13px; color:#0F172A; transition:.15s; }
.filter-search input:focus { outline:none; border-color:#6C63FF; background:#fff; box-shadow:0 0 0 4px rgba(108,99,255,.12); }
.filter-search .search-icon { position:absolute; left:14px; width:14px; height:14px; color:#94A3B8; pointer-events:none; }
.filter-search .search-clear { position:absolute; right:10px; height:22px; width:22px; display:grid; place-items:center; border-radius:9999px; background:transparent; color:#94A3B8; }
.filter-search .search-clear:hover { background:#E5E7EB; color:#475569; }
.filter-search .search-clear svg { width:12px; height:12px; }
.filter-select { appearance:none; background-color:#F8FAFC; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center; border:1px solid #E5E7EB; border-radius:9999px; padding:9px 36px 9px 14px; font-size:13px; font-weight:600; color:#0F172A; cursor:pointer; transition:.15s; }
.filter-select:focus { outline:none; border-color:#6C63FF; background-color:#fff; box-shadow:0 0 0 4px rgba(108,99,255,.12); }
.filter-age { display:inline-flex; align-items:center; gap:6px; background:#F8FAFC; border:1px solid #E5E7EB; border-radius:9999px; padding:5px 12px; }
.filter-age-label { font-size:12px; font-weight:700; color:#64748B; }
.filter-age input { width:52px; background:transparent; border:0; font-size:13px; font-weight:600; color:#0F172A; text-align:center; }
.filter-age input:focus { outline:none; }
.filter-age-dash { color:#94A3B8; font-weight:700; }
.filter-reset { background:transparent; border:1px solid #E5E7EB; border-radius:9999px; padding:9px 14px; font-size:12px; font-weight:700; color:#475569; transition:.15s; }
.filter-reset:hover { background:#FEF2F2; border-color:#FCA5A5; color:#BE123C; }

/* People grid + cards (shared look with the People page) */
.people-grid-full { display:grid; grid-template-columns:1fr; gap:12px; }
@media (min-width:640px) { .people-grid-full { grid-template-columns:repeat(2, minmax(0,1fr)); } }
@media (min-width:1024px) { .people-grid-full { grid-template-columns:repeat(2, minmax(0,1fr)); } }
@media (min-width:1280px) { .people-grid-full { grid-template-columns:repeat(3, minmax(0,1fr)); } }
.user-card { position:relative; display:flex; flex-direction:column; border:1px solid #E5E7EB; border-radius:16px; background:#fff; overflow:hidden; transition:transform .15s ease, box-shadow .2s ease, border-color .15s ease; }
.user-card:hover { transform:translateY(-2px); border-color:#C5C9FF; box-shadow:0 12px 26px rgba(20,20,50,.08); }
.user-card .uc-cover { height:68px; background:radial-gradient(120% 80% at 10% 0%, #C5C9FF 0%, transparent 60%), radial-gradient(120% 80% at 90% 100%, #FFC8DC 0%, transparent 60%), linear-gradient(135deg,#6C63FF,#5A52D5); }
.user-card .uc-body { position:relative; padding:0 16px 16px; text-align:center; }
.user-card .uc-avatar { position:relative; margin:-28px auto 8px; height:64px; width:64px; border-radius:9999px; background:linear-gradient(135deg,#6C63FF,#FF6584); color:#fff; font-weight:800; font-size:22px; display:grid; place-items:center; border:3px solid #fff; overflow:hidden; box-shadow:0 4px 14px rgba(20,20,50,.08); }
.user-card .uc-avatar img { width:100%; height:100%; object-fit:cover; }
.user-card .uc-avatar .uc-dot { position:absolute; bottom:3px; right:3px; height:12px; width:12px; border-radius:9999px; background:#10B981; border:2px solid #fff; }
.user-card .uc-name { font-size:14px; font-weight:800; color:#1E1B4B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.user-card .uc-handle { font-size:12px; color:#6B7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.user-card .uc-meta { margin-top:8px; display:flex; flex-wrap:wrap; gap:4px 6px; justify-content:center; }
.user-card .uc-chip { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:9999px; background:#F1F5F9; color:#475569; font-size:11px; font-weight:600; }
.user-card .uc-chip svg { width:10px; height:10px; }
.user-card .uc-chip.is-online { background:#ECFDF5; color:#047857; }
.user-card .uc-actions { margin-top:12px; display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.user-card .uc-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:8px 12px; border-radius:9999px; font-size:12px; font-weight:700; border:1px solid transparent; transition:.15s; cursor:pointer; text-decoration:none; }
.user-card .uc-btn svg { width:12px; height:12px; }
.user-card .uc-btn-view { background:#F8FAFC; border-color:#E5E7EB; color:#1E1B4B; }
.user-card .uc-btn-view:hover { background:#EEF2FF; border-color:#C5C9FF; color:#5A52D5; }
.user-card .uc-btn-follow { background:linear-gradient(135deg,#6C63FF,#5A52D5); color:#fff; box-shadow:0 4px 12px rgba(108,99,255,.32); }
.user-card .uc-btn-follow:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(108,99,255,.4); }
.user-card .uc-btn-follow.is-following { background:#fff; color:#5A52D5; border-color:#C5C9FF; box-shadow:none; }
.user-card .uc-btn-follow.is-following:hover { background:#FEF2F2; border-color:#FCA5A5; color:#BE123C; }
.user-card .uc-btn-follow.is-following:hover .uc-follow-label-text::after { content:'Unfollow'; }
.user-card .uc-btn-follow.is-following:hover .uc-follow-label-text > span { display:none; }
.user-card .uc-btn-follow[disabled] { opacity:.6; cursor:not-allowed; }
.uc-skeleton { height:220px; border-radius:16px; background:linear-gradient(90deg,#F1F5F9 25%,#F8FAFC 50%,#F1F5F9 75%); background-size:200% 100%; animation:shimmer 1.4s infinite linear; }
@keyframes shimmer { from { background-position:200% 0; } to { background-position:-200% 0; } }

/* Right rail: ad + stats */
.ad-card { padding:12px; position:relative; }
.ad-card .ad-tag { position:absolute; top:14px; left:16px; font-size:10px; font-weight:700; color:#94A3B8; text-transform:uppercase; letter-spacing:.6px; background:rgba(255,255,255,.85); padding:2px 6px; border-radius:4px; z-index:2; }
.stats-card .stats-grid { padding:4px 14px 14px; display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.stat-tile { display:grid; grid-template-areas:"icon val" "icon lbl"; grid-template-columns:30px 1fr; column-gap:9px; align-items:center; background:#F8FAFC; border-radius:10px; padding:8px 10px; }
.stat-tile .stat-icon { grid-area:icon; height:30px; width:30px; border-radius:8px; display:grid; place-items:center; }
.stat-tile .stat-val { grid-area:val; font-size:15px; font-weight:800; color:#1E1B4B; font-variant-numeric:tabular-nums; }
.stat-tile .stat-lbl { grid-area:lbl; font-size:10px; font-weight:600; color:#64748B; text-transform:uppercase; letter-spacing:.3px; }
</style>

@push('head')
<script>
  window.__LOST__ = {
    username: {!! json_encode($username) !!},
    urls: {
      suggested:   {!! json_encode(url('/api/discover/suggested')) !!},
      follow:      {!! json_encode(url('/api/users') . '/:id/follow') !!},
      profileBase: {!! json_encode(rtrim(url('/'), '/')) !!},
    },
  };
</script>
@endpush

@push('scripts')
@vite(['resources/js/user-deleted.js'])
@endpush
@endsection
