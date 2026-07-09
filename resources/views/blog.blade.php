@extends('layouts.app')
@section('title', 'Blog — Tanbat')
@section('meta_description', 'Read the latest and most popular articles on Tanbat — ranked for you by what\'s trending in your country, hot right now, and freshly published.')

@section('content')
@auth
  @include('partials.navbar')
@else
  @include('partials.guest-bar')
@endauth

<div class="blog-shell mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  {{-- ─────────── LEFT PANEL ─────────── --}}
  <aside class="blog-left">
    <div class="sticky top-[88px] space-y-4">

      {{-- Write CTA button --}}
      <a href="{{ url('/articles/create') }}" class="blog-write-btn">
        <span class="bw-orb">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        </span>
        <span class="bw-text">
          <strong>Write an article</strong>
          <span class="bw-sub">Share your story with Tanbat</span>
        </span>
      </a>

      {{-- Sort quick-nav --}}
      <nav class="blog-panel">
        <div class="bp-head">Browse</div>
        <button type="button" class="blog-navlink" data-sort="for-you">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/></svg>
          <span>For you</span>
          @if($geoCountry)<span class="bl-flag">{{ $geoCountry }}</span>@endif
        </button>
        <button type="button" class="blog-navlink" data-sort="hot">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5Z"/></svg>
          <span>Hot right now</span>
        </button>
        <button type="button" class="blog-navlink" data-sort="new">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>Newest</span>
        </button>
      </nav>

      {{-- How ranking works — explains the geo personalisation --}}
      <div class="blog-panel blog-explain">
        <div class="bp-head">How “For you” works</div>
        <p>
          @if($geoCountryName)
            We rank articles by what readers in {{ $geoCountryName }} are opening right now —
          @else
            We rank articles by what readers in your country are opening right now —
          @endif
          blended with what's hot and freshly published across Tanbat.
        </p>
      </div>

      <div class="left-footer">
        <span>© Tanbat</span> · <a href="{{ url('/privacy') }}">Privacy</a> · <a href="{{ url('/books') }}">Library</a>
      </div>
    </div>
  </aside>

  {{-- ─────────── CENTER ─────────── --}}
  <main class="blog-main">

    {{-- Hero --}}
    <header class="blog-hero">
      <div class="bh-copy">
        <h1>The Tanbat Blog</h1>
        <p>
          @if($geoCountryName)
            Handpicked for readers in {{ $geoCountryName }} — plus what's hot and freshly published.
          @else
            Stories, ideas and deep-dives from the community — ranked by what's hot and new.
          @endif
        </p>
      </div>
      <div class="bh-count">
        <strong id="blogTotal">{{ number_format($totalArticles) }}</strong>
        <span>articles</span>
      </div>
    </header>

    {{-- Search bar --}}
    <form class="blog-search" id="blogSearchForm" role="search" onsubmit="return false;">
      <svg class="bs-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="blogSearchInput" type="search" placeholder="Search articles by title or topic…" autocomplete="off">
      <button type="button" id="blogSearchClear" class="bs-clear hidden" aria-label="Clear search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </form>

    {{-- Sort tabs --}}
    <div class="blog-tabs" id="blogTabs">
      <button type="button" class="blog-tab is-active" data-sort="for-you">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/></svg>
        For you
      </button>
      <button type="button" class="blog-tab" data-sort="hot">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5Z"/></svg>
        Hot
      </button>
      <button type="button" class="blog-tab" data-sort="new">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        New
      </button>
      <span class="blog-tab-spacer"></span>
      <span class="blog-result-note" id="blogResultNote"></span>
    </div>

    {{-- Cards grid --}}
    <section id="blogGrid" class="blog-grid" aria-live="polite"></section>

    {{-- Loading --}}
    <div id="blogLoading" class="blog-loading">
      <span class="ldr"></span>
      <span>Loading articles…</span>
    </div>

    {{-- Empty --}}
    <div id="blogEmpty" class="blog-empty hidden">
      <div class="be-orb">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      </div>
      <div class="be-title">No articles found</div>
      <div class="be-sub">Try a different topic or clear your search.</div>
    </div>

    {{-- Load more --}}
    <div class="blog-pager hidden" id="blogPager">
      <button type="button" id="blogLoadMore">
        Load more articles
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
    </div>
  </main>

  {{-- ─────────── RIGHT PANEL ─────────── --}}
  <aside class="blog-right">
    <div class="sticky top-[88px] space-y-4 right-scroll">

      {{-- Ad --}}
      <section class="panel ad-card">
        <span class="ad-tag">Sponsored</span>
        @include('partials.ad-banner')
      </section>

      {{-- Popular now (server-rendered) --}}
      @if($trending->isNotEmpty())
      <section class="panel trend-card">
        <div class="panel-head">
          <h4>{{ $trendingScope === 'country' && $geoCountry ? 'Popular in ' . $geoCountry : 'Popular now' }}</h4>
          <span class="head-sub">{{ $trendingScope === 'country' ? 'in your country' : 'most read' }}</span>
        </div>
        <ol class="trend-list">
          @foreach($trending as $i => $t)
            <li>
              <a href="{{ $t->permalink() }}" class="trend-item">
                <span class="trend-rank">{{ $i + 1 }}</span>
                <span class="trend-body">
                  <span class="trend-title">{{ \Illuminate\Support\Str::limit($t->title ?: 'Untitled', 64) }}</span>
                  <span class="trend-meta">
                    @if($trendingScope === 'country')
                      {{ number_format($t->country_views) }} local reads
                    @else
                      {{ number_format($t->views_count) }} views
                    @endif
                    @if($t->category)· {{ $t->category->name }}@endif
                  </span>
                </span>
              </a>
            </li>
          @endforeach
        </ol>
      </section>
      @endif

      {{-- Site stats --}}
      @include('partials.stat-counter')

      @guest
      <section class="panel bx-cta-panel">
        <div class="bx-cta-title">Join Tanbat free</div>
        <p class="bx-cta-sub">Create an account to write articles, follow authors and save your favourite reads.</p>
        <a href="{{ url('/') }}" class="bx-cta-btn">Create account</a>
        <a href="{{ url('/') }}" class="bx-cta-link">Sign in</a>
      </section>
      @endguest

    </div>
  </aside>
</div>

<style>
/* ─────────── Layout grid (mirrors home/books 3-col shell) ─────────── */
.blog-shell { display: grid; grid-template-columns: 1fr; gap: 20px; }
.blog-left, .blog-right { display: none; }
.blog-main { min-width: 0; grid-column: 1; grid-row: 1; }
@media (min-width: 1024px) {
  .blog-shell { grid-template-columns: 280px minmax(0, 1fr); }
  .blog-left  { display: block; grid-column: 1; grid-row: 1; }
  .blog-main  { grid-column: 2; grid-row: 1; }
}
@media (min-width: 1280px) {
  .blog-shell { grid-template-columns: 280px minmax(0, 1fr) 320px; }
  .blog-right { display: block; grid-column: 3; grid-row: 1; }
}
.right-scroll { max-height: calc(100vh - 88px - 20px); overflow-y: auto; scrollbar-width: none; }
.right-scroll::-webkit-scrollbar { display: none; }

/* ─────────── Left panel ─────────── */
.blog-write-btn {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; border-radius: 14px;
  background: linear-gradient(135deg,#6C63FF,#5A52D5);
  color: #fff; box-shadow: 0 10px 24px rgba(108,99,255,.30);
  transition: transform .15s, box-shadow .15s;
}
.blog-write-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(108,99,255,.42); color: #fff; }
.bw-orb {
  height: 38px; width: 38px; border-radius: 12px; flex-shrink: 0;
  display: grid; place-items: center;
  background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.32);
}
.bw-orb svg { width: 18px; height: 18px; }
.bw-text { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.bw-text strong { font-size: 14px; font-weight: 800; }
.bw-sub { font-size: 11px; opacity: .9; margin-top: 2px; }

.blog-panel {
  background: #fff; border: 1px solid #E5E7EB; border-radius: 14px;
  padding: 10px; box-shadow: 0 1px 2px rgba(20,20,50,.04);
}
.bp-head {
  font-size: 11px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase;
  color: #94A3B8; padding: 6px 8px 8px;
}
.blog-navlink {
  display: flex; align-items: center; gap: 10px; width: 100%;
  padding: 10px 10px; border-radius: 10px;
  font-size: 13.5px; font-weight: 700; color: #475569;
  text-align: left; transition: .15s; cursor: pointer;
}
.blog-navlink svg { width: 18px; height: 18px; flex-shrink: 0; color: #94A3B8; transition: color .15s; }
.blog-navlink span:not(.bl-flag) { flex: 1; }
.blog-navlink:hover { background: #F5F3FF; color: #5A52D5; }
.blog-navlink:hover svg { color: #6C63FF; }
.blog-navlink.is-active { background: linear-gradient(135deg,#EEF2FF,#F5F3FF); color: #5A52D5; }
.blog-navlink.is-active svg { color: #6C63FF; }
.bl-flag {
  font-size: 10px; font-weight: 800; letter-spacing: .4px;
  padding: 2px 7px; border-radius: 9999px;
  background: #EEF2FF; color: #4338CA;
}

.blog-explain { padding: 10px 14px 14px; }
.blog-explain p { font-size: 12px; color: #64748B; line-height: 1.6; margin-top: 2px; }

.left-footer { font-size: 11px; color: #94A3B8; padding: 8px 4px 0; line-height: 1.7; }
.left-footer a { color: #94A3B8; }
.left-footer a:hover { color: #6C63FF; }

/* ─────────── Hero ─────────── */
.blog-hero {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  padding: 22px 24px; border-radius: 16px; margin-bottom: 16px;
  background:
    radial-gradient(120% 90% at 100% 0%, rgba(255,255,255,.42) 0%, transparent 55%),
    linear-gradient(135deg,#5A52D5,#6C63FF 55%,#A78BFA);
  color: #fff; box-shadow: 0 14px 38px rgba(108,99,255,.28);
}
.blog-hero h1 { font-size: 23px; font-weight: 800; letter-spacing: -.01em; }
.blog-hero p  { font-size: 13px; opacity: .94; margin-top: 5px; max-width: 46ch; line-height: 1.5; }
.bh-count {
  flex-shrink: 0; text-align: center; line-height: 1.1;
  background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.32);
  border-radius: 14px; padding: 12px 18px; backdrop-filter: blur(6px);
}
.bh-count strong { display: block; font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; }
.bh-count span { font-size: 11px; opacity: .9; text-transform: uppercase; letter-spacing: .5px; }

/* ─────────── Search ─────────── */
.blog-search { position: relative; margin-bottom: 14px; }
.bs-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); height: 18px; width: 18px; color: #94A3B8; pointer-events: none; }
#blogSearchInput {
  width: 100%; padding: 12px 44px 12px 46px;
  border: 1.5px solid #E5E7EB; border-radius: 12px;
  background: #fff; font-size: 14px; color: #1E1B4B;
  transition: border-color .15s, box-shadow .15s;
}
#blogSearchInput:focus { outline: none; border-color: #6C63FF; box-shadow: 0 0 0 4px rgba(108,99,255,.12); }
.bs-clear {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  height: 24px; width: 24px; display: grid; place-items: center;
  border-radius: 9999px; background: #F1F5F9; color: #94A3B8; cursor: pointer;
}
.bs-clear:hover { background: #E2E8F0; color: #475569; }
.bs-clear svg { width: 13px; height: 13px; }

/* ─────────── Sort tabs ─────────── */
.blog-tabs { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.blog-tab {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 8px 16px; border-radius: 9999px;
  font-size: 13px; font-weight: 700; color: #475569;
  background: #fff; border: 1px solid #E5E7EB; cursor: pointer; transition: .2s;
}
.blog-tab svg { width: 15px; height: 15px; }
.blog-tab:hover { color: #6C63FF; border-color: #C5C9FF; }
.blog-tab.is-active {
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  border-color: transparent; box-shadow: 0 4px 14px rgba(108,99,255,.32);
}
.blog-tab-spacer { flex: 1; }
.blog-result-note { font-size: 12px; font-weight: 600; color: #94A3B8; }

/* ─────────── Grid + cards ─────────── */
.blog-grid { display: grid; gap: 18px; grid-template-columns: 1fr; }
@media (min-width: 640px)  { .blog-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (min-width: 1280px) { .blog-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (min-width: 1480px) { .blog-grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }

.blog-card {
  display: flex; flex-direction: column;
  background: #fff; border: 1px solid #E5E7EB; border-radius: 16px;
  overflow: hidden; transition: transform .15s, box-shadow .2s, border-color .15s;
}
.blog-card:hover { transform: translateY(-3px); box-shadow: 0 16px 34px rgba(20,20,50,.10); border-color: #C5C9FF; }
.bc-figure { position: relative; display: block; aspect-ratio: 16 / 9; background: linear-gradient(135deg,#EEF2FF,#FAF5FF); overflow: hidden; }
.bc-figure img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .3s; }
.blog-card:hover .bc-figure img { transform: scale(1.04); }
.bc-noimg { position: absolute; inset: 0; display: grid; place-items: center; color: #A5B4FC; }
.bc-noimg svg { width: 34px; height: 34px; }
.bc-badges { position: absolute; top: 10px; left: 10px; display: flex; gap: 6px; z-index: 2; }
.bc-badge {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 10px; font-weight: 800; letter-spacing: .3px; text-transform: uppercase;
  padding: 4px 9px; border-radius: 9999px; color: #fff;
  backdrop-filter: blur(4px); box-shadow: 0 2px 8px rgba(0,0,0,.18);
}
.bc-badge svg { width: 11px; height: 11px; }
.bc-badge.local { background: rgba(37,99,235,.92); }
.bc-badge.hot   { background: rgba(244,63,94,.92); }
.bc-badge.new   { background: rgba(16,185,129,.92); }
.bc-cat {
  position: absolute; bottom: 10px; left: 10px; z-index: 2;
  font-size: 10.5px; font-weight: 800; letter-spacing: .3px;
  padding: 4px 10px; border-radius: 9999px;
  background: rgba(255,255,255,.92); color: #5A52D5; backdrop-filter: blur(4px);
}
.bc-body { display: flex; flex-direction: column; flex: 1; padding: 14px 16px 16px; }
.bc-title {
  font-size: 16px; font-weight: 800; color: #1E1B4B; line-height: 1.3;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.bc-title:hover { color: #5A52D5; }
.bc-excerpt {
  margin-top: 7px; font-size: 13px; color: #64748B; line-height: 1.55; flex: 1;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.bc-foot { display: flex; align-items: center; gap: 9px; margin-top: 14px; padding-top: 13px; border-top: 1px solid #F1F5F9; }
.bc-av {
  height: 30px; width: 30px; border-radius: 9999px; flex-shrink: 0;
  background: linear-gradient(135deg,#6C63FF,#FF6584); color: #fff;
  font-size: 12px; font-weight: 800; display: grid; place-items: center; overflow: hidden;
}
.bc-av img { width: 100%; height: 100%; object-fit: cover; }
.bc-who { min-width: 0; flex: 1; line-height: 1.25; }
.bc-author { font-size: 12.5px; font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bc-author:hover { color: #5A52D5; }
.bc-sub { font-size: 11px; color: #94A3B8; }
.bc-views { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 700; color: #94A3B8; flex-shrink: 0; }
.bc-views svg { width: 13px; height: 13px; }

.bc-skeleton { border-radius: 16px; overflow: hidden; border: 1px solid #E5E7EB; background: #fff; }
.bc-skeleton .sk { display: block; background: linear-gradient(90deg,#F1F5F9 25%,#F8FAFC 50%,#F1F5F9 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite linear; }
.bc-skeleton .sk-img { aspect-ratio: 16/9; }
.bc-skeleton .sk-pad { padding: 14px 16px 16px; }
.bc-skeleton .sk-line { height: 13px; border-radius: 6px; margin-bottom: 9px; }
@keyframes shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }

/* ─────────── States ─────────── */
.blog-loading { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 30px; color: #64748B; font-size: 13px; }
.blog-loading .ldr { height: 16px; width: 16px; border-radius: 9999px; border: 2.5px solid #C5C9FF; border-top-color: #6C63FF; animation: bldr .7s linear infinite; }
@keyframes bldr { to { transform: rotate(360deg); } }
.blog-empty { text-align: center; padding: 56px 16px; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.be-orb { height: 60px; width: 60px; border-radius: 16px; background: linear-gradient(135deg,#EEF2FF,#FCE7F3); color: #6C63FF; display: grid; place-items: center; }
.be-orb svg { width: 26px; height: 26px; }
.be-title { font-size: 16px; font-weight: 800; color: #1E1B4B; }
.be-sub { font-size: 13px; color: #64748B; }

.blog-pager { text-align: center; margin: 24px 0 8px; }
.blog-pager button {
  display: inline-flex; align-items: center; gap: 7px;
  background: #fff; color: #5A52D5; border: 1.5px solid #C5C9FF;
  padding: 11px 24px; border-radius: 9999px; font-size: 13px; font-weight: 700;
  cursor: pointer; transition: background .15s, transform .12s;
}
.blog-pager button:hover { background: #EEF2FF; transform: translateY(-1px); }

/* ─────────── Right rail ─────────── */
.panel { background: #fff; border-radius: 12px; border: 1px solid #E5E7EB; box-shadow: 0 1px 2px rgba(20,20,50,.04); overflow: hidden; }
.panel-head { display: flex; align-items: baseline; justify-content: space-between; padding: 14px 16px 8px; }
.panel-head h4 { font-size: 14px; font-weight: 700; color: #1E1B4B; }
.panel-head .head-sub { font-size: 11px; color: #94A3B8; }
.ad-card { padding: 12px; position: relative; }
.ad-card .ad-tag { position: absolute; top: 14px; left: 16px; font-size: 10px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: .6px; background: rgba(255,255,255,.85); padding: 2px 6px; border-radius: 4px; z-index: 2; }

.trend-list { padding: 4px 8px 10px; counter-reset: t; }
.trend-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 8px; border-radius: 10px; transition: background .15s; }
.trend-item:hover { background: #F8FAFC; }
.trend-rank { flex-shrink: 0; height: 22px; width: 22px; border-radius: 8px; display: grid; place-items: center; font-size: 12px; font-weight: 800; background: #EEF2FF; color: #4338CA; }
.trend-body { min-width: 0; line-height: 1.3; }
.trend-title { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 12.5px; font-weight: 700; color: #1E1B4B; }
.trend-item:hover .trend-title { color: #5A52D5; }
.trend-meta { display: block; margin-top: 3px; font-size: 11px; color: #94A3B8; }

.bx-cta-panel { padding: 18px; }
.bx-cta-title { font-size: 15px; font-weight: 800; color: #1E1B4B; }
.bx-cta-sub { font-size: 12.5px; color: #64748B; line-height: 1.5; margin-top: 4px; }
.bx-cta-btn { display: block; text-align: center; margin-top: 12px; background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff; padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; box-shadow: 0 6px 16px rgba(108,99,255,.26); transition: transform .12s; }
.bx-cta-btn:hover { transform: translateY(-1px); color: #fff; }
.bx-cta-link { display: block; text-align: center; margin-top: 8px; font-size: 12.5px; font-weight: 700; color: #5A52D5; }
.bx-cta-link:hover { text-decoration: underline; }

.hidden { display: none !important; }

@media (max-width: 600px) {
  .blog-hero { flex-direction: column; align-items: flex-start; }
  .bh-count { align-self: stretch; }
}
</style>

@push('head')
<script>
  window.__BLOG__ = {
    urls: {
      feed:        {!! json_encode(url('/api/blog/feed')) !!},
      profileBase: {!! json_encode(rtrim(url('/'), '/')) !!},
    },
    geoCountry: {!! json_encode($geoCountry) !!},
  };
</script>
@endpush

@push('scripts')
@vite(['resources/js/blog.js'])
@endpush
@endsection
