@extends('layouts.app')
@section('title', 'Books — Tanbat')

@section('content')
@auth
  @include('partials.navbar')
@else
  @include('partials.guest-bar')
@endauth

<div class="books-shell {{ auth()->check() ? '' : 'books-shell--guest' }} mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  @auth
    @include('partials.side-rails')
  @endauth

  <main class="books-main">

    <header class="books-hero">
      <div>
        <h1>Tanbat Library</h1>
        <p>Every book the community has requested through Tanbat Assistant.</p>
      </div>
      <a href="{{ url('/assistant') }}" class="hero-cta">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Request a book
      </a>
    </header>

    @auth
      <form class="books-search" id="booksSearchForm">
        <svg class="bs-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input id="booksSearchInput" type="search" placeholder="Filter by title, author or publisher…">
      </form>

      <section id="booksGrid" class="books-grid"></section>

      <div id="booksEmpty" class="books-empty hidden">
        <div class="be-orb">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        </div>
        <div class="be-title">No books here yet</div>
        <div class="be-sub">Be the first &mdash; head to Tanbat Assistant and request one.</div>
        <a href="{{ url('/assistant') }}" class="be-cta">Open Tanbat Assistant</a>
      </div>

      <div id="booksLoading" class="books-loading">
        <span class="ldr"></span>
        <span>Loading library…</span>
      </div>

      <div id="booksPager" class="books-pager hidden">
        <button type="button" id="booksLoadMore">Load more</button>
      </div>
    @else
      {{-- Guests see the full library server-side: plain tiles linking to each
           book's detail page (no ad links). --}}
      @if($guestBooks && $guestBooks->isNotEmpty())
        <section class="books-grid">
          @foreach($guestBooks as $b)
            <a href="{{ url('/books/' . $b->slug) }}" class="book-tile">
              <span class="bt-cover">
                @if($b->cover_url)
                  <img src="{{ $b->cover_url }}" alt="" referrerpolicy="no-referrer" loading="lazy"
                       onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'noimg',textContent:'No cover'}))">
                @else
                  <span class="noimg">No cover</span>
                @endif
                @if($b->extension)<span class="bt-ext">{{ $b->extension }}</span>@endif
              </span>
              <span class="bt-body">
                <span class="bt-title">{{ $b->title }}</span>
                @if($b->author)<span class="bt-author">{{ $b->author }}</span>@endif
                <span class="bt-meta">
                  @if($b->year)<span>{{ $b->year }}</span>@endif
                  @if($b->year && $b->size)<span class="dot"></span>@endif
                  @if($b->size)<span>{{ $b->size }}</span>@endif
                </span>
              </span>
            </a>
          @endforeach
        </section>

        {{-- Mobile banner (right rail is hidden below lg). --}}
        <div class="books-ad-mobile lg:hidden">
          <div class="ad-slot">@include('partials.ad-banner')</div>
        </div>
      @else
        <div class="books-empty">
          <div class="be-orb">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          </div>
          <div class="be-title">No books here yet</div>
          <div class="be-sub">Be the first — request a book through Tanbat Assistant.</div>
          <a href="{{ url('/assistant') }}" class="be-cta">Request a book</a>
        </div>
      @endif
    @endauth
  </main>

  {{-- ─────────── GUEST RIGHT AD PANEL (desktop) ─────────── --}}
  @guest
    <aside class="books-aside">
      <div class="books-aside-sticky">
        <div class="ad-slot">@include('partials.ad-banner')</div>

        <div class="bx-cta">
          <div class="bx-cta-title">Join Tanbat free</div>
          <p class="bx-cta-sub">Downloading and requesting books is free and open. Create an account to save your library and join the discussion.</p>
          <a href="{{ url('/') }}" class="bx-cta-btn">Create account</a>
          <a href="{{ url('/') }}" class="bx-cta-link">Sign in</a>
        </div>

        <div class="ad-slot">@include('partials.ad-banner')</div>

        @include('partials.stat-counter')
      </div>
    </aside>
  @endguest
</div>

@auth
  @include('partials.user-sheet')
@endauth

<style>
.books-shell { display: grid; grid-template-columns: 1fr; gap: 20px; }
.books-main  { min-width: 0; grid-column: 1; grid-row: 1; }
@media (min-width: 1024px) {
  .books-shell { grid-template-columns: 280px minmax(0, 1fr); }
  .books-main  { grid-column: 2; grid-row: 1; }
}
@media (min-width: 1280px) {
  .books-shell { grid-template-columns: 280px minmax(0, 1fr) 320px; }
}
/* Guest variant — full library + right-hand ad panel on desktop. */
.books-shell--guest { grid-template-columns: 1fr; max-width: 1200px; }
.books-shell--guest .books-main { grid-column: 1 !important; grid-row: 1; }
.books-aside { display: none; min-width: 0; }
.books-aside-sticky { position: sticky; top: 88px; display: flex; flex-direction: column; gap: 18px; }
.books-aside .ad-slot { min-height: 250px; display: flex; justify-content: center; }
.books-ad-mobile { margin-top: 18px; }
.books-ad-mobile .ad-slot { display: flex; justify-content: center; }
@media (min-width: 1024px) {
  .books-shell--guest { grid-template-columns: minmax(0, 1fr) 300px; gap: 28px; align-items: start; }
  .books-shell--guest .books-main { grid-column: 1 !important; }
  .books-aside { display: block; grid-column: 2; grid-row: 1; }
}
@media (min-width: 1280px) {
  .books-shell--guest { grid-template-columns: minmax(0, 1fr) 320px; }
}

/* Right-panel sign-in promo */
.bx-cta {
  background: #fff; border: 1px solid #E5E7EB; border-radius: 14px;
  padding: 18px; box-shadow: 0 1px 2px rgba(20,20,50,.04);
}
.bx-cta-title { font-size: 15px; font-weight: 800; color: #1E1B4B; }
.bx-cta-sub { font-size: 12.5px; color: #64748B; line-height: 1.5; margin-top: 4px; }
.bx-cta-btn {
  display: block; text-align: center; margin-top: 12px;
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 700;
  box-shadow: 0 6px 16px rgba(108,99,255,.26); transition: transform .12s, box-shadow .12s;
}
.bx-cta-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.34); color: #fff; }
.bx-cta-link { display: block; text-align: center; margin-top: 8px; font-size: 12.5px; font-weight: 700; color: #5A52D5; }
.bx-cta-link:hover { text-decoration: underline; }

.books-hero {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  padding: 22px 24px;
  border-radius: 16px;
  background:
    radial-gradient(120% 80% at 100% 0%, rgba(255,255,255,.4) 0%, transparent 60%),
    linear-gradient(135deg,#5A52D5,#6C63FF 60%,#A78BFA);
  color: #fff;
  box-shadow: 0 14px 38px rgba(108,99,255,.28);
  margin-bottom: 18px;
}
.books-hero h1 { font-size: 22px; font-weight: 800; letter-spacing: -.01em; }
.books-hero p  { font-size: 13px; opacity: .92; margin-top: 4px; }
.hero-cta {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,.18); color: #fff;
  padding: 9px 14px; border-radius: 9999px;
  border: 1px solid rgba(255,255,255,.4);
  font-size: 13px; font-weight: 700;
  backdrop-filter: blur(6px);
  transition: background .15s, transform .12s;
  flex-shrink: 0;
}
.hero-cta:hover { background: rgba(255,255,255,.28); transform: translateY(-1px); }
.hero-cta svg { width: 16px; height: 16px; }

.books-search { position: relative; margin-bottom: 18px; }
.bs-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); height: 18px; width: 18px; color: #94A3B8; pointer-events: none; }
#booksSearchInput {
  width: 100%; padding: 12px 16px 12px 46px;
  border: 1.5px solid #E5E7EB; border-radius: 12px;
  background: #fff; font-size: 14px; color: #1E1B4B;
  transition: border-color .15s, box-shadow .15s;
}
#booksSearchInput:focus { outline: none; border-color: #6C63FF; box-shadow: 0 0 0 4px rgba(108,99,255,.12); }

.books-grid {
  display: grid; gap: 16px;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
}

.book-tile {
  position: relative;
  display: flex; flex-direction: column; gap: 8px;
  background: #fff; border: 1px solid #E5E7EB;
  border-radius: 12px; overflow: hidden;
  transition: transform .15s, box-shadow .15s, border-color .15s;
}
.book-tile:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(108,99,255,.16); border-color: #C5C9FF; }
.book-tile .bt-cover, .book-tile .bt-body { display: block; }
.book-tile .bt-cover {
  aspect-ratio: 2 / 3;
  background: linear-gradient(135deg,#EEF2FF,#FAF5FF);
  display: grid; place-items: center;
  position: relative;
  cursor: pointer;
}
.book-tile .bt-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.book-tile .bt-cover .noimg { color: #94A3B8; font-size: 11px; text-align: center; padding: 8px; }
.book-tile .bt-ext {
  position: absolute; top: 8px; right: 8px;
  background: rgba(108,99,255,.92); color: #fff;
  padding: 3px 8px; border-radius: 9999px;
  font-size: 9.5px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase;
  backdrop-filter: blur(4px);
}
.book-tile .bt-body { padding: 4px 12px 14px; }
.book-tile .bt-title {
  font-size: 13px; font-weight: 800; color: #1E1B4B; line-height: 1.3;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
  min-height: 34px;
}
.book-tile .bt-author {
  margin-top: 4px;
  font-size: 11.5px; color: #64748B;
  display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
}
.book-tile .bt-meta {
  margin-top: 8px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
  font-size: 10.5px; color: #94A3B8;
}
.book-tile .bt-meta .dot { width: 3px; height: 3px; border-radius: 9999px; background: #CBD5E1; }

.books-empty {
  text-align: center; padding: 60px 16px;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
}
.be-orb {
  height: 60px; width: 60px; border-radius: 16px;
  background: linear-gradient(135deg,#EEF2FF,#FCE7F3);
  color: #6C63FF;
  display: grid; place-items: center;
}
.be-orb svg { width: 26px; height: 26px; }
.be-title { font-size: 16px; font-weight: 800; color: #1E1B4B; }
.be-sub   { font-size: 13px; color: #64748B; margin-bottom: 8px; }
.be-cta {
  display: inline-block;
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  padding: 10px 18px; border-radius: 10px;
  font-size: 13px; font-weight: 700;
  box-shadow: 0 6px 16px rgba(108,99,255,.28);
}
.be-cta:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.36); }

.books-loading {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  padding: 32px; color: #64748B; font-size: 13px;
}
.books-loading .ldr {
  height: 16px; width: 16px; border-radius: 9999px;
  border: 2.5px solid #C5C9FF; border-top-color: #6C63FF;
  animation: bldr .7s linear infinite;
}
@keyframes bldr { to { transform: rotate(360deg); } }

.books-pager { text-align: center; margin: 20px 0 8px; }
.books-pager button {
  background: #fff; color: #5A52D5;
  border: 1.5px solid #C5C9FF;
  padding: 10px 22px; border-radius: 9999px;
  font-size: 13px; font-weight: 700;
  cursor: pointer; transition: background .15s, transform .12s;
}
.books-pager button:hover { background: #EEF2FF; transform: translateY(-1px); }

/* Book detail modal */
.book-modal {
  position: fixed; inset: 0;
  background: rgba(15,23,42,.6);
  display: grid; place-items: center;
  z-index: 100; padding: 16px;
  animation: bmFade .18s ease both;
}
@keyframes bmFade { from { opacity: 0; } to { opacity: 1; } }
.book-modal .bm-card {
  background: #fff; border-radius: 16px;
  width: 100%; max-width: 720px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(15,23,42,.4);
  display: grid; grid-template-columns: 220px 1fr;
  animation: bmIn .22s ease both;
}
@keyframes bmIn { from { transform: translateY(12px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.bm-cover { background: #EEF2FF; display: grid; place-items: center; min-height: 320px; }
.bm-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.bm-cover .noimg { color: #94A3B8; font-size: 12px; padding: 12px; text-align: center; }
.bm-body { padding: 22px 24px; display: flex; flex-direction: column; gap: 10px; min-width: 0; position: relative; }
.bm-close {
  position: absolute; top: 12px; right: 12px;
  height: 32px; width: 32px; border-radius: 9999px;
  display: grid; place-items: center;
  background: #F1F5F9; color: #475569; cursor: pointer;
}
.bm-close:hover { background: #E2E8F0; color: #1E1B4B; }
.bm-title { font-size: 19px; font-weight: 800; color: #1E1B4B; line-height: 1.25; padding-right: 36px; }
.bm-author { font-size: 14px; color: #475569; }
.bm-pub { font-size: 12px; color: #6B7280; }
.bm-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.bm-tags .tg {
  font-size: 10.5px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase;
  padding: 3px 8px; border-radius: 6px;
  background: #EEF2FF; color: #4338CA;
}
.bm-meta-row { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #6B7280; margin-top: 8px; }
.bm-meta-row .av {
  height: 28px; width: 28px; border-radius: 9999px;
  background: linear-gradient(135deg,#6C63FF,#FF6584);
  color: #fff; font-weight: 800; font-size: 11px;
  display: grid; place-items: center; overflow: hidden;
}
.bm-meta-row .av img { width: 100%; height: 100%; object-fit: cover; }
.bm-actions { margin-top: auto; padding-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; }
.bm-actions a, .bm-actions button {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 16px; border-radius: 10px;
  font-size: 13px; font-weight: 700;
  cursor: pointer; transition: transform .12s, background .15s, box-shadow .12s;
  border: none;
}
.bm-actions .primary {
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  box-shadow: 0 6px 16px rgba(108,99,255,.28);
}
.bm-actions .primary:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.36); }
.bm-actions .ghost { background: #F1F5F9; color: #1E1B4B; }
.bm-actions .ghost:hover { background: #E2E8F0; }

@media (max-width: 600px) {
  .books-hero { flex-direction: column; align-items: flex-start; }
  .book-modal .bm-card { grid-template-columns: 1fr; max-width: 420px; }
  .bm-cover { min-height: 220px; aspect-ratio: 3 / 2; }
}
.hidden { display: none !important; }
</style>

@auth
  @push('scripts')
  @vite(['resources/js/books.js'])
  @endpush
@endauth
@endsection
