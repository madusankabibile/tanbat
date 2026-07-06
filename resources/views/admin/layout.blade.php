<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Admin') · Tanbat</title>
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
{{-- Non-blocking webfont load (see layouts/app.blade.php) so the Google Fonts
     CDN can't gate first paint; app.css below stays render-blocking via @vite. --}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"></noscript>
@vite(['resources/css/app.css'])
<style>
  /* NOTE: this block is plain CSS, not run through Tailwind/PostCSS, so it must
     not use @apply — those directives are silently dropped by the browser.
     Everything below is authored as literal CSS on purpose. */
  :root {
    --ad-line:      #E9EBF4;   /* card / table borders            */
    --ad-line-soft: #F1F3FB;   /* inner row dividers              */
    --ad-muted:     #6B7194;   /* secondary text                 */
    --ad-brand:     #6C63FF;
    --ad-brand-d:   #5A52D5;
  }

  /* ── Shell ───────────────────────────────────────────── */
  .admin-shell { display: grid; grid-template-columns: 264px 1fr; min-height: 100vh; }
  @media (max-width: 1023px) { .admin-shell { grid-template-columns: 1fr; } .admin-side { display: none; } }
  .admin-side {
    background: linear-gradient(180deg, #1E1B4B 0%, #2A2566 100%);
    color: #DBDDFF;
    position: sticky; top: 0; height: 100vh; overflow-y: auto;
  }
  .admin-side a.nav-link {
    display: flex; align-items: center; gap: .75rem;
    padding: .65rem .9rem; border-radius: .7rem;
    color: #BFC2E8; font-weight: 500; font-size: .9rem;
    transition: all .15s ease;
  }
  .admin-side a.nav-link svg { flex: 0 0 auto; opacity: .9; }
  .admin-side a.nav-link:hover { background: rgba(255,255,255,.06); color: #fff; }
  .admin-side a.nav-link.active {
    background: linear-gradient(135deg, #6C63FF, #5A52D5);
    color: #fff;
    box-shadow: 0 6px 18px rgba(108,99,255,.35);
  }
  .admin-side .nav-section {
    text-transform: uppercase; letter-spacing: .12em; font-size: .68rem;
    color: #7E84BB; padding: 1.3rem .9rem .4rem;
  }

  /* ── KPI cards ───────────────────────────────────────── */
  .kpi {
    border-radius: 1rem; background: #fff; padding: 1.3rem 1.4rem;
    border: 1px solid var(--ad-line); box-shadow: 0 4px 16px rgba(20,20,50,.05);
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .kpi:hover { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(20,20,50,.09); }
  .kpi .kpi-label { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ad-muted); }
  .kpi .kpi-value { margin-top: .55rem; font-size: 1.95rem; font-weight: 800; line-height: 1.05; letter-spacing: -.02em; color: #0F1130; }
  .kpi .kpi-foot  { margin-top: .4rem; font-size: .72rem; color: var(--ad-muted); }

  /* ── Badges ──────────────────────────────────────────── */
  .badge {
    display: inline-flex; align-items: center; gap: .25rem;
    border-radius: 9999px; padding: .22rem .6rem;
    font-size: .7rem; font-weight: 600; line-height: 1.35; text-transform: capitalize;
  }
  .badge-admin   { background: #EDE9FE; color: #6D28D9; }
  .badge-user    { background: #F1F5F9; color: #475569; }
  .badge-on      { background: #DCFCE7; color: #15803D; }
  .badge-off     { background: #F1F5F9; color: #64748B; }
  .badge-live    { background: #DCFCE7; color: #15803D; }
  .badge-expired { background: #FFE4E6; color: #BE123C; }
  .badge-type    { background: #EEF0FF; color: #4842B0; }

  /* ── Tables ──────────────────────────────────────────── */
  .table-card {
    overflow: hidden; border-radius: 1rem; background: #fff;
    border: 1px solid var(--ad-line); box-shadow: 0 4px 16px rgba(20,20,50,.05);
  }
  .table-card table { width: 100%; border-collapse: separate; border-spacing: 0; }
  .table-card th {
    background: #FAFBFE; padding: .9rem 1.25rem; text-align: left;
    font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--ad-muted); white-space: nowrap; border-bottom: 1px solid var(--ad-line);
  }
  .table-card td {
    padding: 1.05rem 1.25rem; font-size: .875rem; color: #334155;
    vertical-align: middle; border-top: 1px solid var(--ad-line-soft);
  }
  .table-card tbody tr:first-child td { border-top: none; }
  .table-card tbody tr { transition: background .12s ease; }
  .table-card tbody tr:hover td { background: #F8F9FF; }
  .table-card th:first-child, .table-card td:first-child { padding-left: 1.5rem; }
  .table-card th:last-child,  .table-card td:last-child  { padding-right: 1.5rem; }
  .table-card th.text-right, .table-card td.text-right { text-align: right; }
  .table-card td .inline-flex { white-space: nowrap; }

  /* ── Compact action buttons (replace cramped inline styles) ── */
  .btn-xs {
    display: inline-flex; align-items: center; justify-content: center; gap: .3rem;
    border: 1px solid var(--ad-line); background: #fff; color: #475569;
    border-radius: .6rem; padding: .42rem .75rem; font-size: .76rem; font-weight: 600;
    line-height: 1; white-space: nowrap; cursor: pointer; transition: all .13s ease;
  }
  .btn-xs:hover { border-color: #C5C9FF; color: var(--ad-brand); background: #FAFAFF; }
  .btn-xs:disabled { opacity: .5; cursor: not-allowed; }
  .btn-xs-primary { border-color: transparent; background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff; }
  .btn-xs-primary:hover { color: #fff; box-shadow: 0 6px 16px rgba(108,99,255,.3); background: linear-gradient(135deg,#6C63FF,#5A52D5); }
  .btn-xs-danger { color: #BE123C; border-color: #FECDD3; }
  .btn-xs-danger:hover { color: #BE123C; background: #FFF1F2; border-color: #FDA4AF; }
  .btn-xs-warn { color: #C2410C; border-color: #FED7AA; }
  .btn-xs-warn:hover { color: #C2410C; background: #FFF7ED; border-color: #FDBA74; }

  /* ── Pagination ──────────────────────────────────────── */
  .pager { display: flex; align-items: center; gap: .375rem; flex-wrap: wrap; }
  .pager a, .pager span {
    display: inline-flex; align-items: center; justify-content: center;
    height: 2rem; min-width: 2rem; padding: 0 .6rem; border-radius: .6rem;
    font-size: .8rem; font-weight: 600;
  }
  .pager a { background: #fff; border: 1px solid var(--ad-line); color: #475569; transition: all .13s ease; }
  .pager a:hover { border-color: #C5C9FF; color: var(--ad-brand); }
  .pager span[aria-current] > span,
  .pager span.active { background: var(--ad-brand); color: #fff; border-radius: .6rem; }
  .pager span.disabled, .pager [aria-disabled] { color: #CBD5E1; }
  .pager svg { width: 1.1rem; height: 1.1rem; }

  /* ── Misc ────────────────────────────────────────────── */
  .avatar-circle {
    width: 36px; height: 36px; border-radius: 9999px; object-fit: cover; background: #E0E3FF;
    display: inline-grid; place-items: center; font-weight: 700; color: #5A52D5; font-size: .8rem;
    flex: 0 0 auto;
  }
  .flash {
    margin-bottom: 1.25rem; display: flex; align-items: center; gap: .75rem;
    border-radius: .8rem; border: 1px solid #A7F3D0; background: #ECFDF5;
    padding: .8rem 1.05rem; font-size: .875rem; font-weight: 600; color: #065F46;
  }
  .field-error { margin-top: .25rem; font-size: .75rem; font-weight: 600; color: #E11D48; }

  /* ── Row action menu (kebab dropdown) ────────────────────
     The panel is position:fixed and JS-positioned so it escapes the
     .table-card { overflow:hidden } clip. */
  .row-menu { display: inline-block; }
  .row-menu__btn {
    display: inline-grid; place-items: center; width: 2rem; height: 2rem;
    border-radius: .6rem; border: 1px solid var(--ad-line); background: #fff;
    color: #64748B; cursor: pointer; transition: all .13s ease;
  }
  .row-menu__btn:hover,
  .row-menu__btn[aria-expanded="true"] { border-color: #C5C9FF; color: var(--ad-brand); background: #FAFAFF; }
  .row-menu__panel {
    position: fixed; z-index: 80; min-width: 11rem; padding: .375rem;
    background: #fff; border: 1px solid var(--ad-line); border-radius: .75rem;
    box-shadow: 0 12px 32px rgba(30,27,75,.16), 0 2px 8px rgba(30,27,75,.06);
    opacity: 0; transform: translateY(-4px); pointer-events: none;
    transition: opacity .12s ease, transform .12s ease;
  }
  .row-menu__panel.is-open { opacity: 1; transform: translateY(0); pointer-events: auto; }
  .row-menu__panel form { display: block; margin: 0; }
  .row-menu__item {
    display: flex; align-items: center; gap: .55rem; width: 100%;
    padding: .5rem .625rem; border: 0; border-radius: .5rem; background: transparent;
    font-size: .8125rem; font-weight: 600; color: #334155; text-align: left; cursor: pointer;
    transition: background .1s ease, color .1s ease;
  }
  .row-menu__item svg { width: 1rem; height: 1rem; flex: 0 0 auto; }
  .row-menu__item:hover { background: #F4F5FF; color: var(--ad-brand); }
  .row-menu__item:disabled { opacity: .45; cursor: not-allowed; color: #94A3B8; }
  .row-menu__item:disabled:hover { background: transparent; color: #94A3B8; }
  .row-menu__item--danger { color: #BE123C; }
  .row-menu__item--danger:hover { background: #FFF1F2; color: #BE123C; }
  .row-menu__divider { height: 1px; margin: .3rem .25rem; background: var(--ad-line); }
</style>
@stack('head')
</head>
<body class="bg-[#F7F8FF]">

@php
  $u = auth()->user();
  $initials = $u ? strtoupper(mb_substr($u->name, 0, 1)) : 'A';
@endphp

<div class="admin-shell">

  {{-- ─────────── SIDEBAR ─────────── --}}
  <aside class="admin-side">
    <div class="px-5 py-5">
      <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-white font-extrabold shadow-pop">T</span>
        <span class="text-white font-extrabold tracking-tight">Tanbat <span class="text-brand-300">Admin</span></span>
      </a>
    </div>

    <nav class="px-3 pb-6">
      <div class="nav-section">Overview</div>
      <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        Dashboard
      </a>

      <div class="nav-section">Manage</div>
      <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Users
      </a>
      <a href="{{ route('admin.posts.index') }}" class="nav-link {{ request()->routeIs('admin.posts.*') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
        Posts
      </a>
      <a href="{{ route('admin.books.index') }}" class="nav-link {{ request()->routeIs('admin.books.*') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        Books
      </a>
      <a href="{{ route('admin.categories.index') }}" class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
        Categories
      </a>
      <a href="{{ route('admin.ads.index') }}" class="nav-link {{ request()->routeIs('admin.ads.*') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-8v18l-18-8z"/><path d="M11.6 12.5a3 3 0 1 0-5.2 3"/></svg>
        Advertisements
      </a>

      <div class="nav-section">Integrations</div>
      <a href="{{ route('admin.book-search.index') }}" class="nav-link {{ request()->routeIs('admin.book-search.*') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Book Search Engine
      </a>
      <a href="{{ route('admin.reddit.index') }}" class="nav-link {{ request()->routeIs('admin.reddit.*') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0A12 12 0 1 0 24 12 12 12 0 0 0 12 0Zm6.3 13.7a1 1 0 0 1 0 1.4 6.7 6.7 0 0 1-9.4 0 1 1 0 0 1 1.4-1.4 4.7 4.7 0 0 0 6.6 0 1 1 0 0 1 1.4 0Zm-9.4-3a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Zm5 0a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Z"/></svg>
        Reddit
      </a>
      <a href="{{ route('admin.pinterest.index') }}" class="nav-link {{ request()->routeIs('admin.pinterest.*') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0a12 12 0 0 0-4.37 23.17c-.1-.94-.2-2.4.04-3.44.22-.93 1.4-5.96 1.4-5.96s-.36-.72-.36-1.78c0-1.67.97-2.92 2.17-2.92 1.02 0 1.51.77 1.51 1.69 0 1.03-.65 2.56-.99 3.98-.28 1.19.6 2.16 1.77 2.16 2.12 0 3.76-2.24 3.76-5.48 0-2.86-2.06-4.86-5-4.86-3.41 0-5.41 2.56-5.41 5.2 0 1.03.4 2.13.89 2.73.1.12.11.22.08.34l-.33 1.35c-.05.22-.17.27-.4.16-1.49-.69-2.42-2.87-2.42-4.62 0-3.76 2.73-7.21 7.88-7.21 4.13 0 7.35 2.95 7.35 6.88 0 4.11-2.59 7.42-6.18 7.42-1.21 0-2.34-.63-2.73-1.37l-.74 2.83c-.27 1.03-1 2.32-1.48 3.11A12 12 0 1 0 12 0Z"/></svg>
        Pinterest
      </a>

      <div class="nav-section">Site</div>
      <a href="{{ url('/home') }}" class="nav-link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
        Back to site
      </a>
      <form method="POST" action="{{ url('/auth/logout') }}" class="px-1 mt-1">
        @csrf
        <button class="nav-link w-full text-left" type="submit">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign out
        </button>
      </form>
    </nav>
  </aside>

  {{-- ─────────── MAIN ─────────── --}}
  <main class="min-w-0">
    <header class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-200 bg-white/80 px-6 py-3 backdrop-blur">
      <div>
        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">@yield('breadcrumb', 'Admin')</div>
        <h1 class="text-xl font-extrabold text-slate-900">@yield('heading', 'Dashboard')</h1>
      </div>
      <div class="flex items-center gap-3">
        <span class="hidden text-right sm:block">
          <span class="block text-sm font-semibold text-slate-800">{{ $u?->name }}</span>
          <span class="block text-xs text-slate-500">{{ '@' . $u?->username }}</span>
        </span>
        <span class="avatar-circle">{{ $initials }}</span>
      </div>
    </header>

    <div class="px-6 py-6">
      @if(session('status'))
        <div class="flash">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          {{ session('status') }}
        </div>
      @endif

      @yield('content')
    </div>
  </main>

</div>

{{-- Row action menus (kebab dropdowns). Delegated, so it works for any number
     of `.row-menu` on the page without per-row handlers. The panel is moved to
     <body> and fixed-positioned on open so the table's overflow:hidden can't
     clip it; it's returned to its row on close. --}}
<script>
(function () {
  var open = null; // { panel, home, next }

  function close() {
    if (!open) return;
    open.panel.classList.remove('is-open');
    open.btn.setAttribute('aria-expanded', 'false');
    var p = open.panel, home = open.home, next = open.next;
    // After the fade, restore the panel to its original spot in the DOM.
    setTimeout(function () {
      if (p.parentNode === document.body) home.insertBefore(p, next);
      p.style.cssText = '';
    }, 130);
    open = null;
  }

  function position(panel, btn) {
    var r = btn.getBoundingClientRect();
    // Right-align the panel to the button; flip above if it'd overflow bottom.
    panel.style.visibility = 'hidden';
    panel.style.display = 'block';
    var pw = panel.offsetWidth, ph = panel.offsetHeight;
    var left = Math.max(8, r.right - pw);
    var top  = r.bottom + 6;
    if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 6);
    panel.style.left = left + 'px';
    panel.style.top  = top + 'px';
    panel.style.visibility = '';
    panel.style.display = '';
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.row-menu__btn');
    if (btn) {
      e.preventDefault();
      var menu  = btn.closest('.row-menu');
      var panel = menu.querySelector('.row-menu__panel');
      var wasOpenHere = open && open.panel === panel;
      close();
      if (wasOpenHere) return;
      open = { panel: panel, btn: btn, home: panel.parentNode, next: panel.nextSibling };
      document.body.appendChild(panel);
      position(panel, btn);
      // Next frame so the transition runs.
      requestAnimationFrame(function () {
        panel.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
      });
      return;
    }
    // Click outside an open panel closes it (clicks inside fall through to the
    // form buttons normally).
    if (open && !e.target.closest('.row-menu__panel')) close();
  });

  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  window.addEventListener('scroll', close, true);
  window.addEventListener('resize', close);
})();
</script>

@stack('scripts')
</body>
</html>
