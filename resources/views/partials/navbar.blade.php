@php
  $u = auth()->user();
  $avatar = $u?->avatarUrl();
@endphp

<nav data-app-nav class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/85 backdrop-blur-xl">
  <div class="mx-auto flex h-14 sm:h-16 max-w-[1480px] items-center gap-2 px-3 sm:gap-3 sm:px-5 lg:px-6">

    {{-- T-Logo --}}
    <a href="{{ url('/home') }}" class="flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-500 text-base sm:text-lg font-extrabold tracking-tighter text-white shadow-soft" aria-label="Tanbat home">
      T
    </a>

    {{-- Search: collapses to an icon button on mobile, opens an overlay search bar --}}
    <form id="navSearch" class="relative hidden sm:block flex-1 max-w-2xl"
          action="{{ url('/search') }}" method="GET"
          autocomplete="off"
          data-search-form>
      <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="navSearchInput" name="q" type="search" autocomplete="off"
        placeholder="Search posts, people, tags…"
        value="{{ request('q', '') }}"
        class="w-full rounded-full border border-slate-200 bg-slate-50 py-2.5 pl-11 pr-10 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-brand-400 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-500/10">
      <button type="button" id="navSearchClear" class="absolute right-3 top-1/2 hidden -translate-y-1/2 rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Clear search">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>

      {{-- Live suggestions panel --}}
      <div id="navSearchPanel"
           class="absolute left-0 right-0 top-[calc(100%+8px)] hidden max-h-[70vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-pop animate-fup"
           role="listbox" aria-label="Search suggestions"></div>
    </form>

    {{-- Mobile-only spacer so the right cluster pushes to the edge --}}
    <div class="flex-1 sm:hidden"></div>

    {{-- Right cluster --}}
    <div class="ml-auto flex items-center gap-0.5">

      {{-- Mobile search trigger (opens overlay) --}}
      <button type="button" id="navSearchToggle" class="nav-icon sm:hidden" aria-label="Search">
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </button>

      {{-- Create dropdown --}}
      <div class="relative sm:mr-1" data-menu="create">
        <button type="button"
          class="flex items-center gap-1.5 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 px-2.5 py-1.5 sm:px-4 sm:py-2 text-sm font-semibold text-white shadow-soft transition hover:-translate-y-0.5 hover:shadow-pop"
          aria-haspopup="true" data-menu-trigger aria-label="Create">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          <span class="hidden md:inline">Create</span>
        </button>
        <div data-menu-panel class="nav-drop create-panel hidden origin-top-right border-slate-200 bg-white shadow-pop animate-fup">
          <button type="button" data-create="status"  class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:bg-brand-50">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-amber-100 text-amber-600">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-9 8l4-4h10a2 2 0 002-2V6a2 2 0 00-2-2H4a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <div><div>Status</div><div class="text-xs text-slate-500">Share a quick thought</div></div>
          </button>
          <button type="button" data-create="image"   class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:bg-brand-50">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-100 text-emerald-600">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <div><div>Image post</div><div class="text-xs text-slate-500">Photos to your feed</div></div>
          </button>
          <button type="button" data-create="video"   class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:bg-brand-50">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-rose-100 text-rose-600">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </span>
            <div><div>Video post</div><div class="text-xs text-slate-500">Share a clip</div></div>
          </button>
          <a href="{{ url('/articles/create') }}" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-slate-700 hover:bg-brand-50">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-sky-100 text-sky-600">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </span>
            <div><div>Article post</div><div class="text-xs text-slate-500">Long-form with editor</div></div>
          </a>
        </div>
      </div>

      {{-- Notifications --}}
      <div class="relative" data-menu="notifications">
        <button type="button" class="nav-icon" title="Notifications" aria-label="Notifications" data-menu-trigger>
          <svg class="h-[18px] w-[18px] sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
          <span id="notifBadge" class="hidden absolute -right-0.5 -top-0.5 grid min-w-[18px] h-[18px] place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white ring-2 ring-white"></span>
        </button>
        <div data-menu-panel class="nav-drop notif-panel hidden origin-top-right border-slate-200 bg-white shadow-pop animate-fup">
          <div class="nav-drop-head">
            <div class="nav-drop-title">Notifications</div>
            <button type="button" id="notifMarkRead">Mark all read</button>
          </div>
          <div id="notifList" class="nav-drop-list">
            <div class="py-8 text-center text-xs text-slate-400">Loading…</div>
          </div>
          <div class="border-t border-slate-100 px-4 py-2 text-center">
            <a href="#" class="text-xs font-semibold text-slate-500 hover:text-brand-600">See all activity</a>
          </div>
        </div>
      </div>

      {{-- Messages --}}
      <button type="button" class="nav-icon" title="Messages" aria-label="Messages" data-action="messages">
        <svg class="h-[18px] w-[18px] sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
      </button>

      {{-- People discovery --}}
      <a href="{{ url('/discover/people') }}" class="nav-icon hidden sm:grid {{ request()->is('discover/people') ? '!text-brand-600 bg-brand-50' : '' }}" title="People" aria-label="People">
        <svg class="h-[18px] w-[18px] sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      </a>

      {{-- Admin panel (admins only) --}}
      @if($u && $u->isAdmin())
        <a href="{{ route('admin.dashboard') }}"
           class="nav-icon relative ml-0.5 bg-gradient-to-br from-violet-500 to-brand-600 !text-white hover:!text-white hover:from-violet-600 hover:to-brand-700 hover:bg-none shadow-soft"
           title="Admin panel" aria-label="Admin panel">
          <svg class="h-[18px] w-[18px] sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6l8-4z"/>
            <path d="M9 12l2 2 4-4"/>
          </svg>
        </a>
      @endif

      {{-- Divider --}}
      <div class="mx-1.5 hidden h-6 w-px bg-slate-200 sm:block"></div>

      {{-- Profile dropdown --}}
      <div class="relative" data-menu="profile">
        <button type="button" data-menu-trigger class="flex items-center rounded-full p-0.5 transition hover:bg-slate-100" aria-label="Profile menu">
          @if($avatar)
            <img src="{{ $avatar }}" alt="" class="h-8 w-8 sm:h-9 sm:w-9 rounded-full object-cover ring-2 ring-brand-500/70">
          @else
            <span class="grid h-8 w-8 sm:h-9 sm:w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white ring-2 ring-brand-500/70">{{ strtoupper(substr($u->username ?? $u->name ?? 'U', 0, 1)) }}</span>
          @endif
          <svg class="ml-0.5 mr-0.5 hidden sm:block h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div data-menu-panel class="nav-drop profile-panel hidden origin-top-right border-slate-200 bg-white shadow-pop animate-fup">
          <div class="flex items-center gap-3 rounded-xl px-3 py-2.5">
            @if($avatar)
              <img src="{{ $avatar }}" class="h-10 w-10 rounded-full object-cover" alt="">
            @else
              <span class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white">{{ strtoupper(substr($u->username ?? $u->name ?? 'U', 0, 1)) }}</span>
            @endif
            <div class="min-w-0">
              <div class="truncate text-sm font-semibold text-slate-900">{{ $u->name }}</div>
              <div class="truncate text-xs text-slate-500">&#64;{{ $u->username }}</div>
            </div>
          </div>
          <div class="my-1 h-px bg-slate-100"></div>
          <a href="{{ url('/u/' . ($u->username ?? '')) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-brand-50">
            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            View profile
          </a>
          {{-- People link surfaced in profile menu so we can hide its icon on very small screens --}}
          <a href="{{ url('/discover/people') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-brand-50 sm:hidden">
            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Discover people
          </a>
          <button type="button"
                  data-action="share-my-profile"
                  data-share-url="{{ url('/u/' . ($u->username ?? '')) }}"
                  data-share-title="{{ $u->name }} (@{{ $u->username }}) on Tanbat"
                  data-share-image="{{ $u->avatarUrl() ?? '' }}"
                  class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-slate-700 hover:bg-brand-50">
            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Share my profile
          </button>
          <a href="#" data-action="settings" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-brand-50">
            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Profile settings
          </a>
          @if($u && $u->isAdmin())
            <div class="my-1 h-px bg-slate-100"></div>
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6l8-4z"/>
                <path d="M9 12l2 2 4-4"/>
              </svg>
              Admin panel
              <span class="ml-auto rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-700">Admin</span>
            </a>
          @endif
          <div class="my-1 h-px bg-slate-100"></div>
          <button type="button" data-action="logout" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign out
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- Mobile search overlay (slides down when triggered) --}}
  <div id="mobileSearchBar" class="hidden border-t border-slate-200/70 bg-white/95 px-3 py-2 sm:hidden">
    <form id="navSearchMobile" action="{{ url('/search') }}" method="GET" autocomplete="off" data-search-form class="relative">
      <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input name="q" type="search" autocomplete="off"
        placeholder="Search posts, people, tags…"
        value="{{ request('q', '') }}"
        class="w-full rounded-full border border-slate-200 bg-slate-50 py-2.5 pl-11 pr-10 text-base text-slate-900 placeholder:text-slate-400 transition focus:border-brand-400 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-500/10">
      <button type="button" id="mobileSearchClose" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close search">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </form>
  </div>
</nav>

<style>
  /* ───── Navbar dropdown panels ─────
     Desktop: bell-/avatar-anchored rounded popovers.
     Mobile (<640px): full-width sheets pinned below the navbar.
     Raw CSS so it doesn't depend on a Tailwind rebuild. */
  .nav-drop{
    position:absolute;right:0;top:100%;margin-top:8px;
    border-width:1px;border-style:solid;
    border-radius:16px;
    overflow:hidden;
    z-index:50;
  }
  .notif-panel  { width:360px; }
  .create-panel { width:260px; padding:6px; }
  .profile-panel{ width:264px; padding:6px; }
  .msg-panel    { width:360px; }   /* used by messenger.js threads dropdown */

  /* Scrollable list region inside a dropdown (notif / msg) */
  .nav-drop-list{ max-height:420px; overflow-y:auto; }

  /* Panel section header (title + action link), bigger type on mobile */
  .nav-drop-head{
    display:flex;align-items:center;justify-content:space-between;
    border-bottom:1px solid #F1F5F9;
    padding:12px 16px;
  }
  .nav-drop-head .nav-drop-title{ font-size:14px;font-weight:700;color:#1E1B4B; }
  .nav-drop-head a, .nav-drop-head button{
    font-size:12px;font-weight:600;color:#5A52D5;
  }
  .nav-drop-head a:hover, .nav-drop-head button:hover{ text-decoration:underline; }

  @media (max-width:639px){
    /* CRITICAL: drop backdrop-filter on the navbar at mobile width.
       backdrop-filter creates a "containing block" for any position:fixed
       descendant, so the dropdown panels below would be measured relative
       to the nav (whose inner box is offset by the wrap <span> messenger.js
       injects) instead of the viewport — causing the panel to slip left/right
       and not span the full screen. With the filter removed, the panels
       re-anchor to the real viewport edges. The bg-white/85 background still
       looks fine without the blur. */
    nav[data-app-nav]{
      backdrop-filter:none !important;
      -webkit-backdrop-filter:none !important;
      background:#ffffff !important;
    }

    .nav-drop{
      position:fixed;top:56px;left:0;right:0;
      width:100vw;margin-top:0;
      border-radius:0;border-left:0;border-right:0;
      max-height:calc(100vh - 56px);
      max-height:calc(100dvh - 56px);
      overflow-y:auto;
      padding:0;
    }
    .create-panel,.profile-panel{ padding:6px; }
    .nav-drop-list{ max-height:none; overflow:visible; }
    .nav-drop-head{ padding:14px 16px; }
    .nav-drop-head .nav-drop-title{ font-size:16px; }
    .nav-drop-head a, .nav-drop-head button{ font-size:13px; }
  }
</style>

<script>
  (function(){
    const toggle = document.getElementById('navSearchToggle');
    const bar    = document.getElementById('mobileSearchBar');
    const close  = document.getElementById('mobileSearchClose');
    if (!toggle || !bar) return;
    toggle.addEventListener('click', () => {
      bar.classList.toggle('hidden');
      if (!bar.classList.contains('hidden')) {
        setTimeout(() => bar.querySelector('input')?.focus(), 30);
      }
    });
    close?.addEventListener('click', () => bar.classList.add('hidden'));
  })();
</script>
