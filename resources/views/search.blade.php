@extends('layouts.app')
@section('title', ($q ? '"' . $q . '" — ' : '') . 'Search — Tanbat')

@section('content')
@include('partials.navbar')

<div class="home-shell mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  @include('partials.side-rails')

  <main class="home-center">

    <header class="mb-4">
      <h1 class="text-xl font-extrabold text-slate-900 sm:text-2xl">
        @if($q)
          Results for <span class="text-brand-600">"{{ $q }}"</span>
        @else
          Search Tanbat
        @endif
      </h1>
      <p id="searchSummary" class="mt-1 text-sm text-slate-500">
        @if($q)
          Looking up posts, images, videos, people and tags…
        @else
          Start typing in the search bar above to find posts, people and tags.
        @endif
      </p>
    </header>

    {{-- Tabs --}}
    <div id="searchTabs" class="mb-5 flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
      <button data-tab="all"    class="search-tab">All</button>
      <button data-tab="posts"  class="search-tab">Posts <span class="search-count" data-count="posts">0</span></button>
      <button data-tab="images" class="search-tab">Images <span class="search-count" data-count="images">0</span></button>
      <button data-tab="videos" class="search-tab">Videos <span class="search-count" data-count="videos">0</span></button>
      <button data-tab="people" class="search-tab">People <span class="search-count" data-count="people">0</span></button>
      <button data-tab="tags"   class="search-tab">Tags <span class="search-count" data-count="tags">0</span></button>
    </div>

    {{-- Results container --}}
    <div id="searchResults" class="space-y-4"></div>

    {{-- Loading --}}
    <div id="searchLoading" class="hidden py-10 text-center text-sm text-slate-500">
      <span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-brand-300 border-t-transparent align-middle"></span>
      <span class="ml-2">Searching…</span>
    </div>

    {{-- Empty state --}}
    <div id="searchEmpty" class="hidden py-16 text-center">
      <div class="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-500">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </div>
      <div class="text-sm font-semibold text-slate-700">No results</div>
      <div class="text-xs text-slate-500">Try different keywords or check the spelling.</div>
    </div>

    {{-- Load more --}}
    <div class="mt-6 text-center">
      <button type="button" id="searchLoadMore"
              class="hidden inline-flex items-center gap-2 rounded-full bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-soft hover:bg-brand-600">
        Show more
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>

  </main>
</div>

@include('partials.post-detail-modal')
@include('partials.user-sheet')
@include('partials.share-modal')

<style>
.search-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0.4rem 0.95rem;
  border-radius: 9999px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
  background: #fff;
  border: 1px solid #E5E7EB;
  white-space: nowrap;
  transition: .2s;
}
.search-tab:hover { color: #6C63FF; border-color: #C5C9FF; }
.search-tab.active { background: linear-gradient(135deg,#6C63FF,#5A52D5); color:#fff; border-color: transparent; box-shadow: 0 4px 14px rgba(108,99,255,.32); }
.search-count {
  display: inline-grid;
  place-items: center;
  min-width: 22px;
  height: 18px;
  padding: 0 6px;
  font-size: 10px;
  font-weight: 700;
  color: #475569;
  background: #F1F5F9;
  border-radius: 999px;
}
.search-tab.active .search-count { background: rgba(255,255,255,.25); color:#fff; }

/* People list cards */
.people-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
}
.person-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid #E5E7EB;
  background: #fff;
  transition: .2s;
}
.person-card:hover { box-shadow: 0 6px 20px rgba(20,20,50,.06); border-color: #C5C9FF; }
.person-card .pc-avatar img,
.person-card .pc-avatar span {
  width: 48px; height: 48px; border-radius: 9999px; object-fit: cover;
  display: grid; place-items: center;
  background: linear-gradient(135deg,#6C63FF,#A78BFA); color:#fff; font-weight:700;
}
.person-card .pc-name { font-weight: 700; color:#0F172A; font-size: 14px; }
.person-card .pc-handle { color:#64748B; font-size: 12px; }

/* Image / video grid (for image & video tabs only) */
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 10px;
}
.media-tile {
  position: relative;
  aspect-ratio: 1 / 1;
  border-radius: 12px;
  overflow: hidden;
  background: #F1F5F9;
  display: block;
  cursor: pointer;
}
.media-tile img { width:100%; height:100%; object-fit:cover; transition: transform .35s; }
.media-tile:hover img { transform: scale(1.04); }
.media-tile .badge {
  position: absolute; top: 8px; left: 8px;
  padding: 3px 8px; border-radius: 999px;
  background: rgba(15,23,42,.8); color:#fff; font-size: 10px; font-weight:700;
  text-transform: uppercase; letter-spacing: .03em;
}
.media-tile .play {
  position: absolute; inset: 0; display:grid; place-items:center;
  background: linear-gradient(180deg, transparent 50%, rgba(0,0,0,.45));
  color:#fff; pointer-events:none;
}
.media-tile .play svg { width: 36px; height: 36px; filter: drop-shadow(0 2px 6px rgba(0,0,0,.4)); }

/* Tag chips */
.tag-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 10px;
}
.tag-card {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border-radius: 12px;
  background: #fff; border: 1px solid #E5E7EB;
  transition: .2s;
}
.tag-card:hover { border-color:#C5C9FF; box-shadow: 0 6px 18px rgba(20,20,50,.06); }
.tag-card .tg-hash {
  width: 36px; height: 36px; display:grid; place-items:center;
  border-radius: 10px; background:#EEF2FF; color:#5A52D5; font-weight: 800;
}
.tag-card .tg-name { font-size: 14px; font-weight: 700; color:#0F172A; }
.tag-card .tg-count { font-size: 11px; color:#64748B; }

/* Book result card */
.search-book-card {
  display: grid;
  grid-template-columns: 96px 1fr;
  gap: 16px;
  padding: 14px;
  border: 1px solid #E5E7EB;
  border-radius: 16px;
  background: #fff;
  transition: transform .15s, box-shadow .15s, border-color .15s;
}
.search-book-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(108,99,255,.16); border-color: #C5C9FF; }
.search-book-card .sb-cover {
  width: 96px; aspect-ratio: 2 / 3;
  border-radius: 10px; overflow: hidden;
  background: linear-gradient(135deg,#EEF2FF,#FCE7F3);
  border: 1px solid #E5E7EB;
  display: grid; place-items: center;
}
.search-book-card .sb-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.search-book-card .sb-body { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.search-book-card .sb-meta-row { display: flex; align-items: center; gap: 8px; font-size: 11px; color: #94A3B8; }
.search-book-card .sb-badge {
  background: #F5F3FF; color: #6D28D9;
  padding: 2px 7px; border-radius: 9999px;
  font-size: 10px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase;
}
.search-book-card .sb-when { color: #94A3B8; }
.search-book-card .sb-title { font-size: 16px; font-weight: 800; color: #1E1B4B; line-height: 1.25; margin-top: 2px; word-break: break-word; }
.search-book-card .sb-author { font-size: 13px; color: #475569; }
.search-book-card .sb-author strong { color: #1E1B4B; }
.search-book-card .sb-pub { font-size: 12px; color: #6B7280; }
.search-book-card .sb-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 4px; }
.search-book-card .sb-tag {
  font-size: 10px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase;
  padding: 2.5px 7px; border-radius: 6px;
}
.search-book-card .sb-tag.ext  { background: #DBEAFE; color: #1D4ED8; }
.search-book-card .sb-tag.size { background: #DCFCE7; color: #047857; }
.search-book-card .sb-tag.year { background: #FCE7F3; color: #9D174D; }
.search-book-card .sb-tag.lang { background: #FEF3C7; color: #92400E; }
.search-book-card .sb-blurb {
  margin-top: 4px;
  font-size: 13px; color: #475569; line-height: 1.55;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
@media (max-width: 540px) {
  .search-book-card { grid-template-columns: 80px 1fr; gap: 12px; padding: 12px; }
  .search-book-card .sb-cover { width: 80px; }
}

/* Section heading for "All" view */
.section-head {
  display: flex; align-items: center; justify-content: space-between;
  margin: 18px 0 10px;
}
.section-head h3 {
  font-size: 13px; font-weight: 800; text-transform: uppercase;
  letter-spacing: .06em; color: #334155;
}
.section-head .see-all {
  font-size: 12px; font-weight: 700; color:#5A52D5;
}
.section-head .see-all:hover { text-decoration: underline; }
</style>

@push('head')
<script>
  window.__SEARCH__ = {
    q:   {!! json_encode($q ?? '') !!},
    tab: {!! json_encode($tab ?? 'all') !!},
    urls: {
      results: {!! json_encode(url('/api/search/results')) !!},
      page:    {!! json_encode(url('/search')) !!},
    },
  };
</script>
@endpush

@push('scripts')
@vite(['resources/js/search.js'])
@endpush
@endsection
