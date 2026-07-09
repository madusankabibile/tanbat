{{-- Shared left + right side rails used on Home, Search, People Discovery pages. --}}

{{-- ─────────── LEFT PANEL ─────────── --}}
<aside class="home-left">
  <div class="sticky top-[88px] space-y-4">

    @auth
    @if(request()->is('home'))
    {{-- Saved posts CTA — only on the home feed page where home.js wires the modal. --}}
    <button type="button" class="panel saved-cta" data-action="open-saved-posts">
      <span class="saved-orb">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
      </span>
      <span class="saved-text">
        <span class="saved-title-row">
          <strong>Saved posts</strong>
          <span class="saved-count-pill hidden" id="savedCount">0</span>
        </span>
        <span class="saved-sub-text">Your bookmarks, all in one place</span>
      </span>
      <svg class="saved-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
    @endif
    @endauth

    {{-- Guests get a join card in place of the member-only saved/people panels. --}}
    @guest
    <div class="panel join-card">
      <div class="join-orb">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      </div>
      <div class="join-title">Join Tanbat</div>
      <div class="join-sub">Sign in to like, comment, save posts and follow people.</div>
      <button type="button" onclick="window.openAuthModal && window.openAuthModal('register')" class="join-btn">Create free account</button>
      <button type="button" onclick="window.openAuthModal && window.openAuthModal('login')" class="join-link">I already have an account</button>
    </div>
    @endguest

    {{-- Public shortcuts — shown to everyone (guest + member). --}}
    {{-- Tanbat Assistant CTA — wizard for finding books (and, soon, other resources) --}}
    <a href="{{ url('/assistant') }}" class="panel assist-cta {{ request()->is('assistant') ? 'is-active' : '' }}">
      <span class="assist-orb">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 1 4 4v1a4 4 0 0 1-8 0V6a4 4 0 0 1 4-4z"/><path d="M5 22v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><circle cx="12" cy="6.5" r="1.2" fill="currentColor"/></svg>
      </span>
      <span class="assist-text">
        <strong>Tanbat Assistant</strong>
        <span class="assist-sub-text">Find books &mdash; powered by you</span>
      </span>
      <svg class="saved-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

    {{-- Books library --}}
    <a href="{{ url('/books') }}" class="panel books-cta {{ request()->is('books') ? 'is-active' : '' }}">
      <span class="books-orb">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      </span>
      <span class="books-text">
        <strong>Library</strong>
        <span class="books-sub-text">Browse every book</span>
      </span>
      <svg class="saved-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

    {{-- Blog CTA --}}
    <a href="{{ url('/blog') }}" class="panel books-cta {{ request()->is('blog') ? 'is-active' : '' }}">
      <span class="books-orb" style="background:linear-gradient(135deg,#6C63FF,#A78BFA);box-shadow:0 6px 16px rgba(108,99,255,.28);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/><path d="M8 7h8M8 11h6"/></svg>
      </span>
      <span class="books-text">
        <strong>Blog</strong>
        <span class="books-sub-text">Articles picked for your country</span>
      </span>
      <svg class="saved-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

    {{-- Discover People CTA — the People page requires an account, so members only. --}}
    @auth
    <a href="{{ url('/discover/people') }}" class="panel people-cta {{ request()->is('discover/people') ? 'is-active' : '' }}">
      <span class="people-orb">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </span>
      <span class="people-text">
        <strong>People</strong>
        <span class="people-sub-text">Discover, follow, and connect</span>
      </span>
      <svg class="saved-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    @endauth

    <div class="left-footer">
      <span>© Tanbat</span> · <a href="#">About</a> · <a href="#">Privacy</a> · <a href="#">Terms</a>
    </div>
  </div>
</aside>

{{-- ─────────── RIGHT PANEL ─────────── --}}
<aside class="home-right">
  <div class="sticky top-[88px] space-y-4 right-scroll">

    {{-- Advertisement square --}}
    <section class="panel ad-card">
      <span class="ad-tag">Sponsored</span>
      @include('partials.ad-banner')
    </section>

    {{-- Recent visitors (custom widget — replaces SuperCounters) --}}
    @include('partials.stat-counter')

    {{-- Active / online users --}}
    <section class="panel active-card">
      <div class="panel-head">
        <h4>Most active</h4>
        <span class="head-sub">last 24h</span>
      </div>
      <ul class="active-list" id="activeUsers">
        <li class="active-skeleton"></li>
        <li class="active-skeleton"></li>
        <li class="active-skeleton"></li>
      </ul>
    </section>

  </div>
</aside>

<style>
/* ─────────── Layout grid ─────────── */
/* The partial emits left + right asides ahead of <main>, so we pin each
   region to a specific column with grid-column to keep them visually in
   left / center / right order regardless of DOM order. */
.home-shell {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}
.home-left, .home-right { display: none; }
.home-center { min-width: 0; grid-column: 1; grid-row: 1; }
@media (min-width: 1024px) {
  .home-shell { grid-template-columns: 280px minmax(0, 1fr); }
  .home-left   { display: block; grid-column: 1; grid-row: 1; }
  .home-center { grid-column: 2; grid-row: 1; }
}
@media (min-width: 1280px) {
  .home-shell { grid-template-columns: 280px minmax(0, 1fr) 320px; }
  .home-right  { display: block; grid-column: 3; grid-row: 1; }
}

/* Right rail scrolls internally once it's taller than the viewport, instead
   of growing past the fold — the sticky offset (top-[88px]) is subtracted
   from the max height so it never gets clipped by the bottom of the screen. */
.right-scroll {
  max-height: calc(100vh - 88px - 20px);
  overflow-y: auto;
  scrollbar-width: none;
}
.right-scroll::-webkit-scrollbar { display: none; }

/* ─────────── Shared panel ─────────── */
.panel {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #E5E7EB;
  box-shadow: 0 1px 2px rgba(20,20,50,.04);
  overflow: hidden;
}
.panel-head {
  display: flex; align-items: baseline; justify-content: space-between;
  padding: 14px 16px 8px;
}
.panel-head h4 { font-size: 14px; font-weight: 700; color: #1E1B4B; }
.panel-head .head-sub { font-size: 11px; color: #94A3B8; }

/* ─────────── Saved CTA + People CTA ─────────── */
.saved-cta, .people-cta {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px;
  width: 100%; text-align: left;
  background: #fff;
  cursor: pointer;
  transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.saved-cta:hover, .people-cta:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(108,99,255,.16); border-color: #C5C9FF; }
.saved-orb, .people-orb {
  height: 36px; width: 36px; border-radius: 12px;
  display: grid; place-items: center; flex-shrink: 0;
  color: #fff;
}
.saved-orb { background: linear-gradient(135deg,#F59E0B,#F97316); box-shadow: 0 6px 16px rgba(249,115,22,.28); }
.people-orb { background: linear-gradient(135deg,#6C63FF,#A78BFA); box-shadow: 0 6px 16px rgba(108,99,255,.28); }
.saved-orb svg, .people-orb svg { width: 18px; height: 18px; }
.saved-text, .people-text { display: flex; flex-direction: column; line-height: 1.2; flex: 1; min-width: 0; }
.saved-title-row { display: flex; align-items: center; gap: 8px; }
.saved-title-row strong, .people-text strong { font-size: 14px; font-weight: 700; color: #1E1B4B; }
.saved-sub-text, .people-sub-text { font-size: 11px; color: #6B7280; margin-top: 3px; }
.saved-count-pill {
  display: inline-grid; place-items: center; min-width: 22px; height: 18px; padding: 0 6px;
  border-radius: 9999px; background: #EEF2FF; color: #4338CA; font-size: 10px; font-weight: 800;
}
.saved-arrow { margin-left: auto; color: #94A3B8; flex-shrink: 0; }
.people-cta.is-active { background: linear-gradient(135deg,#FFFFFF 0%,#F5F3FF 100%); border-color: #C5C9FF; }
.people-cta.is-active .people-text strong { color: #5A52D5; }

/* ── Tanbat Assistant CTA ── */
.assist-cta, .books-cta {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; width: 100%; text-align: left;
  background: #fff; cursor: pointer;
  transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.assist-cta:hover, .books-cta:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(108,99,255,.16); border-color: #C5C9FF; }
.assist-orb, .books-orb {
  height: 36px; width: 36px; border-radius: 12px;
  display: grid; place-items: center; flex-shrink: 0; color: #fff;
}
.assist-orb { background: linear-gradient(135deg,#A78BFA,#6C63FF); box-shadow: 0 6px 16px rgba(108,99,255,.32); }
.books-orb  { background: linear-gradient(135deg,#5A52D5,#7C3AED); box-shadow: 0 6px 16px rgba(124,58,237,.28); }
.assist-orb svg, .books-orb svg { width: 18px; height: 18px; }
.assist-text, .books-text { display: flex; flex-direction: column; line-height: 1.2; flex: 1; min-width: 0; }
.assist-text strong, .books-text strong { font-size: 14px; font-weight: 700; color: #1E1B4B; }
.assist-sub-text, .books-sub-text { font-size: 11px; color: #6B7280; margin-top: 3px; }
.assist-cta.is-active, .books-cta.is-active { background: linear-gradient(135deg,#FFFFFF 0%,#F5F3FF 100%); border-color: #C5C9FF; }
.assist-cta.is-active .assist-text strong,
.books-cta.is-active  .books-text strong { color: #5A52D5; }

/* ── Guest "Join Tanbat" card ── */
.join-card {
  padding: 18px 16px 16px; text-align: center;
  background: linear-gradient(160deg,#FFFFFF 0%,#F5F3FF 100%);
  border-color: #E5E1FF;
}
.join-card .join-orb {
  height: 44px; width: 44px; margin: 0 auto 10px; border-radius: 14px;
  display: grid; place-items: center; color: #fff;
  background: linear-gradient(135deg,#6C63FF,#FF6584);
  box-shadow: 0 8px 20px rgba(108,99,255,.32);
}
.join-card .join-orb svg { width: 22px; height: 22px; }
.join-card .join-title { font-size: 15px; font-weight: 800; color: #1E1B4B; }
.join-card .join-sub { margin-top: 4px; font-size: 12px; color: #64748B; line-height: 1.5; }
.join-card .join-btn {
  margin-top: 12px; width: 100%; padding: 9px 12px; border-radius: 10px;
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  font-size: 13px; font-weight: 700; box-shadow: 0 6px 16px rgba(108,99,255,.3);
  transition: transform .12s, box-shadow .12s;
}
.join-card .join-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.4); }
.join-card .join-link { margin-top: 8px; width: 100%; font-size: 12px; font-weight: 600; color: #6C63FF; }
.join-card .join-link:hover { text-decoration: underline; }

.left-footer { font-size: 11px; color: #94A3B8; padding: 8px 4px 0; line-height: 1.7; }
.left-footer a { color: #94A3B8; }
.left-footer a:hover { color: #6C63FF; }

/* ─────────── Right column ─────────── */
.ad-card { padding: 12px; position: relative; }
.ad-card .ad-tag {
  position: absolute; top: 14px; left: 16px;
  font-size: 10px; font-weight: 700; color: #94A3B8;
  text-transform: uppercase; letter-spacing: .6px;
  background: rgba(255,255,255,.85); padding: 2px 6px; border-radius: 4px;
  z-index: 2;
}
.ad-square {
  aspect-ratio: 1 / 1;
  border-radius: 10px;
  overflow: hidden;
  background:
    radial-gradient(80% 80% at 30% 30%, rgba(255,255,255,.35), transparent 55%),
    linear-gradient(135deg, #6C63FF 0%, #FF6584 100%);
  display: grid; place-items: center;
  color: #fff;
  position: relative;
}
.ad-square .ad-content { padding: 18px; text-align: center; }
.ad-square .ad-logo {
  display: inline-grid; place-items: center;
  height: 44px; width: 44px; border-radius: 12px;
  background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.4);
  font-weight: 800; font-size: 18px;
  backdrop-filter: blur(4px);
}
.ad-square .ad-title { margin-top: 12px; font-size: 18px; font-weight: 800; letter-spacing: -.01em; }
.ad-square .ad-sub { margin-top: 4px; font-size: 12px; opacity: .9; line-height: 1.4; }
.ad-square .ad-btn {
  display: inline-block; margin-top: 14px;
  background: #fff; color: #6C63FF;
  padding: 8px 16px; border-radius: 9999px;
  font-size: 12px; font-weight: 700;
  box-shadow: 0 6px 18px rgba(0,0,0,.18);
}
.ad-square .ad-btn:hover { transform: translateY(-1px); }

.stats-card .stats-grid {
  padding: 4px 14px 14px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
}
.stat-tile {
  display: grid;
  grid-template-areas: "icon val" "icon lbl";
  grid-template-columns: 30px 1fr;
  column-gap: 9px;
  align-items: center;
  background: #F8FAFC;
  border-radius: 10px;
  padding: 8px 10px;
}
.stat-tile .stat-icon {
  grid-area: icon;
  height: 30px; width: 30px; border-radius: 8px;
  display: grid; place-items: center;
}
.stat-tile .stat-val { grid-area: val; font-size: 15px; font-weight: 800; color: #1E1B4B; font-variant-numeric: tabular-nums; }
.stat-tile .stat-lbl { grid-area: lbl; font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: .3px; }

.active-card .active-list { padding: 4px 6px 8px; }
.active-list li {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px;
  border-radius: 10px;
  cursor: pointer; transition: background .15s ease;
}
.active-list li:hover { background: #F8FAFC; }
.active-list .av {
  position: relative;
  height: 34px; width: 34px; border-radius: 9999px;
  background: linear-gradient(135deg,#6C63FF,#FF6584); color: #fff;
  font-weight: 700; font-size: 13px;
  display: grid; place-items: center;
  overflow: visible;
}
.active-list .av img { height: 100%; width: 100%; border-radius: inherit; object-fit: cover; display: block; }
.active-list .av .dot {
  position: absolute; bottom: 0; right: 0;
  height: 10px; width: 10px; border-radius: 9999px;
  background: #94A3B8; border: 2px solid #fff;
}
.active-list .av.is-online .dot { background: #10B981; }
.active-list .who { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.active-list .who .name { font-size: 13px; font-weight: 700; color: #1E1B4B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.active-list .who .meta { font-size: 11px; color: #6B7280; margin-top: 2px; }

.active-skeleton {
  height: 50px; border-radius: 10px;
  background: linear-gradient(90deg, #F1F5F9 25%, #F8FAFC 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite linear;
  margin: 4px 6px;
}
@keyframes shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
</style>

@push('scripts')
@vite(['resources/js/sidebars.js'])
@endpush
