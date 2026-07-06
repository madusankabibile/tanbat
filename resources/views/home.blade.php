@extends('layouts.app')
@section('title', 'Home — Tanbat')

{{-- Skeleton loader styles pushed to <head> so the shimmer cards are painted
     fully styled the instant they appear — independent of the body-level
     <style> blocks (which parse later) or the compiled app.css bundle. --}}
@push('head')
<style>
.sk-card { background:#fff; border-radius:8px; border:1px solid #E5E7EB; box-shadow:0 1px 2px rgba(20,20,50,.04); overflow:hidden; padding-bottom:8px; }
.sk { display:block; background:linear-gradient(90deg,#EEF1F6 25%,#F7F9FC 50%,#EEF1F6 75%); background-size:200% 100%; animation:skShimmer 1.3s infinite linear; border-radius:8px; }
@keyframes skShimmer { from { background-position:200% 0; } to { background-position:-200% 0; } }
.sk-head { display:flex; align-items:center; gap:10px; padding:12px 14px 8px; }
.sk-avatar { height:40px; width:40px; border-radius:9999px; flex-shrink:0; }
.sk-head-lines { display:flex; flex-direction:column; gap:6px; flex:1; }
.sk-head-lines .sk-line { height:11px; }
.sk-text { padding:4px 14px 12px; display:flex; flex-direction:column; gap:8px; }
.sk-text .sk-line { height:12px; }
.sk-line { border-radius:6px; }
.sk-media { height:280px; width:100%; }
.sk-actions { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; padding:10px 14px 4px; }
.sk-pill { height:26px; border-radius:8px; }
@media (prefers-reduced-motion: reduce) { .sk { animation:none; } }
/* #feedLoading carries .feed-stack (display:flex) for layout; this id-scoped
   rule outranks it so JS toggling .hidden truly removes the skeleton. */
#feedLoading { display:flex; flex-direction:column; gap:16px; max-width:640px; margin:0 auto; }
#feedLoading.hidden { display:none; }
</style>
@endpush

@section('content')
@include('partials.navbar')

<div class="home-shell mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  @include('partials.side-rails')

  {{-- ─────────── CENTER (FEED) ─────────── --}}
  <main class="home-center">

    {{-- Filter chips --}}
    <div class="mb-4 flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none max-w-[640px] mx-auto">
      <button data-filter="all"     class="filter-chip active">All</button>
      <button data-filter="status"  class="filter-chip">Status</button>
      <button data-filter="image"   class="filter-chip">Images</button>
      <button data-filter="video"   class="filter-chip">Videos</button>
      <button data-filter="article" class="filter-chip">Articles</button>
      <button data-filter="book"    class="filter-chip">Books</button>
    </div>

    {{-- Single-column FB feed --}}
    <div id="feed" class="feed-stack"></div>

    {{-- Generic "nothing here" — only shown when there are truly no posts at all (new account, no users posting) --}}
    <div id="feedEmpty" class="hidden py-16 text-center">
      <div class="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-500">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <div id="feedEmptyTitle" class="text-sm font-semibold text-slate-700">Nothing here yet</div>
      <div id="feedEmptyDesc" class="text-xs text-slate-500">Be the first to share something — hit Create above.</div>
    </div>

    {{-- Initial loading state (first batch fetching) — shimmer skeleton cards
         that mimic the real post layout, swapped out the moment posts arrive.
         Styles live in the inline <style> below so they render instantly even
         before the compiled app.css bundle is applied. --}}
    <div id="feedLoading" class="feed-stack" aria-hidden="true">
      @for ($i = 0; $i < 3; $i++)
        <div class="sk-card">
          <div class="sk-head">
            <span class="sk sk-avatar"></span>
            <div class="sk-head-lines">
              <span class="sk sk-line" style="width:42%"></span>
              <span class="sk sk-line" style="width:26%"></span>
            </div>
          </div>
          <div class="sk-text">
            <span class="sk sk-line" style="width:96%"></span>
            <span class="sk sk-line" style="width:80%"></span>
          </div>
          <span class="sk sk-media"></span>
          <div class="sk-actions">
            <span class="sk sk-pill"></span>
            <span class="sk sk-pill"></span>
            <span class="sk sk-pill"></span>
          </div>
        </div>
      @endfor
    </div>

    {{-- Bottom spinner — appears while the *next* single post is being fetched --}}
    <div id="feedNextLoading" class="hidden flex items-center justify-center gap-2 py-6 text-xs font-medium text-slate-400">
      <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-brand-300 border-t-transparent"></span>
      <span>Loading next post…</span>
    </div>

    {{-- End-of-feed sentinel — shown when /api/feed returns exhausted=true --}}
    <div id="feedEnd" class="hidden py-10 text-center">
      <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-white shadow-soft">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="text-sm font-extrabold text-slate-700">Nothing more to load</div>
      <div class="text-xs text-slate-500">You've seen everything we have for now — refresh to start over.</div>
    </div>
  </main>

</div>

@include('partials.create-modals')
@include('partials.post-detail-modal')
@include('partials.user-sheet')
@include('partials.share-modal')

<style>
/* ─────────── Filter chips ─────────── */
.filter-chip {
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
.filter-chip:hover { color: #6C63FF; border-color: #C5C9FF; }
.filter-chip.active { background: linear-gradient(135deg,#6C63FF,#5A52D5); color:#fff; border-color: transparent; box-shadow: 0 4px 14px rgba(108,99,255,.32); }

/* ─────────── FB-style feed ─────────── */
.feed-stack { display: flex; flex-direction: column; gap: 16px; max-width: 640px; margin: 0 auto; }

.post-card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #E5E7EB;
  box-shadow: 0 1px 2px rgba(20,20,50,.04);
  overflow: hidden;
  transition: box-shadow .18s ease;
}
.post-card:hover { box-shadow: 0 4px 12px rgba(20,20,50,.06); }

/* Sponsored feed card — looks like a regular image post; media is a display ad. */
.ad-feed-card .ad-feed-av { display: grid; place-items: center; background: linear-gradient(135deg,#6C63FF,#A78BFA); color: #fff; }
.ad-feed-card .ad-feed-av svg { width: 20px; height: 20px; }
.ad-feed-card .adbot-slot { min-height: 250px; display: flex; justify-content: center; align-items: center; background: #F8FAFC; }

.post-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px 8px; }
.post-head .avatar {
  height: 40px; width: 40px; border-radius: 9999px; object-fit: cover; flex-shrink: 0;
  background: linear-gradient(135deg,#6C63FF,#FF6584);
  display: grid; place-items: center; color: #fff; font-weight: 700; font-size: 15px;
}
.post-head .who { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; flex: 1; }
.post-head .name { font-size: 14px; font-weight: 700; color: #1E1B4B; }
.post-head .meta {
  font-size: 12px; color: #6B7280;
  display: flex; gap: 6px; align-items: center; margin-top: 2px;
  flex-wrap: wrap;
}
.post-head .meta .dot { width: 3px; height: 3px; border-radius: 9999px; background: #CBD5E1; flex-shrink: 0; }
.post-head .views {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 2px 9px; border-radius: 9999px;
  background: linear-gradient(135deg, #FFE4EC 0%, #FFF1D6 100%);
  color: #E11D48;
  font-weight: 800; font-size: 11.5px; letter-spacing: .2px;
  box-shadow: 0 1px 0 rgba(225,29,72,.08), inset 0 0 0 1px rgba(225,29,72,.15);
  transition: transform .2s ease, box-shadow .2s ease;
}
.post-head .views svg { width: 13px; height: 13px; stroke: #E11D48; }
.post-head .views strong { font-weight: 800; color: #BE123C; }
.post-head .views .views-label { font-weight: 700; color: #DB2777; opacity: .85; }
.post-head .views.is-pulse { animation: viewsPulse .55s ease; }
@keyframes viewsPulse {
  0%   { transform: scale(1);    box-shadow: 0 0 0 0 rgba(225,29,72,.45); }
  50%  { transform: scale(1.12); box-shadow: 0 0 0 6px rgba(225,29,72,0);  }
  100% { transform: scale(1);    box-shadow: 0 0 0 0 rgba(225,29,72,0);    }
}
.post-badge {
  display: inline-flex; padding: 1px 7px; border-radius: 9999px;
  font-size: 10px; font-weight: 800; letter-spacing: .3px; text-transform: uppercase;
}
.post-badge.status   { background: #EEF2FF; color: #4338CA; }
.post-badge.image    { background: #ECFEFF; color: #0E7490; }
.post-badge.video    { background: #FEF2F2; color: #BE123C; }
.post-badge.article  { background: #FEFCE8; color: #A16207; }
.post-badge.book     { background: #F5F3FF; color: #6D28D9; }

/* ─────────── Book card ─────────── */
.book-card .book-body {
  display: grid; grid-template-columns: 110px 1fr; gap: 14px;
  padding: 4px 14px 14px;
}
.book-card .book-cover {
  width: 110px; aspect-ratio: 2 / 3;
  border-radius: 8px; overflow: hidden;
  background: linear-gradient(135deg,#EEF2FF,#FCE7F3);
  border: 1px solid #E5E7EB;
  display: grid; place-items: center;
}
.book-card .book-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.book-card .book-noimg { color: #94A3B8; font-size: 11px; text-align: center; padding: 8px; }
.book-card .book-info { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.book-card .book-title {
  display: block;
  font-size: 15px; font-weight: 800; color: #1E1B4B;
  line-height: 1.25; word-break: break-word;
  text-decoration: none;
  transition: color .15s;
}
.book-card .book-title:hover { color: #5A52D5; }
.book-card .book-cover { display: grid; place-items: center; cursor: pointer; }
.book-card .book-cover:hover { box-shadow: 0 6px 14px rgba(108,99,255,.18); }
.book-card .book-author { font-size: 13px; color: #475569; }
.book-card .book-pub { font-size: 12px; color: #6B7280; }
.book-card .book-desc {
  font-size: 13px; color: #475569; line-height: 1.55;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
  margin-top: 4px;
}
.book-card .book-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 2px; }
.book-card .book-tag {
  font-size: 10px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase;
  padding: 2.5px 7px; border-radius: 6px;
}
.book-card .book-tag.ext  { background: #DBEAFE; color: #1D4ED8; }
.book-card .book-tag.size { background: #DCFCE7; color: #047857; }
.book-card .book-tag.year { background: #FCE7F3; color: #9D174D; }
.book-card .book-tag.lang { background: #FEF3C7; color: #92400E; }
.book-card .book-dl {
  margin-top: 8px;
  display: inline-flex; align-items: center; gap: 6px;
  align-self: flex-start;
  background: linear-gradient(135deg,#6C63FF,#5A52D5);
  color: #fff; padding: 7px 14px; border-radius: 9999px;
  border: 0;
  font-size: 12.5px; font-weight: 700;
  box-shadow: 0 4px 12px rgba(108,99,255,.28);
  transition: transform .12s, box-shadow .12s, background .15s, opacity .15s;
  cursor: pointer;
}
.book-card .book-dl:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(108,99,255,.36); }
.book-card .book-dl.is-counting {
  background: #E2E8F0; color: #64748B;
  box-shadow: none; cursor: not-allowed;
}
.book-card .book-dl.is-counting:hover { transform: none; box-shadow: none; }
.book-card .book-dl-counter {
  display: inline-grid; place-items: center;
  min-width: 26px; padding: 2px 8px;
  border-radius: 9999px;
  background: rgba(255,255,255,.18);
  color: inherit;
  font-size: 11px; font-weight: 800; font-variant-numeric: tabular-nums;
}
.book-card .book-dl.is-counting .book-dl-counter {
  background: #fff; color: #475569;
}
@media (max-width: 480px) {
  .book-card .book-body { grid-template-columns: 90px 1fr; }
  .book-card .book-cover { width: 90px; }
}
.post-menu { margin-left: auto; height: 32px; width: 32px; border-radius: 9999px; display: grid; place-items: center; color: #64748B; }
.post-menu:hover { background: #F1F5F9; color: #1E1B4B; }

.post-body { padding: 4px 14px 12px; font-size: 14px; color: #1E293B; line-height: 1.55; word-break: break-word; }

/* Media */
.post-media { background: #000; position: relative; }
.post-media img, .post-media video { display: block; width: 100%; height: auto; }
.post-media.is-adult img, .post-media.is-adult video { filter: blur(28px); transform: scale(1.06); }
.adult-pill {
  position: absolute; top: 12px; right: 12px;
  background: #E11D48; color: #fff;
  padding: 3px 9px; border-radius: 6px;
  font-size: 11px; font-weight: 800; letter-spacing: .4px;
  z-index: 3;
}

/* Status — colored canvas */
.status-canvas {
  padding: 36px 24px;
  min-height: 280px;
  display: flex; align-items: center; justify-content: center;
  text-align: center;
  font-size: 22px; font-weight: 700; line-height: 1.4;
  letter-spacing: -.01em;
  word-break: break-word;
}

/* Image — gallery */
.image-card .gallery-wrap { position: relative; overflow: hidden; background: #000; }
.image-card .gallery-track {
  display: flex; overflow-x: auto;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}
.image-card .gallery-track::-webkit-scrollbar { display: none; }
.image-card .gallery-slide {
  flex: 0 0 100%; width: 100%;
  scroll-snap-align: center;
  display: block;
}
.image-card .gallery-track.is-multi .gallery-slide {
  aspect-ratio: 4 / 5; object-fit: cover;
}
.image-card .gallery-btn {
  position: absolute; top: 50%; transform: translateY(-50%);
  height: 36px; width: 36px; border-radius: 9999px;
  background: rgba(255,255,255,.95); color: #1E1B4B;
  display: grid; place-items: center; cursor: pointer;
  box-shadow: 0 2px 12px rgba(0,0,0,.3);
  opacity: 0; transition: opacity .2s ease, transform .12s ease;
  z-index: 2;
}
.image-card .gallery-wrap:hover .gallery-btn { opacity: 1; }
.image-card .gallery-btn:hover { transform: translateY(-50%) scale(1.06); }
.image-card .gallery-prev { left: 10px; }
.image-card .gallery-next { right: 10px; }
.image-card .gallery-btn svg { width: 18px; height: 18px; }
.image-card .gallery-counter {
  position: absolute; top: 12px; right: 12px;
  background: rgba(15,15,30,.7); color: #fff;
  padding: 3px 9px; border-radius: 9999px;
  font-size: 11px; font-weight: 700;
  backdrop-filter: blur(6px); z-index: 2;
}
.image-card .gallery-dots {
  display: flex; justify-content: center; gap: 5px;
  padding: 8px 0 4px;
}
.image-card .gallery-dots .dot {
  height: 6px; width: 6px; border-radius: 9999px;
  background: #CBD5E1; transition: .2s;
}
.image-card .gallery-dots .dot.active {
  background: #6C63FF; width: 18px; border-radius: 3px;
}

/* Video — YouTube-ish thumbnail */
.video-card .video-thumb-wrap {
  position: relative; overflow: hidden; background: #000;
  cursor: pointer; aspect-ratio: 16 / 9;
}
.video-card .video-thumb-wrap img {
  width: 100%; height: 100%; object-fit: cover; display: block;
  transition: transform .25s ease;
}
.video-card .video-thumb-wrap:hover img { transform: scale(1.03); }
.video-card .video-thumb-wrap.is-adult img { filter: blur(28px); transform: scale(1.08); }
.video-card .play-btn {
  position: absolute; inset: 0;
  display: grid; place-items: center; pointer-events: none;
}
.video-card .play-disc {
  width: 64px; height: 44px; border-radius: 12px;
  background: rgba(220, 38, 38, .92);
  display: grid; place-items: center;
  box-shadow: 0 8px 24px rgba(0,0,0,.45);
  transition: background .15s ease, transform .15s ease;
}
.video-card .video-thumb-wrap:hover .play-disc { background: #DC2626; transform: scale(1.06); }
.video-card .play-disc svg { width: 20px; height: 20px; fill: #fff; }

/* Article — link preview style */
.article-card .article-figure { position: relative; display: block; overflow: hidden; background: #F1F5F9; }
.article-card .article-figure img {
  width: 100%; height: auto; display: block;
  transition: transform .3s ease;
}
.article-card:hover .article-figure img { transform: scale(1.03); }
.article-card .article-cat {
  position: absolute; top: 12px; left: 12px;
  background: rgba(255,255,255,.96); color: #4338CA;
  padding: 4px 10px; border-radius: 6px;
  font-size: 10px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase;
  box-shadow: 0 2px 8px rgba(0,0,0,.12);
}
.article-card .article-meta { padding: 14px 16px 4px; background: #F8FAFC; }
.article-card .article-title {
  font-size: 18px; font-weight: 800; color: #1E1B4B; line-height: 1.3;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.article-card .article-desc {
  margin-top: 4px; font-size: 13px; color: #475569; line-height: 1.55;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.article-card .article-read {
  display: inline-flex; align-items: center; gap: 6px;
  margin: 8px 16px 14px;
  font-size: 13px; font-weight: 700; color: #6C63FF;
  background: #F8FAFC;
}
.article-card .article-read:hover { color: #5A52D5; }

/* Footer counts + actions (shared) */
.post-counts {
  display: flex; align-items: center; gap: 6px;
  padding: 10px 14px 8px;
  font-size: 12px; color: #6B7280;
}
.post-counts[hidden] { display: none; }
.post-counts .reaction-stack { display: inline-flex; align-items: center; }
.post-counts .re-chip {
  display: inline-grid; place-items: center;
  width: 18px; height: 18px; border-radius: 9999px;
  background: #fff; box-shadow: 0 0 0 1.5px #fff;
  font-size: 11px; line-height: 1;
}
.post-counts .re-chip + .re-chip { margin-left: -5px; }
.post-counts [data-likes-count]:not(:empty) { margin-left: 4px; }
.post-actions {
  display: grid; grid-template-columns: 1fr 1fr 1fr;
  gap: 4px;
  padding: 4px 8px 8px;
  border-top: 1px solid #F1F5F9;
}
.post-actions button {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 9px 6px;
  border-radius: 8px;
  background: transparent;
  color: #475569; font-size: 13px; font-weight: 600;
  transition: background .15s ease, color .15s ease, transform .1s ease;
  min-width: 0;
}
.post-actions button:hover { background: #F1F5F9; }
.post-actions button:active { transform: scale(.97); }
.post-actions button svg { width: 18px; height: 18px; flex-shrink: 0; }
.post-actions .btn-share:hover { color: #6C63FF; }
.post-actions .btn-comment:hover { color: #0EA5E9; }

/* ── Facebook-style reactions ── */
.reaction-wrap { position: relative; display: flex; }
.reaction-wrap > .btn-like { width: 100%; }
.btn-like .re-emoji { font-size: 18px; line-height: 1; }
.re-emoji:empty { display: none; }
.btn-like.is-reacted .re-default { display: none; }
.btn-like.is-reacted { color: #2563EB; }

.reaction-pop {
  position: absolute; bottom: calc(100% + 8px); left: 0;
  display: flex; gap: 2px; padding: 5px 7px;
  background: #fff; border-radius: 9999px;
  box-shadow: 0 12px 32px rgba(15,23,42,.20); border: 1px solid #EEF2F7;
  opacity: 0; pointer-events: none;
  transform: translateY(8px) scale(.9); transform-origin: bottom left;
  transition: opacity .15s ease, transform .15s ease;
  z-index: 50; white-space: nowrap;
}
/* Transparent bridge over the gap so hover doesn't break travelling
   from the Like button up to the popover. */
.reaction-pop::after {
  content: ''; position: absolute; left: 0; right: 0; top: 100%; height: 12px;
}
.reaction-wrap:hover .reaction-pop,
.reaction-wrap:focus-within .reaction-pop,
.reaction-wrap.is-open .reaction-pop {
  opacity: 1; pointer-events: auto; transform: translateY(0) scale(1);
}
/* After a choice, keep it shut even while the cursor still hovers it. */
.reaction-wrap.is-dismissed .reaction-pop {
  opacity: 0 !important; pointer-events: none !important; transform: translateY(8px) scale(.9) !important;
}
.reaction-pop .reaction-pop-btn {
  display: grid; place-items: center;
  width: 40px; height: 40px; padding: 0;
  border-radius: 9999px; background: transparent;
  font-size: 26px; line-height: 1; cursor: pointer;
  transition: transform .12s ease, background .12s ease;
}
.reaction-pop .reaction-pop-btn:hover { transform: translateY(-7px) scale(1.3); background: #F8FAFC; }
.reaction-pop .reaction-pop-btn:active { transform: translateY(-3px) scale(1.15); }

@media (max-width: 480px) {
  .post-actions button > [data-like-label],
  .post-actions button > .lbl { display: none; }
  .status-canvas { font-size: 18px; min-height: 220px; padding: 28px 18px; }
  .article-card .article-title { font-size: 16px; }
}
</style>

@push('scripts')
@vite(['resources/js/home.js'])
@endpush
@endsection
