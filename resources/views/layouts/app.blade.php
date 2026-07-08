<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Tanbat — Connect & Share')</title>
{{-- Critical paint hints: open early TCP/TLS to the font CDNs so the
     render-blocking stylesheet below doesn't gate paint as long. --}}
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
{{-- Inline critical CSS: paint the body bg and a sensible fallback font
     BEFORE app.css arrives, so reloads don't flash white-on-Times-New-Roman
     while Tailwind's compiled bundle is still in flight. --}}
<style>html,body{background:#F7F8FF;color:#1E1B4B;font-family:'Inter',ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;-webkit-font-smoothing:antialiased;margin:0;min-height:100vh;}
/* The adblock-probe scripts in runtime-check.js load Google's ad scripts,
   which makes AdSense auto-ads inject an anchor <ins class="adsbygoogle"> as the
   first <body> child. As an inline-block it spawns a ~24px line box at the very
   top, pushing the sticky navbar down 24px — a gap that "fills" the moment you
   scroll and the nav pins to top:0. Force the (empty/reserved) slot to block so
   it collapses to 0 height; a real anchor ad uses position:fixed so this is a
   no-op when one actually renders. */
body > ins.adsbygoogle{display:block!important;}
/* Boot splash: a full-page SKELETON of the real layout (navbar + left/center/
   right rails), painted from inline CSS so it shows instantly — before app.css
   loads — and masks the unstyled-HTML flash on reload. JS fades + removes it
   once the page has loaded and styled. */
#tb-splash{position:fixed;inset:0;z-index:2147483647;background:#F7F8FF;overflow:hidden;transition:opacity .25s ease;}
#tb-splash.tb-hide{opacity:0;pointer-events:none;}
#tb-splash .sp{background:linear-gradient(90deg,#E9EBF4 25%,#F6F8FC 50%,#E9EBF4 75%);background-size:200% 100%;animation:spShine 1.25s infinite linear;border-radius:8px;display:block;}
@keyframes spShine{from{background-position:200% 0;}to{background-position:-200% 0;}}
@media (prefers-reduced-motion: reduce){#tb-splash .sp{animation:none;}}
#tb-splash .sp-card{background:#fff;border:1px solid #E5E7EB;border-radius:12px;box-shadow:0 1px 2px rgba(20,20,50,.04);overflow:hidden;}
#tb-splash .sp-line{height:12px;border-radius:6px;}
/* Navbar — mirrors partials/navbar.blade.php (h-14/sm:h-16, max-w-1480 inner,
   max-w-2xl search, icon cluster + divider + avatar) so it lines up with the
   real bar and the shell below it. */
#tb-splash .sp-nav{height:56px;background:#fff;border-bottom:1px solid #E5E7EB;}
#tb-splash .sp-nav-inner{height:100%;max-width:1480px;margin:0 auto;display:flex;align-items:center;gap:12px;padding:0 12px;}
#tb-splash .sp-logo{width:36px;height:36px;border-radius:12px;flex-shrink:0;}
#tb-splash .sp-search{display:none;}
#tb-splash .sp-spacer{flex:1;}
#tb-splash .sp-nav-r{margin-left:auto;display:flex;align-items:center;gap:8px;}
#tb-splash .sp-btn{width:44px;height:38px;border-radius:12px;flex-shrink:0;}
#tb-splash .sp-ico{width:38px;height:38px;border-radius:9999px;flex-shrink:0;}
#tb-splash .sp-divider{width:1px;height:24px;background:#E5E7EB;margin:0 4px;flex-shrink:0;}
#tb-splash .sp-avatar{width:36px;height:36px;border-radius:9999px;flex-shrink:0;}
@media(min-width:640px){
  #tb-splash .sp-nav{height:64px;}
  #tb-splash .sp-nav-inner{gap:14px;padding:0 20px;}
  #tb-splash .sp-logo{width:40px;height:40px;}
  #tb-splash .sp-search{display:block;height:42px;border-radius:9999px;flex:1;max-width:672px;}
  #tb-splash .sp-spacer{display:none;}
  #tb-splash .sp-avatar{width:36px;height:36px;}
}
@media(min-width:768px){#tb-splash .sp-btn{width:104px;}}
@media(min-width:1024px){#tb-splash .sp-nav-inner{padding:0 24px;}}
/* Shell grid — mirrors .home-shell breakpoints */
#tb-splash .sp-shell{max-width:1480px;margin:0 auto;padding:20px 12px;display:grid;grid-template-columns:1fr;gap:20px;}
@media(min-width:640px){#tb-splash .sp-shell{padding:20px;}}
@media(min-width:1024px){#tb-splash .sp-shell{padding:20px 24px;}}
#tb-splash .sp-left,#tb-splash .sp-right{display:none;}
@media(min-width:1024px){#tb-splash .sp-shell{grid-template-columns:280px minmax(0,1fr);}#tb-splash .sp-left{display:block;}}
@media(min-width:1280px){#tb-splash .sp-shell{grid-template-columns:280px minmax(0,1fr) 320px;}#tb-splash .sp-right{display:block;}}
#tb-splash .sp-col{display:flex;flex-direction:column;gap:16px;}
#tb-splash .sp-pad{padding:14px;}
/* Left profile card */
#tb-splash .sp-cover{height:72px;}
#tb-splash .sp-av-lg{width:64px;height:64px;border-radius:9999px;margin:-32px auto 0;border:3px solid #fff;}
#tb-splash .sp-block{height:46px;border-radius:10px;}
/* Center feed */
#tb-splash .sp-center{min-width:0;max-width:640px;width:100%;margin:0 auto;}
#tb-splash .sp-chips{display:flex;gap:8px;margin-bottom:16px;}
#tb-splash .sp-chip{width:64px;height:32px;border-radius:9999px;}
#tb-splash .sp-feed{display:flex;flex-direction:column;gap:16px;}
#tb-splash .sp-head{display:flex;align-items:center;gap:10px;}
#tb-splash .sp-av{width:40px;height:40px;border-radius:9999px;flex-shrink:0;}
#tb-splash .sp-hlines{flex:1;display:flex;flex-direction:column;gap:6px;}
#tb-splash .sp-media{height:260px;}
#tb-splash .sp-actions{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;}
#tb-splash .sp-pill{height:26px;border-radius:8px;}
</style>
{{-- Load the third-party CDN stylesheets WITHOUT blocking first paint. The
     `media=print`→`onload media=all` swap lets the browser fetch them without
     gating render, so a slow font/Plyr CDN can't hold the whole page hostage.
     Inter falls back to the inline critical font above until the webfont lands
     (display=swap), and app.css below stays render-blocking via @vite — so it
     is the only sheet that gates styled paint, and it's served from our host. --}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" media="print" onload="this.media='all'">
<noscript>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css">
</noscript>
<script defer src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/messenger.js', 'resources/js/share-module.js', 'resources/js/runtime-check.js'])
@stack('head')
</head>
<body class="bg-slate-50">

{{-- Boot splash overlay: a shimmering skeleton of the page (navbar + rails +
     feed) that covers everything until the page has loaded & styled, then
     fades out. Kept up for a short minimum so it reads as deliberate, and
     force-removed after a safety cap so a stalled asset can never trap it. --}}
<div id="tb-splash" aria-hidden="true">
  {{-- Navbar --}}
  <div class="sp-nav">
    <div class="sp-nav-inner">
      <span class="sp sp-logo"></span>
      <span class="sp sp-search"></span>
      <span class="sp-spacer"></span>
      <span class="sp-nav-r">
        <span class="sp sp-btn"></span>
        <span class="sp sp-ico"></span>
        <span class="sp sp-ico"></span>
        <span class="sp sp-ico"></span>
        <span class="sp-divider"></span>
        <span class="sp sp-avatar"></span>
      </span>
    </div>
  </div>
  {{-- Three-column shell --}}
  <div class="sp-shell">
    {{-- Left rail --}}
    <div class="sp-left sp-col">
      <div class="sp-card">
        <span class="sp sp-cover"></span>
        <div class="sp-pad" style="text-align:center">
          <span class="sp sp-av-lg"></span>
          <span class="sp sp-line" style="width:55%;margin:12px auto 0"></span>
          <span class="sp sp-line" style="width:34%;height:10px;margin:8px auto 0"></span>
        </div>
      </div>
      <div class="sp-card sp-pad"><span class="sp sp-block"></span></div>
      <div class="sp-card sp-pad"><span class="sp sp-block"></span></div>
    </div>
    {{-- Center feed --}}
    <div class="sp-center">
      <div class="sp-chips">
        <span class="sp sp-chip"></span>
        <span class="sp sp-chip"></span>
        <span class="sp sp-chip"></span>
        <span class="sp sp-chip"></span>
      </div>
      <div class="sp-feed">
        @for ($i = 0; $i < 2; $i++)
          <div class="sp-card">
            <div class="sp-pad sp-head">
              <span class="sp sp-av"></span>
              <span class="sp-hlines">
                <span class="sp sp-line" style="width:42%"></span>
                <span class="sp sp-line" style="width:26%;height:10px"></span>
              </span>
            </div>
            <div class="sp-pad" style="padding-top:0;display:flex;flex-direction:column;gap:8px">
              <span class="sp sp-line" style="width:94%"></span>
              <span class="sp sp-line" style="width:78%"></span>
            </div>
            <span class="sp sp-media"></span>
            <div class="sp-pad sp-actions">
              <span class="sp sp-pill"></span>
              <span class="sp sp-pill"></span>
              <span class="sp sp-pill"></span>
            </div>
          </div>
        @endfor
      </div>
    </div>
    {{-- Right rail --}}
    <div class="sp-right sp-col">
      <div class="sp-card sp-pad"><span class="sp" style="height:250px;border-radius:8px"></span></div>
      <div class="sp-card sp-pad">
        <span class="sp sp-line" style="width:45%;margin-bottom:12px"></span>
        <span class="sp sp-block"></span>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var MIN_MS = 350;            // minimum time the splash stays visible
  var SAFETY_MS = 4000;        // hard cap — never trap the user behind it
  var start = Date.now(), done = false;
  function hide() {
    var s = document.getElementById('tb-splash');
    if (!s || done) return;
    done = true;
    var wait = Math.max(0, MIN_MS - (Date.now() - start));
    setTimeout(function () {
      s.classList.add('tb-hide');
      setTimeout(function () { if (s.parentNode) s.parentNode.removeChild(s); }, 260);
    }, wait);
  }
  // Reveal once everything (incl. app.css) has loaded; load fires after the
  // render-blocking stylesheet, so the page underneath is already styled.
  if (document.readyState === 'complete') hide();
  else window.addEventListener('load', hide);
  setTimeout(hide, SAFETY_MS);
})();
</script>

<div id="toast" class="pointer-events-none fixed left-1/2 bottom-8 z-[9999] -translate-x-1/2 translate-y-16 rounded-xl px-5 py-3 text-sm font-medium text-white opacity-0 shadow-pop transition-all duration-300"></div>

@yield('content')

@php
  $authUser = auth()->user();
  $userPayload = $authUser ? [
    'id'              => $authUser->id,
    'name'            => $authUser->name,
    'username'        => $authUser->username,
    'email'           => $authUser->email,
    'role'            => $authUser->role,
    'profile_picture' => $authUser->avatarUrl(),
  ] : null;
  $appBase = rtrim(config('app.url'), '/');
@endphp
<script>
  window.__APP__ = {
    csrf: document.querySelector('meta[name="csrf-token"]').content,
    user: {!! json_encode($userPayload) !!},
    urls: {
      base:          {!! json_encode($appBase) !!},
      logout:        {!! json_encode(url('/auth/logout')) !!},
      home:          {!! json_encode(url('/home')) !!},
      landing:       {!! json_encode(url('/')) !!},
      articleCreate: {!! json_encode(url('/articles/create')) !!},
      assistant:     {!! json_encode(url('/assistant')) !!},
      books:         {!! json_encode(url('/books')) !!},
      api: {
        categories:    {!! json_encode(url('/api/categories')) !!},
        sidebar:       {!! json_encode(url('/api/sidebar')) !!},
        visitors:      {!! json_encode(url('/api/visitors')) !!},
        posts:         {!! json_encode(url('/api/posts')) !!},
        feed:            {!! json_encode(url('/api/feed')) !!},
        feedImpressions: {!! json_encode(url('/api/feed/impressions')) !!},
        statusPost:    {!! json_encode(url('/api/posts/status')) !!},
        imagePost:     {!! json_encode(url('/api/posts/image')) !!},
        videoPost:     {!! json_encode(url('/api/posts/video')) !!},
        articlePost:   {!! json_encode(url('/api/posts/article')) !!},
        inlineImage:   {!! json_encode(url('/api/posts/inline-image')) !!},
        users:         {!! json_encode(url('/api/users')) !!},
        notifications: {!! json_encode(url('/api/notifications')) !!},
        commentDelete: {!! json_encode(url('/api/comments') . '/:id') !!},
        postSave:      {!! json_encode(url('/api/posts') . '/:id/save') !!},
        postHide:      {!! json_encode(url('/api/posts') . '/:id/hide') !!},
        postUpdate:    {!! json_encode(url('/api/posts') . '/:id') !!},
        postDelete:    {!! json_encode(url('/api/posts') . '/:id') !!},
        postsSaved:    {!! json_encode(url('/api/posts/saved')) !!},
        assistantSearch:  {!! json_encode(url('/api/assistant/search')) !!},
        assistantConfirm: {!! json_encode(url('/api/assistant/confirm')) !!},
        assistantStatus:  {!! json_encode(url('/api/assistant/status')) !!},
        books:            {!! json_encode(url('/api/books')) !!},
        saveCategories: {!! json_encode(url('/api/save-categories')) !!},
        saveCategory:   {!! json_encode(url('/api/save-categories') . '/:id') !!},
        messages: {
          threads: {!! json_encode(url('/api/messages/threads')) !!},
          thread:  {!! json_encode(url('/api/messages/threads')) !!},
          unread:  {!! json_encode(url('/api/messages/unread-count')) !!},
        },
      },
      profileBase:   {!! json_encode(url('/u')) !!},
      messagesPage:  {!! json_encode(url('/messages')) !!},
    },
  };
</script>

@auth
  @include('partials.chat-dock')

  {{-- Soft bot scheduler heartbeat. Pings /tasks/run once shortly after page
       load, then every 60s. Server-side cache lock coalesces concurrent
       pings from multiple users so the scan runs at most once a minute. --}}
  <script>
    (function () {
      var url = {!! json_encode(url('/tasks/run')) !!};
      function ping() {
        try {
          fetch(url, { credentials: 'same-origin', cache: 'no-store', keepalive: true })
            .catch(function () {});
        } catch (e) {}
      }
      setTimeout(ping, 15000);
      setInterval(ping, 60000);
    })();
  </script>
@endauth

@stack('scripts')

</body>
</html>
