<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'OMRMS')</title>

  {{-- Per-page SEO (canonical, robots, description, Open Graph, Twitter,
       JSON-LD) is hand-rolled per page and pushed here. --}}
  @stack('head')

  <link rel="icon" href="{{ \App\Support\Omrms::url('/favicon.ico') }}">
  <style>
    :root{
      --ink:#1f2430; --muted:#6b7280; --line:#e7e5df; --paper:#fbfaf7;
      --card:#ffffff; --brand:#b4441f; --brand-ink:#8a3417; --chip:#f2efe9;
    }
    *{box-sizing:border-box}
    html,body{margin:0;padding:0}
    body{
      background:var(--paper); color:var(--ink);
      font-family:Georgia,"Iowan Old Style","Times New Roman",serif;
      -webkit-font-smoothing:antialiased; line-height:1.6;
    }
    a{color:inherit;text-decoration:none}
    img{max-width:100%;display:block}
    .wrap{max-width:1200px;margin:0 auto;padding:0 20px}
    .u-sans{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}

    /* Header + nav */
    .omr-head{border-bottom:1px solid var(--line);background:var(--card);position:sticky;top:0;z-index:20}
    .omr-head-in{display:flex;align-items:center;justify-content:space-between;gap:20px;min-height:70px;flex-wrap:wrap}
    .omr-brand{display:flex;align-items:center;gap:12px}
    .omr-logo{
      width:40px;height:40px;border-radius:9px;display:grid;place-items:center;
      background:var(--brand);color:#fff;font-family:Georgia,serif;font-weight:700;font-size:20px;letter-spacing:.5px;
    }
    .omr-brand b{font-size:22px;letter-spacing:.02em;display:block}
    .omr-brand span small{display:block;font-size:11px;color:var(--muted);letter-spacing:.14em;text-transform:uppercase;font-family:system-ui,sans-serif}
    .omr-nav{display:flex;align-items:center;gap:6px;font-family:system-ui,sans-serif;font-size:14px;flex-wrap:wrap}
    .omr-nav a{padding:8px 14px;border-radius:8px;color:#3b4150;font-weight:600}
    .omr-nav a:hover{background:var(--chip);color:var(--brand-ink)}
    .omr-nav a.omr-nav-cta{background:var(--brand);color:#fff}
    .omr-nav a.omr-nav-cta:hover{background:var(--brand-ink);color:#fff}
    @media(max-width:640px){
      .omr-head-in{min-height:0;padding:12px 0}
      .omr-nav{width:100%;overflow-x:auto;flex-wrap:nowrap;-webkit-overflow-scrolling:touch}
      .omr-nav::-webkit-scrollbar{display:none}
    }

    /* Footer */
    .omr-foot{border-top:1px solid var(--line);margin-top:56px;padding:28px 0;color:var(--muted);
      font-size:13px;font-family:system-ui,sans-serif;text-align:center}
    .omr-foot a{color:var(--brand-ink)}

    /* Card grid */
    .omr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:26px;margin-top:32px}
    .omr-card{background:var(--card);border:1px solid var(--line);border-radius:14px;overflow:hidden;
      display:flex;flex-direction:column;transition:transform .16s ease,box-shadow .16s ease}
    .omr-card:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(31,36,48,.10)}
    .omr-card-fig{aspect-ratio:16/9;background:var(--chip);overflow:hidden;display:block}
    .omr-card-fig img{width:100%;height:100%;object-fit:cover}
    .omr-card-noimg{width:100%;height:100%;display:grid;place-items:center;color:#c9c4b8;font-size:34px;font-family:Georgia,serif}
    .omr-card-body{padding:16px 18px 20px;display:flex;flex-direction:column;gap:10px;flex:1}
    .omr-cat{align-self:flex-start;font-family:system-ui,sans-serif;font-size:11px;font-weight:700;
      letter-spacing:.08em;text-transform:uppercase;color:var(--brand-ink);background:var(--chip);
      padding:4px 9px;border-radius:6px}
    a.omr-cat:hover{background:#eadfd6}
    .omr-card h2{font-size:20px;line-height:1.3;margin:0;font-weight:700}
    .omr-card:hover h2{color:var(--brand-ink)}
    .omr-card-meta{margin-top:auto;font-family:system-ui,sans-serif;font-size:12px;color:var(--muted)}

    /* Native ad card (first grid cell) */
    .omr-card-ad{background:linear-gradient(180deg,#fff,#fbf6f2);border-color:#eadfd6}
    .omr-card-ad-tag{font-family:system-ui,sans-serif;font-size:10px;letter-spacing:.14em;text-transform:uppercase;
      color:#b7885f;padding:12px 16px 0}
    .omr-card-ad .omr-ad{padding:12px 16px 18px}

    .omr-page-title{font-size:34px;margin:36px 0 4px;font-weight:700;letter-spacing:-.01em}
    .omr-page-sub{color:var(--muted);font-family:system-ui,sans-serif;font-size:14px;margin:0}
    .omr-crumb{font-family:system-ui,sans-serif;font-size:13px;color:var(--muted);margin:26px 0 0}
    .omr-crumb a:hover{color:var(--brand-ink)}

    /* Pagination */
    .omr-pager{display:flex;justify-content:center;gap:12px;margin:44px 0 0;font-family:system-ui,sans-serif}
    .omr-pager a,.omr-pager span{padding:10px 18px;border:1px solid var(--line);border-radius:9px;
      background:var(--card);font-size:14px;font-weight:600}
    .omr-pager a:hover{border-color:var(--brand);color:var(--brand-ink)}
    .omr-pager .is-disabled{opacity:.4}

    /* Categories index */
    .omr-cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px;margin-top:30px}
    .omr-cat-card{display:flex;align-items:center;justify-content:space-between;gap:12px;
      background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px 20px;
      transition:transform .15s,box-shadow .15s,border-color .15s}
    .omr-cat-card:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(31,36,48,.08);border-color:#e0d7c9}
    .omr-cat-card b{font-size:18px;font-weight:700}
    .omr-cat-card .omr-cat-count{font-family:system-ui,sans-serif;font-size:12px;font-weight:700;color:var(--brand-ink);
      background:var(--chip);border-radius:20px;padding:4px 11px}

    /* Article layout */
    .omr-article{display:grid;grid-template-columns:minmax(0,1fr);gap:40px;margin-top:22px}
    @media(min-width:1000px){.omr-article{grid-template-columns:minmax(0,1fr) 320px}}
    .omr-main{min-width:0}
    .omr-eyebrow{font-family:system-ui,sans-serif;font-size:12px;font-weight:700;letter-spacing:.1em;
      text-transform:uppercase;color:var(--brand-ink)}
    .omr-eyebrow:hover{text-decoration:underline}
    .omr-title{font-size:40px;line-height:1.15;margin:12px 0 16px;font-weight:700;letter-spacing:-.015em}
    @media(max-width:640px){.omr-title{font-size:30px}}
    .omr-byline{font-family:system-ui,sans-serif;font-size:13.5px;color:var(--muted);
      display:flex;flex-wrap:wrap;gap:10px;align-items:center;border-bottom:1px solid var(--line);padding-bottom:18px}
    .omr-byline .dot{width:3px;height:3px;border-radius:50%;background:#cfcabd}
    .omr-author{display:inline-flex;align-items:center;gap:9px}
    .omr-avatar{width:34px;height:34px;border-radius:50%;object-fit:cover;background:var(--chip)}
    .omr-avatar-ph{display:grid;place-items:center;color:#fff;background:var(--brand);font-family:Georgia,serif;font-weight:700;font-size:16px}
    .omr-author a{color:var(--ink);font-weight:700}
    .omr-author a:hover{color:var(--brand-ink);text-decoration:underline}
    .omr-hero{margin:24px 0;border-radius:14px;overflow:hidden;background:var(--chip)}
    .omr-hero img{width:100%;height:auto}

    /* Prose */
    .omr-prose{font-size:18.5px;line-height:1.8;color:#262c38}
    .omr-prose p{margin:1.05em 0}
    .omr-prose h2{font-size:27px;margin:1.6em 0 .5em;font-weight:700;letter-spacing:-.01em;line-height:1.25}
    .omr-prose h3{font-size:22px;margin:1.4em 0 .5em;font-weight:700}
    .omr-prose a{color:var(--brand-ink);text-decoration:underline;text-underline-offset:3px}
    .omr-prose img{border-radius:12px;margin:1.5em auto;box-shadow:0 6px 22px rgba(31,36,48,.10)}
    .omr-prose ul,.omr-prose ol{padding-left:1.4em;margin:1em 0}
    .omr-prose li{margin:.4em 0}
    .omr-prose blockquote{border-left:4px solid var(--brand);background:#fbf1ec;border-radius:8px;
      padding:12px 20px;margin:1.5em 0;color:var(--brand-ink);font-style:italic}
    .omr-prose pre{background:#1b2130;color:#e6e9f0;padding:16px;border-radius:12px;overflow:auto;
      font-family:ui-monospace,Menlo,monospace;font-size:14px}
    .omr-prose code{font-family:ui-monospace,Menlo,monospace;font-size:.92em}
    .omr-prose figure{margin:1.5em 0}

    /* Sidebar / rail */
    .omr-rail{display:flex;flex-direction:column;gap:26px}
    @media(min-width:1000px){.omr-rail{position:sticky;top:90px;align-self:start}}
    .omr-rail-h{font-family:system-ui,sans-serif;font-size:12px;font-weight:700;letter-spacing:.1em;
      text-transform:uppercase;color:var(--muted);margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid var(--line)}
    .omr-rel{display:flex;flex-direction:column;gap:16px}
    .omr-rel a{display:grid;grid-template-columns:76px 1fr;gap:12px;align-items:center}
    .omr-rel-fig{width:76px;height:56px;border-radius:8px;overflow:hidden;background:var(--chip)}
    .omr-rel-fig img{width:100%;height:100%;object-fit:cover}
    .omr-rel b{font-size:14.5px;font-weight:600;line-height:1.3;font-family:system-ui,sans-serif;color:var(--ink)}
    .omr-rel a:hover b{color:var(--brand-ink)}

    /* Statistics card */
    .omr-stats{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px 18px}
    .omr-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .omr-stat{background:var(--chip);border-radius:10px;padding:12px 12px;font-family:system-ui,sans-serif}
    .omr-stat b{display:block;font-size:19px;font-weight:800;color:var(--ink);line-height:1.1}
    .omr-stat span{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}

    /* Ads */
    .omr-ad{display:flex;justify-content:center;align-items:center;overflow:hidden}
    .omr-ad-square{min-height:250px}
    .omr-ad-native{min-height:100px;width:100%}
    .omr-ad-native > div{width:100%}
    .omr-ad-wrap{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px}
    .omr-ad-label{font-family:system-ui,sans-serif;font-size:10px;letter-spacing:.12em;text-transform:uppercase;
      color:#b7b2a6;text-align:center;margin-bottom:8px}
    .omr-midad{margin:30px 0;padding:14px;border:1px dashed #e2d8ca;border-radius:12px;background:#fffdf9}

    /* Mobile-only helper (mid-article ad) */
    .omr-mobile-only{display:block}
    @media(min-width:1000px){.omr-mobile-only{display:none}}

    .omr-back{display:inline-flex;align-items:center;gap:7px;font-family:system-ui,sans-serif;font-size:13px;
      color:var(--muted);margin-top:26px}
    .omr-back:hover{color:var(--brand-ink)}

    /* Social share panel */
    .omr-share{margin-top:36px;padding-top:22px;border-top:1px solid var(--line);
      display:flex;flex-wrap:wrap;align-items:center;gap:14px}
    .omr-share-label{font-family:system-ui,sans-serif;font-size:13px;font-weight:700;letter-spacing:.06em;
      text-transform:uppercase;color:var(--muted)}
    .omr-share-btns{display:flex;flex-wrap:wrap;gap:10px}
    .omr-sh{position:relative;width:42px;height:42px;border-radius:11px;display:grid;place-items:center;
      color:#fff;border:0;cursor:pointer;transition:transform .14s ease,box-shadow .14s ease,filter .14s ease}
    .omr-sh svg{width:20px;height:20px}
    .omr-sh:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(31,36,48,.20);filter:brightness(1.05)}
    .sh-fb{background:#1877f2}
    .sh-x{background:#000}
    .sh-wa{background:#25d366}
    .sh-tg{background:#26a5e4}
    .sh-in{background:#0a66c2}
    .sh-pin{background:#e60023}
    .sh-copy{background:var(--brand)}
    .sh-copy .ic-ok{display:none}
    .sh-copy.is-copied{background:#1a9d5a}
    .sh-copy.is-copied .ic-link{display:none}
    .sh-copy.is-copied .ic-ok{display:block}
    .sh-copy-tip{position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%) translateY(4px);
      background:#1f2430;color:#fff;font-family:system-ui,sans-serif;font-size:11px;font-weight:600;
      padding:4px 9px;border-radius:6px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .14s,transform .14s}
    .sh-copy.is-copied .sh-copy-tip{opacity:1;transform:translateX(-50%) translateY(0)}

    /* Simple content page (publish guide) */
    .omr-doc{max-width:760px;margin:12px auto 0}
    .omr-doc h1{font-size:38px;font-weight:700;letter-spacing:-.015em;margin:34px 0 10px}
    .omr-doc .lead{font-size:19px;color:#3b4150;line-height:1.7}
    .omr-step{display:grid;grid-template-columns:44px 1fr;gap:16px;align-items:start;margin:22px 0;
      background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px 20px}
    .omr-step .n{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;background:var(--brand);
      color:#fff;font-weight:700;font-size:18px}
    .omr-step h3{margin:0 0 4px;font-size:19px;font-weight:700}
    .omr-step p{margin:0;color:#414755;font-size:16px;line-height:1.65}
    .omr-cta{display:inline-flex;align-items:center;gap:8px;background:var(--brand);color:#fff;font-family:system-ui,sans-serif;
      font-weight:700;font-size:15px;padding:13px 22px;border-radius:10px;margin-top:8px}
    .omr-cta:hover{background:var(--brand-ink)}
    .omr-doc a.inline{color:var(--brand-ink);text-decoration:underline}
  </style>
</head>
<body>
  @php $omr = \App\Support\Omrms::class; @endphp
  <header class="omr-head">
    <div class="wrap omr-head-in">
      <a class="omr-brand" href="{{ $omr::url('/') }}">
        <span class="omr-logo">O</span>
        <span><b>OMRMS</b><small>Articles &amp; Reads</small></span>
      </a>
      <nav class="omr-nav">
        <a href="{{ $omr::url('/') }}">Home</a>
        <a href="{{ $omr::url('/categories') }}">Categories</a>
        <a class="omr-nav-cta" href="{{ $omr::url('/how-to-publish') }}">Publish an article</a>
      </nav>
    </div>
  </header>

  <main class="wrap">
    @yield('content')
  </main>

  <footer class="omr-foot">
    <div class="wrap">
      © {{ date('Y') }} OMRMS · <a href="{{ $omr::url('/categories') }}">Categories</a> ·
      <a href="{{ $omr::url('/how-to-publish') }}">Publish an article</a>
    </div>
  </footer>
</body>
</html>
