@extends('layouts.app')
@section('title', 'Tanbat Assistant')

@section('content')
@auth
  @include('partials.navbar')
@else
  @include('partials.guest-bar')
@endauth

<div class="assistant-shell mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  {{-- ─────────── LEFT AD RAIL ─────────── --}}
  <aside class="assist-rail assist-rail--left">
    <div class="assist-rail-sticky">
      <section class="assist-ad">
        <span class="assist-ad-tag">Sponsored</span>
        @include('partials.ad-banner')
      </section>
    </div>
  </aside>

  <main class="assistant-main">

    <header class="assist-hero">
      <div class="assist-hero-orb">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 7l9-4 9 4-9 4-9-4z"/>
          <path d="M3 12l9 4 9-4"/>
          <path d="M3 17l9 4 9-4"/>
        </svg>
      </div>
      <div>
        <h1>Tanbat Assistant</h1>
        <p>Find what you need. Books today, software, games and movies coming soon.</p>
      </div>
    </header>

    {{-- Progress steps --}}
    <ol class="assist-steps" id="assistSteps">
      <li data-step="service"><span class="dot">1</span><span class="lbl">Service</span></li>
      <li data-step="query"><span class="dot">2</span><span class="lbl">What to find</span></li>
      <li data-step="results"><span class="dot">3</span><span class="lbl">Review</span></li>
      <li data-step="preparing"><span class="dot">4</span><span class="lbl">Preparing</span></li>
      <li data-step="done"><span class="dot">5</span><span class="lbl">Done</span></li>
    </ol>

    {{-- ───── STEP 1: Service picker ───── --}}
    <section class="assist-panel" data-pane="service">
      <h2 class="assist-h2">What are you looking for?</h2>
      <p class="assist-sub">Pick the kind of resource. Only books are available right now.</p>

      <div class="service-grid" id="serviceGrid">
        <button type="button" class="service-card" data-service="book">
          <span class="svc-orb svc-book">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          </span>
          <span class="svc-title">Find a book</span>
          <span class="svc-sub">PDF, EPUB, MOBI &amp; more</span>
          <span class="svc-pill ok">Available</span>
        </button>

        <button type="button" class="service-card" data-service="software" data-locked="1">
          <span class="svc-orb svc-soft">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><line x1="2" y1="20" x2="22" y2="20"/></svg>
          </span>
          <span class="svc-title">Find a software</span>
          <span class="svc-sub">Apps &amp; utilities</span>
          <span class="svc-pill soon">Coming soon</span>
        </button>

        <button type="button" class="service-card" data-service="game" data-locked="1">
          <span class="svc-orb svc-game">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h4M8 10v4"/><circle cx="16" cy="12" r="1"/><circle cx="18" cy="14" r="1"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>
          </span>
          <span class="svc-title">Find a game</span>
          <span class="svc-sub">PC &amp; console titles</span>
          <span class="svc-pill soon">Coming soon</span>
        </button>

        <button type="button" class="service-card" data-service="movie" data-locked="1">
          <span class="svc-orb svc-movie">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="7" y1="4" x2="7" y2="20"/><line x1="17" y1="4" x2="17" y2="20"/><line x1="2" y1="8" x2="7" y2="8"/><line x1="2" y1="16" x2="7" y2="16"/></svg>
          </span>
          <span class="svc-title">Find a movie</span>
          <span class="svc-sub">Films &amp; documentaries</span>
          <span class="svc-pill soon">Coming soon</span>
        </button>
      </div>
    </section>

    {{-- ───── STEP 2: Query input ───── --}}
    <section class="assist-panel hidden" data-pane="query">
      <h2 class="assist-h2"><span data-svc-name>Book</span> title</h2>
      <p class="assist-sub">Type the title clearly &mdash; no author, no edition. Spelling matters.</p>

      <form id="assistQueryForm" class="query-form">
        <div class="query-input">
          <svg class="qi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input id="assistQuery" type="text" autocomplete="off" placeholder="e.g. The Pragmatic Programmer" maxlength="200" required>
        </div>
        <div class="query-actions">
          <button type="button" class="btn-ghost" data-back>← Back</button>
          <button type="submit" class="btn-primary">Search</button>
        </div>
      </form>
    </section>

    {{-- ───── STEP 3: Results (10 per page, pick one) ───── --}}
    <section class="assist-panel hidden" data-pane="results">
      <div class="results-head">
        <h2 class="assist-h2">Pick your book</h2>
        <p class="assist-sub" id="resultsCounter"></p>
      </div>

      <div class="results-list" id="resultsList">
        {{-- populated by JS --}}
      </div>

      <div class="results-nav" id="resultsNav">
        <button type="button" class="btn-ghost" data-back>← Back</button>
        <div class="results-pager" id="resultsPager">
          <button type="button" class="btn-secondary" id="btnPrevPage">← Prev</button>
          <span class="page-indicator" id="pageIndicator"></span>
          <button type="button" class="btn-secondary" id="btnNextPage">Next →</button>
        </div>
      </div>

      <div class="results-empty hidden" id="resultsEmpty">
        <div class="re-orb">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
        <div class="re-title">No more matches</div>
        <div class="re-sub">Try a different spelling or another keyword.</div>
        <button type="button" class="btn-primary" data-restart>Start over</button>
      </div>

      <div class="results-loading hidden" id="resultsLoading">
        <span class="loader"></span>
        <span>Searching…</span>
      </div>
    </section>

    {{-- ───── STEP 4: Preparing (2-min wait) ───── --}}
    <section class="assist-panel hidden" data-pane="preparing">
      <div class="prep-art">
        <span class="prep-orb">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        </span>
      </div>
      <h2 class="assist-h2 text-center">Preparing your book…</h2>
      <p class="assist-sub text-center">
        Your request is queued. This usually takes about 2 minutes.
        Feel free to close this tab &mdash; we'll send a notification when it's ready.
      </p>

      <div class="prep-clock">
        <span id="prepMinutes">02</span><span class="prep-colon">:</span><span id="prepSeconds">00</span>
      </div>

      <div class="prep-bar">
        <div class="prep-bar-fill" id="prepBarFill"></div>
      </div>

      <div class="prep-actions">
        <a class="btn-secondary" href="{{ url('/books') }}">Browse the library</a>
        <a class="btn-ghost" href="{{ url('/home') }}">Back to home</a>
      </div>
    </section>

    {{-- ───── STEP 5: Confirmation / Done ───── --}}
    <section class="assist-panel hidden" data-pane="done">
      <div class="done-art">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h2 class="assist-h2 text-center">All set!</h2>
      <p class="assist-sub text-center">
        We've added your book to the library. Come back in a little while &mdash; we'll send a notification when it's ready to grab.
      </p>

      <div class="done-card" id="doneCard">
        {{-- populated by JS with the created post details --}}
      </div>

      <div class="done-actions">
        <a class="btn-primary" id="doneViewBtn" href="#">Open the book</a>
        <a class="btn-secondary" href="{{ url('/books') }}">Browse all books</a>
        <button type="button" class="btn-ghost" data-restart>Find another</button>
      </div>
    </section>

  </main>

  {{-- ─────────── RIGHT AD RAIL ─────────── --}}
  <aside class="assist-rail assist-rail--right">
    <div class="assist-rail-sticky">
      <section class="assist-ad">
        <span class="assist-ad-tag">Sponsored</span>
        @include('partials.ad-banner')
      </section>
      @include('partials.stat-counter')
    </div>
  </aside>
</div>

@auth
  @include('partials.user-sheet')
@endauth
@include('partials.share-modal')

<style>
.assistant-shell { display: grid; grid-template-columns: 1fr; gap: 20px; }
.assistant-main  { min-width: 0; grid-column: 1; grid-row: 1; max-width: 760px; margin: 0 auto; width: 100%; }

/* Left + right sponsored ad rails, shown to every visitor. Hidden on narrow
   screens where there's no room; revealed as the viewport widens (left at lg,
   right at xl) so the wizard column never gets squeezed. */
.assist-rail { display: none; min-width: 0; }
.assist-rail-sticky {
  position: sticky; top: 88px;
  display: flex; flex-direction: column; gap: 16px;
  max-height: calc(100vh - 88px - 20px);
  overflow-y: auto; scrollbar-width: none;
}
.assist-rail-sticky::-webkit-scrollbar { display: none; }
.assist-ad {
  position: relative;
  background: #fff; border: 1px solid #E5E7EB; border-radius: 14px;
  padding: 12px; box-shadow: 0 1px 2px rgba(20,20,50,.04);
}
.assist-ad-tag {
  position: absolute; top: 10px; left: 14px; z-index: 2;
  font-size: 10px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase;
  color: #94A3B8; background: rgba(255,255,255,.85);
  padding: 2px 6px; border-radius: 4px;
}
.assist-ad .ad-banner { min-height: 250px; }

@media (min-width: 1024px) {
  .assistant-shell { grid-template-columns: 300px minmax(0, 1fr); }
  .assist-rail--left { display: block; grid-column: 1; grid-row: 1; }
  .assistant-main    { grid-column: 2; grid-row: 1; }
}
@media (min-width: 1280px) {
  .assistant-shell { grid-template-columns: 300px minmax(0, 1fr) 320px; }
  .assist-rail--right { display: block; grid-column: 3; grid-row: 1; }
}

.assist-hero {
  display: flex; align-items: center; gap: 16px;
  padding: 20px 22px;
  border-radius: 16px;
  background:
    radial-gradient(120% 80% at 0% 0%, rgba(255,255,255,.6) 0%, transparent 60%),
    linear-gradient(135deg,#6C63FF 0%,#A78BFA 60%,#FF6584 100%);
  color: #fff;
  box-shadow: 0 14px 38px rgba(108,99,255,.28);
  margin-bottom: 22px;
}
.assist-hero-orb {
  flex-shrink: 0;
  height: 56px; width: 56px; border-radius: 16px;
  background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.45);
  display: grid; place-items: center;
  backdrop-filter: blur(6px);
}
.assist-hero-orb svg { width: 26px; height: 26px; color: #fff; }
.assist-hero h1 { font-size: 22px; font-weight: 800; letter-spacing: -.01em; line-height: 1.15; }
.assist-hero p  { font-size: 13px; opacity: .92; margin-top: 4px; max-width: 56ch; }

/* Stepper */
.assist-steps {
  display: flex; align-items: center; justify-content: space-between;
  gap: 8px; padding: 0; margin: 0 0 18px;
  list-style: none;
}
.assist-steps li {
  flex: 1; display: flex; align-items: center; gap: 8px;
  font-size: 12px; font-weight: 600; color: #94A3B8;
  min-width: 0;
}
.assist-steps .dot {
  flex-shrink: 0;
  height: 28px; width: 28px; border-radius: 9999px;
  display: grid; place-items: center;
  background: #fff; border: 2px solid #E2E8F0; color: #94A3B8;
  font-weight: 800;
  transition: .2s;
}
.assist-steps .lbl {
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.assist-steps li.is-active { color: #5A52D5; }
.assist-steps li.is-active .dot { background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff; border-color: transparent; box-shadow: 0 4px 12px rgba(108,99,255,.32); }
.assist-steps li.is-done    { color: #10B981; }
.assist-steps li.is-done .dot { background: #10B981; color: #fff; border-color: transparent; }
@media (max-width: 540px) {
  .assist-steps .lbl { display: none; }
}

/* Panels */
.assist-panel {
  background: #fff; border-radius: 14px; border: 1px solid #E5E7EB;
  box-shadow: 0 1px 2px rgba(20,20,50,.04);
  padding: 22px 24px;
  animation: panelIn .25s ease both;
}
@keyframes panelIn {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.assist-h2 { font-size: 18px; font-weight: 800; color: #1E1B4B; }
.assist-sub { font-size: 13px; color: #64748B; margin-top: 4px; }
.text-center { text-align: center; }

/* Service grid */
.service-grid {
  margin-top: 18px;
  display: grid; gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}
.service-card {
  position: relative;
  text-align: left;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  border-radius: 14px;
  padding: 16px 16px 14px;
  cursor: pointer;
  display: flex; flex-direction: column; gap: 6px;
  transition: border-color .15s, transform .15s, box-shadow .15s;
}
.service-card:hover { border-color: #C5C9FF; transform: translateY(-2px); box-shadow: 0 8px 22px rgba(108,99,255,.14); }
.service-card[data-locked="1"] { opacity: .65; cursor: not-allowed; }
.service-card[data-locked="1"]:hover { transform: none; box-shadow: none; border-color: #E5E7EB; }

.svc-orb {
  height: 40px; width: 40px; border-radius: 12px;
  display: grid; place-items: center; color: #fff;
  flex-shrink: 0;
}
.svc-orb svg { width: 20px; height: 20px; }
.svc-book  { background: linear-gradient(135deg,#6C63FF,#5A52D5); box-shadow: 0 6px 16px rgba(108,99,255,.32); }
.svc-soft  { background: linear-gradient(135deg,#0EA5E9,#0284C7); }
.svc-game  { background: linear-gradient(135deg,#F59E0B,#EA580C); }
.svc-movie { background: linear-gradient(135deg,#EF4444,#BE123C); }
.svc-title { font-size: 15px; font-weight: 700; color: #1E1B4B; }
.svc-sub   { font-size: 12px; color: #6B7280; }
.svc-pill  {
  position: absolute; top: 12px; right: 12px;
  font-size: 9.5px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase;
  padding: 2px 8px; border-radius: 9999px;
}
.svc-pill.ok   { background: #DCFCE7; color: #047857; }
.svc-pill.soon { background: #F1F5F9; color: #64748B; }

/* Query form */
.query-form { margin-top: 18px; display: flex; flex-direction: column; gap: 16px; }
.query-input { position: relative; }
.qi-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); height: 18px; width: 18px; color: #94A3B8; pointer-events: none; }
#assistQuery {
  width: 100%; padding: 14px 16px 14px 46px;
  border: 1.5px solid #E5E7EB; border-radius: 12px;
  background: #F8FAFC; color: #1E1B4B; font-size: 15px;
  transition: border-color .15s, background .15s, box-shadow .15s;
}
#assistQuery:focus { outline: none; border-color: #6C63FF; background: #fff; box-shadow: 0 0 0 4px rgba(108,99,255,.12); }
.query-actions { display: flex; justify-content: space-between; gap: 10px; }

.btn-primary, .btn-secondary, .btn-ghost {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 10px 18px; border-radius: 10px;
  font-size: 14px; font-weight: 700;
  cursor: pointer; transition: transform .12s, box-shadow .12s, background .12s, color .12s;
  border: none;
}
.btn-primary {
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  box-shadow: 0 6px 16px rgba(108,99,255,.28);
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.36); }
.btn-secondary {
  background: #F1F5F9; color: #1E1B4B;
}
.btn-secondary:hover { background: #E2E8F0; }
.btn-ghost {
  background: transparent; color: #64748B;
}
.btn-ghost:hover { background: #F8FAFC; color: #1E1B4B; }
.btn-primary:disabled, .btn-secondary:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }

.btn-secondary.is-waiting {
  background: #E2E8F0; color: #64748B;
  font-variant-numeric: tabular-nums;
}
.btn-secondary.is-waiting::before {
  content: ''; display: inline-block;
  height: 10px; width: 10px; margin-right: 6px;
  border: 2px solid #94A3B8; border-top-color: transparent; border-radius: 9999px;
  animation: ldr .7s linear infinite;
}

/* Results — paginated list, 10 per page */
.results-head { margin-bottom: 16px; }
.results-list { display: flex; flex-direction: column; gap: 10px; }
.result-item {
  display: grid; grid-template-columns: 64px 1fr auto; gap: 14px; align-items: center;
  padding: 12px; cursor: pointer;
  border: 1px solid #E5E7EB; border-radius: 12px; background: #fff;
  transition: border-color .15s, box-shadow .15s, transform .12s;
}
.result-item:hover { border-color: #C5C9FF; box-shadow: 0 6px 18px rgba(108,99,255,.12); transform: translateY(-1px); }
.ri-cover {
  width: 64px; aspect-ratio: 2 / 3; flex-shrink: 0;
  border-radius: 8px; overflow: hidden; background: #EEF2FF;
  display: grid; place-items: center; border: 1px solid #E5E7EB;
}
.ri-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ri-cover .noimg { color: #94A3B8; font-size: 9px; padding: 4px; text-align: center; }
.ri-info { min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.ri-title {
  font-size: 14.5px; font-weight: 800; color: #1E1B4B; line-height: 1.25; word-break: break-word;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.ri-author { font-size: 12px; color: #475569; }
.ri-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 2px; }
.ri-action { flex-shrink: 0; }
.ri-select { white-space: nowrap; padding: 8px 16px; font-size: 13px; }

.result-tag {
  font-size: 10px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase;
  padding: 2px 7px; border-radius: 6px;
  background: #EEF2FF; color: #4338CA;
}
.result-tag.lang { background: #FEF3C7; color: #92400E; }
.result-tag.size { background: #DCFCE7; color: #047857; }
.result-tag.year { background: #FCE7F3; color: #9D174D; }
.result-tag.ext  { background: #DBEAFE; color: #1D4ED8; }

.results-nav { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
.results-pager { display: flex; align-items: center; gap: 8px; }
.results-pager.hidden { display: none !important; }
.page-indicator { font-size: 12.5px; font-weight: 700; color: #64748B; min-width: 92px; text-align: center; }

.results-empty {
  text-align: center; padding: 32px 16px;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
}
.re-orb {
  height: 56px; width: 56px; border-radius: 16px;
  background: #F1F5F9; color: #94A3B8;
  display: grid; place-items: center;
}
.re-orb svg { width: 24px; height: 24px; }
.re-title { font-size: 16px; font-weight: 800; color: #1E1B4B; }
.re-sub   { font-size: 13px; color: #64748B; margin-bottom: 12px; }

.results-loading {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  padding: 24px; color: #64748B; font-size: 13px;
}
.loader {
  height: 16px; width: 16px; border-radius: 9999px;
  border: 2.5px solid #C5C9FF; border-top-color: #6C63FF;
  animation: ldr .7s linear infinite;
}
@keyframes ldr { to { transform: rotate(360deg); } }

/* Preparing */
.prep-art { display: flex; justify-content: center; margin-top: 2px; }
.prep-orb {
  height: 64px; width: 64px; border-radius: 18px;
  background: linear-gradient(135deg,#6C63FF,#A78BFA);
  color: #fff;
  display: grid; place-items: center;
  box-shadow: 0 12px 28px rgba(108,99,255,.32);
  animation: prepPulse 1.6s ease-in-out infinite;
}
.prep-orb svg { width: 28px; height: 28px; }
@keyframes prepPulse {
  0%, 100% { transform: scale(1); box-shadow: 0 12px 28px rgba(108,99,255,.32); }
  50%      { transform: scale(1.06); box-shadow: 0 16px 36px rgba(108,99,255,.45); }
}
.prep-clock {
  margin: 22px auto 14px; text-align: center;
  font-size: 56px; font-weight: 800; color: #1E1B4B;
  letter-spacing: -.02em;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}
.prep-clock .prep-colon { color: #94A3B8; margin: 0 4px; }
.prep-bar {
  height: 8px;
  background: #F1F5F9;
  border-radius: 9999px; overflow: hidden;
  margin: 4px 8px 22px;
}
.prep-bar-fill {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg,#6C63FF,#A78BFA);
  border-radius: 9999px;
  transition: width .8s linear;
}
.prep-actions {
  display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;
}

/* Done */
.done-art {
  margin: 4px auto 14px; height: 64px; width: 64px;
  display: grid; place-items: center; border-radius: 9999px;
  background: linear-gradient(135deg,#10B981,#059669);
  color: #fff;
  box-shadow: 0 10px 26px rgba(16,185,129,.34);
}
.done-art svg { width: 30px; height: 30px; }
.done-card {
  margin-top: 18px;
  display: grid; grid-template-columns: 96px 1fr; gap: 14px;
  padding: 14px; border-radius: 12px;
  background: #F8FAFC; border: 1px solid #E5E7EB;
}
.done-card .dc-cover {
  width: 96px; aspect-ratio: 2 / 3;
  border-radius: 8px; overflow: hidden;
  background: #EEF2FF; display: grid; place-items: center;
}
.done-card .dc-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.done-card .dc-cover .noimg { color: #94A3B8; font-size: 10px; text-align: center; padding: 4px; }
.done-card .dc-meta { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.done-card .dc-title { font-size: 15px; font-weight: 800; color: #1E1B4B; line-height: 1.2; }
.done-card .dc-author { font-size: 12px; color: #475569; }
.done-card .dc-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }

.done-actions { display: flex; gap: 10px; justify-content: center; margin-top: 18px; flex-wrap: wrap; }

@media (max-width: 540px) {
  .result-item {
    grid-template-columns: 56px 1fr;
    grid-template-areas: "cover info" "action action";
    row-gap: 10px;
  }
  .ri-cover { grid-area: cover; }
  .ri-info { grid-area: info; }
  .ri-action { grid-area: action; }
  .ri-select { width: 100%; }
  .done-card { grid-template-columns: 1fr; }
  .done-card .dc-cover { width: 120px; margin: 0 auto; }
  .results-nav { justify-content: stretch; }
  .results-pager { flex: 1; justify-content: space-between; }
  .done-actions > * { flex: 1; }
}

/* Hide helpers reuse .hidden from Tailwind utilities-ish */
.hidden { display: none !important; }
</style>

@push('scripts')
@vite(['resources/js/assistant.js'])
@endpush
@endsection
