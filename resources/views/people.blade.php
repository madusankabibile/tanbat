@extends('layouts.app')
@section('title', 'People — Tanbat')

@push('head')
@include('partials._seo', [
    'title'       => 'Discover People on Tanbat',
    'description' => 'Find and connect with people on Tanbat. Browse members, follow creators, and grow your network.',
    'url'         => route('people'),
])
@endpush

@section('content')
@include('partials.navbar')

<div class="home-shell mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  @include('partials.side-rails')

  <main class="home-center">

    <header class="mb-5">
      <h1 class="text-xl font-extrabold text-slate-900 sm:text-2xl">Discover people</h1>
      <p class="mt-1 text-sm text-slate-500">
        Reconnect with people you follow, see who's following you, find new accounts to follow.
      </p>
    </header>

    {{-- Section tabs --}}
    <div id="peopleTabs" class="mb-4 flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
      <button data-section="following" class="people-tab">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Following
        <span class="people-count" data-count="following">0</span>
      </button>
      <button data-section="followers" class="people-tab">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        Followers
        <span class="people-count" data-count="followers">0</span>
      </button>
      <button data-section="visitors" class="people-tab">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Profile visits
        <span class="people-count" data-count="visitors">0</span>
      </button>
      <button data-section="discover" class="people-tab">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
        Discover
        <span class="people-count" data-count="discover">0</span>
      </button>
    </div>

    {{-- Filter / search bar --}}
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
          <option value="name">Sort by name</option>
          <option value="recent">Most recent</option>
          <option value="newest">Newest members</option>
          <option value="oldest">Oldest members</option>
          <option value="age_desc">Age (high → low)</option>
          <option value="age_asc">Age (low → high)</option>
        </select>

        <button type="button" id="filterReset" class="filter-reset">Reset</button>
      </div>
    </section>

    {{-- Section heading --}}
    <div class="section-head mt-5 mb-2">
      <h3 id="sectionTitle" class="text-base font-extrabold text-slate-900">Following</h3>
      <span id="sectionTotal" class="text-xs font-semibold text-slate-500">0 people</span>
    </div>

    {{-- Cards grid --}}
    <div id="peopleGrid" class="people-grid-full"></div>

    {{-- Loading --}}
    <div id="peopleLoading" class="hidden py-10 text-center text-sm text-slate-500">
      <span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent align-middle"></span>
      <span class="ml-2">Loading…</span>
    </div>

    {{-- Empty state --}}
    <div id="peopleEmpty" class="hidden py-16 text-center">
      <div class="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-500">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      </div>
      <div class="text-sm font-semibold text-slate-700" id="peopleEmptyTitle">Nobody here yet</div>
      <div class="text-xs text-slate-500" id="peopleEmptyDesc">Try a different filter or section.</div>
    </div>

    {{-- Load more --}}
    <div class="mt-6 text-center">
      <button type="button" id="peopleLoadMore"
              class="hidden inline-flex items-center gap-2 rounded-full bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-soft hover:bg-brand-600">
        Show more
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>

  </main>
</div>

@include('partials.user-sheet')
@include('partials.share-modal')

<style>
/* Tabs */
.people-tab {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
  background: #fff;
  border: 1px solid #E5E7EB;
  white-space: nowrap;
  transition: .2s;
}
.people-tab:hover { color: #6C63FF; border-color: #C5C9FF; }
.people-tab.active {
  background: linear-gradient(135deg,#6C63FF,#5A52D5);
  color: #fff;
  border-color: transparent;
  box-shadow: 0 4px 14px rgba(108,99,255,.32);
}
.people-count {
  display: inline-grid; place-items: center;
  min-width: 22px; height: 18px; padding: 0 6px;
  font-size: 10px; font-weight: 700;
  color: #475569; background: #F1F5F9;
  border-radius: 999px;
}
.people-tab.active .people-count { background: rgba(255,255,255,.25); color: #fff; }

/* Filters */
.filters-card {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 14px;
  padding: 12px;
  box-shadow: 0 1px 2px rgba(20,20,50,.04);
}
.filters-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}
@media (min-width: 700px) {
  .filters-row { grid-template-columns: 2fr 1fr 1fr; }
}
@media (min-width: 1024px) {
  .filters-row {
    grid-template-columns: 1.8fr 1fr 1fr auto 1.1fr auto;
    align-items: center;
  }
}

.filter-search {
  position: relative;
  display: flex;
  align-items: center;
}
.filter-search input {
  width: 100%;
  border-radius: 9999px;
  border: 1px solid #E5E7EB;
  background: #F8FAFC;
  padding: 9px 36px 9px 38px;
  font-size: 13px;
  color: #0F172A;
  transition: .15s;
}
.filter-search input:focus {
  outline: none;
  border-color: #6C63FF;
  background: #fff;
  box-shadow: 0 0 0 4px rgba(108,99,255,.12);
}
.filter-search .search-icon {
  position: absolute; left: 14px;
  width: 14px; height: 14px;
  color: #94A3B8;
  pointer-events: none;
}
.filter-search .search-clear {
  position: absolute; right: 10px;
  height: 22px; width: 22px;
  display: grid; place-items: center;
  border-radius: 9999px;
  background: transparent;
  color: #94A3B8;
}
.filter-search .search-clear:hover { background: #E5E7EB; color: #475569; }
.filter-search .search-clear svg { width: 12px; height: 12px; }

.filter-select {
  appearance: none;
  background-color: #F8FAFC;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  border: 1px solid #E5E7EB;
  border-radius: 9999px;
  padding: 9px 36px 9px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #0F172A;
  cursor: pointer;
  transition: .15s;
}
.filter-select:focus { outline: none; border-color: #6C63FF; background-color: #fff; box-shadow: 0 0 0 4px rgba(108,99,255,.12); }

.filter-age {
  display: inline-flex; align-items: center; gap: 6px;
  background: #F8FAFC;
  border: 1px solid #E5E7EB;
  border-radius: 9999px;
  padding: 5px 12px;
}
.filter-age-label { font-size: 12px; font-weight: 700; color: #64748B; }
.filter-age input {
  width: 52px;
  background: transparent;
  border: 0;
  font-size: 13px;
  font-weight: 600;
  color: #0F172A;
  text-align: center;
}
.filter-age input:focus { outline: none; }
.filter-age-dash { color: #94A3B8; font-weight: 700; }

.filter-reset {
  background: transparent;
  border: 1px solid #E5E7EB;
  border-radius: 9999px;
  padding: 9px 14px;
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  transition: .15s;
}
.filter-reset:hover { background: #FEF2F2; border-color: #FCA5A5; color: #BE123C; }

/* Section heading */
.section-head {
  display: flex; align-items: center; justify-content: space-between;
}

/* People grid */
.people-grid-full {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
@media (min-width: 640px) {
  .people-grid-full { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1024px) {
  .people-grid-full { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

.user-card {
  position: relative;
  display: flex;
  flex-direction: column;
  border: 1px solid #E5E7EB;
  border-radius: 16px;
  background: #fff;
  overflow: hidden;
  transition: transform .15s ease, box-shadow .2s ease, border-color .15s ease;
}
.user-card:hover {
  transform: translateY(-2px);
  border-color: #C5C9FF;
  box-shadow: 0 12px 26px rgba(20,20,50,.08);
}
.user-card .uc-cover {
  height: 68px;
  background:
    radial-gradient(120% 80% at 10% 0%, #C5C9FF 0%, transparent 60%),
    radial-gradient(120% 80% at 90% 100%, #FFC8DC 0%, transparent 60%),
    linear-gradient(135deg,#6C63FF,#5A52D5);
}
.user-card .uc-body {
  position: relative;
  padding: 0 16px 16px;
  text-align: center;
}
.user-card .uc-avatar {
  margin: -28px auto 8px;
  height: 64px; width: 64px;
  border-radius: 9999px;
  background: linear-gradient(135deg,#6C63FF,#FF6584);
  color: #fff; font-weight: 800; font-size: 22px;
  display: grid; place-items: center;
  border: 3px solid #fff;
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(20,20,50,.08);
}
.user-card .uc-avatar img { width: 100%; height: 100%; object-fit: cover; }
.user-card .uc-name {
  font-size: 14px; font-weight: 800; color: #1E1B4B;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-card .uc-handle {
  font-size: 12px; color: #6B7280;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-card .uc-meta {
  margin-top: 8px;
  display: flex; flex-wrap: wrap; gap: 4px 6px;
  justify-content: center;
}
.user-card .uc-chip {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: 9999px;
  background: #F1F5F9; color: #475569;
  font-size: 11px; font-weight: 600;
}
.user-card .uc-chip svg { width: 10px; height: 10px; }
.user-card .uc-visit {
  margin-top: 6px;
  font-size: 11px;
  color: #64748B;
  font-weight: 600;
}
.user-card .uc-actions {
  margin-top: 12px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.user-card .uc-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 8px 12px;
  border-radius: 9999px;
  font-size: 12px; font-weight: 700;
  border: 1px solid transparent;
  transition: .15s;
  cursor: pointer;
}
.user-card .uc-btn svg { width: 12px; height: 12px; }
.user-card .uc-btn-view {
  background: #F8FAFC;
  border-color: #E5E7EB;
  color: #1E1B4B;
}
.user-card .uc-btn-view:hover { background: #EEF2FF; border-color: #C5C9FF; color: #5A52D5; }
.user-card .uc-btn-follow {
  background: linear-gradient(135deg,#6C63FF,#5A52D5);
  color: #fff;
  box-shadow: 0 4px 12px rgba(108,99,255,.32);
}
.user-card .uc-btn-follow:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(108,99,255,.4); }
.user-card .uc-btn-follow.is-following {
  background: #fff;
  color: #5A52D5;
  border-color: #C5C9FF;
  box-shadow: none;
}
.user-card .uc-btn-follow.is-following:hover {
  background: #FEF2F2;
  border-color: #FCA5A5;
  color: #BE123C;
}
.user-card .uc-btn-follow.is-following:hover .uc-follow-label::before { content: 'Unfollow'; }
.user-card .uc-btn-follow.is-following:hover .uc-follow-label-text { display: none; }
.user-card .uc-btn-follow[disabled] { opacity: .6; cursor: not-allowed; }
.user-card .uc-btn-follow.is-self {
  background: #F1F5F9;
  color: #94A3B8;
  cursor: default;
  box-shadow: none;
}
.user-card .uc-skeleton {
  height: 220px;
  border-radius: 16px;
  background: linear-gradient(90deg, #F1F5F9 25%, #F8FAFC 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite linear;
}
</style>

@push('head')
<script>
  window.__PEOPLE__ = {
    urls: {
      summary: {!! json_encode(url('/api/people/summary')) !!},
      filters: {!! json_encode(url('/api/people/filters')) !!},
      list:    {!! json_encode(url('/api/people')) !!},
      follow:  {!! json_encode(url('/api/users') . '/:id/follow') !!},
      profileBase: {!! json_encode(rtrim(url('/'), '/')) !!},
    },
    initial: {!! json_encode(request()->query('section', 'following')) !!},
  };
</script>
@endpush

@push('scripts')
@vite(['resources/js/people.js'])
@endpush
@endsection
