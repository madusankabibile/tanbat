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

    /* Header */
    .omr-head{border-bottom:1px solid var(--line);background:var(--card)}
    .omr-head .wrap{display:flex;align-items:center;justify-content:space-between;height:72px}
    .omr-brand{display:flex;align-items:center;gap:12px}
    .omr-logo{
      width:40px;height:40px;border-radius:9px;display:grid;place-items:center;
      background:var(--brand);color:#fff;font-family:Georgia,serif;font-weight:700;font-size:20px;
      letter-spacing:.5px;
    }
    .omr-brand b{font-size:22px;letter-spacing:.02em}
    .omr-brand span{display:block;font-size:11px;color:var(--muted);letter-spacing:.14em;text-transform:uppercase;font-family:system-ui,sans-serif}
    .omr-tagline{font-size:13px;color:var(--muted);font-family:system-ui,sans-serif}

    /* Footer */
    .omr-foot{border-top:1px solid var(--line);margin-top:56px;padding:28px 0;color:var(--muted);
      font-size:13px;font-family:system-ui,sans-serif;text-align:center}

    /* Sans UI accents */
    .u-sans{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}

    /* Card grid */
    .omr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:26px;margin-top:32px}
    .omr-card{background:var(--card);border:1px solid var(--line);border-radius:14px;overflow:hidden;
      display:flex;flex-direction:column;transition:transform .16s ease,box-shadow .16s ease}
    .omr-card:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(31,36,48,.10)}
    .omr-card-fig{aspect-ratio:16/9;background:var(--chip);overflow:hidden}
    .omr-card-fig img{width:100%;height:100%;object-fit:cover}
    .omr-card-noimg{width:100%;height:100%;display:grid;place-items:center;color:#c9c4b8;font-size:34px;font-family:Georgia,serif}
    .omr-card-body{padding:16px 18px 20px;display:flex;flex-direction:column;gap:10px;flex:1}
    .omr-cat{align-self:flex-start;font-family:system-ui,sans-serif;font-size:11px;font-weight:700;
      letter-spacing:.08em;text-transform:uppercase;color:var(--brand-ink);background:var(--chip);
      padding:4px 9px;border-radius:6px}
    .omr-card h2{font-size:20px;line-height:1.3;margin:0;font-weight:700}
    .omr-card:hover h2{color:var(--brand-ink)}
    .omr-card-meta{margin-top:auto;font-family:system-ui,sans-serif;font-size:12px;color:var(--muted)}

    .omr-page-title{font-size:34px;margin:36px 0 4px;font-weight:700;letter-spacing:-.01em}
    .omr-page-sub{color:var(--muted);font-family:system-ui,sans-serif;font-size:14px;margin:0}

    /* Pagination */
    .omr-pager{display:flex;justify-content:center;gap:12px;margin:44px 0 0;font-family:system-ui,sans-serif}
    .omr-pager a,.omr-pager span{padding:10px 18px;border:1px solid var(--line);border-radius:9px;
      background:var(--card);font-size:14px;font-weight:600}
    .omr-pager a:hover{border-color:var(--brand);color:var(--brand-ink)}
    .omr-pager .is-disabled{opacity:.4}

    /* Article layout */
    .omr-article{display:grid;grid-template-columns:minmax(0,1fr);gap:40px;margin-top:28px}
    @media(min-width:1000px){.omr-article{grid-template-columns:minmax(0,1fr) 320px}}
    .omr-main{min-width:0}
    .omr-eyebrow{font-family:system-ui,sans-serif;font-size:12px;font-weight:700;letter-spacing:.1em;
      text-transform:uppercase;color:var(--brand-ink)}
    .omr-title{font-size:40px;line-height:1.15;margin:12px 0 14px;font-weight:700;letter-spacing:-.015em}
    @media(max-width:640px){.omr-title{font-size:30px}}
    .omr-byline{font-family:system-ui,sans-serif;font-size:13px;color:var(--muted);
      display:flex;flex-wrap:wrap;gap:8px;align-items:center;border-bottom:1px solid var(--line);padding-bottom:18px}
    .omr-byline .dot{width:3px;height:3px;border-radius:50%;background:#cfcabd}
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
    @media(min-width:1000px){.omr-rail{position:sticky;top:20px;align-self:start}}
    .omr-rail-h{font-family:system-ui,sans-serif;font-size:12px;font-weight:700;letter-spacing:.1em;
      text-transform:uppercase;color:var(--muted);margin:0 0 4px;padding-bottom:8px;border-bottom:1px solid var(--line)}
    .omr-rel{display:flex;flex-direction:column;gap:16px}
    .omr-rel a{display:grid;grid-template-columns:76px 1fr;gap:12px;align-items:center}
    .omr-rel-fig{width:76px;height:56px;border-radius:8px;overflow:hidden;background:var(--chip)}
    .omr-rel-fig img{width:100%;height:100%;object-fit:cover}
    .omr-rel b{font-size:14.5px;font-weight:600;line-height:1.3;font-family:system-ui,sans-serif;color:var(--ink)}
    .omr-rel a:hover b{color:var(--brand-ink)}

    /* Ads */
    .omr-ad{display:flex;justify-content:center;align-items:center;overflow:hidden}
    .omr-ad-square{min-height:250px}
    .omr-ad-native{min-height:120px;width:100%}
    .omr-ad-native > div{width:100%}
    .omr-ad-wrap{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px}
    .omr-ad-label{font-family:system-ui,sans-serif;font-size:10px;letter-spacing:.12em;text-transform:uppercase;
      color:#b7b2a6;text-align:center;margin-bottom:8px}
    .omr-home-native{margin:26px 0 4px}

    .omr-back{display:inline-flex;align-items:center;gap:7px;font-family:system-ui,sans-serif;font-size:13px;
      color:var(--muted);margin-top:26px}
    .omr-back:hover{color:var(--brand-ink)}
  </style>
</head>
<body>
  <header class="omr-head">
    <div class="wrap">
      <a class="omr-brand" href="{{ \App\Support\Omrms::url('/') }}">
        <span class="omr-logo">O</span>
        <span>
          <b>OMRMS</b>
          <span>Articles &amp; Reads</span>
        </span>
      </a>
      <span class="omr-tagline">Fresh articles, every day</span>
    </div>
  </header>

  <main class="wrap">
    @yield('content')
  </main>

  <footer class="omr-foot">
    <div class="wrap">© {{ date('Y') }} OMRMS · All articles for reading. </div>
  </footer>
</body>
</html>
