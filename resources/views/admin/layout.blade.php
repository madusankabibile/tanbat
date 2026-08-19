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
     CDN can't gate first paint; app.css below stays render-blocking via @vite.
     IBM Plex Mono carries every number and micro-label in the panel. --}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap"></noscript>
@vite(['resources/css/app.css'])
<style>
  /* ══════════════════════════════════════════════════════════════
     Tanbat Admin — "Ledger"
     Archival-gray canvas, white panels, hairline rules. Every number
     is set in mono with tabular figures so columns align down the page.
     Pine is the only accent; brass is the second data hue.

     NOTE: this block is plain CSS, not run through Tailwind/PostCSS, so it
     must not use @apply — those directives are silently dropped by the
     browser. Everything below is authored as literal CSS on purpose.
     ══════════════════════════════════════════════════════════════ */
  :root {
    --paper:      #EFF0F2;  /* app canvas                     */
    --surface:    #FFFFFF;  /* panels, sidebar, table body    */
    --sunken:     #F7F8F9;  /* table heads, inert wells       */

    /* Ink scale. Every step that carries text clears 4.5:1 on white:
       18.1 / 10.1 / 6.3 / 4.7. --faint is below that on purpose and is
       therefore reserved for non-text marks: icons at rest, gridlines,
       disabled controls. */
    --ink:        #14161A;  /* headings, values               */
    --ink-2:      #3D424B;  /* body copy, table cells         */
    --ink-3:      #5B6070;  /* secondary, captions            */
    --ink-4:      #6E7480;  /* micro-labels, axis ticks       */
    --faint:      #9AA0AA;  /* icons, gridlines, disabled     */

    --rule:       #E2E4E8;  /* panel + table outer borders    */
    --rule-soft:  #EDEEF1;  /* inner dividers                 */

    --pine:       #07775A;  /* primary accent / series 1      */
    --pine-d:     #05604A;  /* accent text on white           */
    --pine-tint:  #E7F2EE;
    --brass:      #B45309;  /* series 2                       */
    --brass-tint: #FBF1E5;
    --blue:       #175CD3;  /* series 3                       */
    --blue-tint:  #EDF2FC;
    --plum:       #9A3F8F;  /* series 4                       */
    --rose:       #B42318;  /* destructive / negative trend   */
    --rose-tint:  #FCEFEE;

    --mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
    --r:    8px;   /* panels  */
    --r-sm: 6px;   /* controls */

    /* Legacy aliases kept so nothing that still references them goes unstyled. */
    --ad-line:      var(--rule);
    --ad-line-soft: var(--rule-soft);
    --ad-muted:     var(--ink-3);
    --ad-brand:     var(--pine);
    --ad-brand-d:   var(--pine-d);
  }

  body.admin-body {
    background: var(--paper);
    color: var(--ink-2);
  }

  /* Numerals line up in columns, everywhere. */
  .num, .kpi-value, .table-card td.text-right, .pager a, .pager span {
    font-family: var(--mono);
    font-variant-numeric: tabular-nums;
  }
  /* …but a right-aligned cell is also where the Actions column lives, and
     controls are never mono. Opt them back out. */
  .table-card td.text-right .btn-xs,
  .table-card td.text-right .row-menu,
  .table-card td.text-right button,
  .table-card td.text-right a { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }

  /* Shared micro-label: mono, uppercase, wide tracking. The eyebrow of the
     whole panel — used by nav sections, table heads, KPI labels, form labels. */
  .micro {
    font-family: var(--mono);
    font-size: .625rem;
    font-weight: 500;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--ink-3);
  }

  /* ── Shell ───────────────────────────────────────────── */
  .admin-shell { display: grid; grid-template-columns: 252px 1fr; min-height: 100vh; }

  .admin-side {
    background: var(--surface);
    border-right: 1px solid var(--rule);
    position: sticky; top: 0; height: 100vh; overflow-y: auto;
    display: flex; flex-direction: column;
  }
  .admin-side::-webkit-scrollbar { width: 6px; }
  .admin-side::-webkit-scrollbar-thumb { background: #DDE0E4; border-radius: 3px; }

  .admin-brand {
    display: flex; align-items: center; gap: .625rem;
    padding: 1.15rem 1.25rem;
    border-bottom: 1px solid var(--rule-soft);
  }
  .admin-brand__mark {
    display: grid; place-items: center;
    width: 2rem; height: 2rem; flex: 0 0 auto;
    border-radius: var(--r-sm);
    background: var(--ink); color: #fff;
    font-family: var(--mono); font-weight: 600; font-size: .95rem;
  }
  .admin-brand__name { font-size: .9375rem; font-weight: 600; color: var(--ink); letter-spacing: -.01em; line-height: 1.1; }
  .admin-brand__kind {
    display: block; margin-top: .15rem;
    font-family: var(--mono); font-size: .5625rem; font-weight: 500;
    letter-spacing: .16em; text-transform: uppercase; color: var(--ink-4);
  }

  .admin-side nav { padding: .5rem .625rem 1.5rem; }
  .nav-section {
    font-family: var(--mono);
    font-size: .5625rem; font-weight: 500;
    letter-spacing: .16em; text-transform: uppercase;
    color: var(--ink-4);
    padding: 1.25rem .625rem .375rem;
  }
  .admin-side .nav-section:first-child { padding-top: .75rem; }

  .admin-side a.nav-link,
  .admin-side button.nav-link {
    display: flex; align-items: center; gap: .6875rem;
    padding: .5rem .625rem; border-radius: var(--r-sm);
    color: var(--ink-2); font-size: .8125rem; font-weight: 500;
    background: none; border: 0; cursor: pointer;
    transition: background .12s ease, color .12s ease;
  }
  .admin-side .nav-link svg { flex: 0 0 auto; color: var(--faint); transition: color .12s ease; }
  .admin-side .nav-link:hover { background: #F4F5F6; color: var(--ink); }
  .admin-side .nav-link:hover svg { color: var(--ink-3); }
  .admin-side .nav-link.active {
    background: var(--pine-tint);
    color: var(--pine-d); font-weight: 600;
    box-shadow: inset 3px 0 0 var(--pine);
  }
  .admin-side .nav-link.active svg { color: var(--pine); }
  .admin-side .nav-link:focus-visible { outline: 2px solid var(--pine); outline-offset: 1px; }

  /* ── Top bar ─────────────────────────────────────────── */
  .admin-top {
    position: sticky; top: 0; z-index: 30;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    padding: .8125rem 1.5rem;
    background: rgba(255,255,255,.88);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--rule);
  }
  .admin-top__crumb { font-family: var(--mono); font-size: .5625rem; font-weight: 500; letter-spacing: .16em; text-transform: uppercase; color: var(--ink-4); }
  .admin-top__title { margin-top: .1875rem; font-size: 1.125rem; font-weight: 600; letter-spacing: -.02em; color: var(--ink); line-height: 1.15; }

  .admin-user { display: flex; align-items: center; gap: .625rem; }
  .admin-user__name { display: block; font-size: .8125rem; font-weight: 600; color: var(--ink); line-height: 1.2; }
  .admin-user__handle { display: block; font-family: var(--mono); font-size: .6875rem; color: var(--ink-4); }

  .admin-burger {
    display: none; place-items: center;
    width: 2.25rem; height: 2.25rem; flex: 0 0 auto;
    border: 1px solid var(--rule); border-radius: var(--r-sm);
    background: var(--surface); color: var(--ink-2); cursor: pointer;
  }
  .admin-burger:hover { border-color: var(--ink-4); }
  .admin-scrim { display: none; }

  @media (max-width: 1023px) {
    .admin-shell { grid-template-columns: 1fr; }
    .admin-side {
      position: fixed; inset: 0 auto 0 0; z-index: 60; width: 252px;
      transform: translateX(-100%); transition: transform .2s ease;
      box-shadow: 0 0 0 1px var(--rule);
    }
    .admin-side.is-open { transform: translateX(0); }
    .admin-burger { display: grid; }
    .admin-scrim {
      display: block; position: fixed; inset: 0; z-index: 50;
      background: rgba(20,22,26,.4);
      opacity: 0; pointer-events: none; transition: opacity .2s ease;
    }
    .admin-scrim.is-open { opacity: 1; pointer-events: auto; }
    .admin-top { padding-left: 1rem; padding-right: 1rem; }
  }
  @media (prefers-reduced-motion: reduce) {
    .admin-side, .admin-scrim, .row-menu__panel { transition: none; }
  }

  /* ── Panels (also re-skins the shared .card used by other admin pages) ── */
  .panel,
  .admin-shell .card {
    background: var(--surface);
    border: 1px solid var(--rule);
    border-radius: var(--r);
    box-shadow: none;
  }
  .panel__head {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
    padding: .9375rem 1.125rem;
    border-bottom: 1px solid var(--rule-soft);
  }
  .panel__title { font-size: .875rem; font-weight: 600; color: var(--ink); letter-spacing: -.01em; }
  .panel__sub   { margin-top: .1875rem; font-size: .75rem; color: var(--ink-3); }
  .panel__link {
    flex: 0 0 auto;
    font-family: var(--mono); font-size: .625rem; font-weight: 500;
    letter-spacing: .1em; text-transform: uppercase; color: var(--pine-d);
    border-bottom: 1px solid transparent; padding-bottom: 1px;
    transition: border-color .12s ease;
  }
  .panel__link:hover { border-bottom-color: var(--pine); }
  .panel__body { padding: 1.125rem; }
  .panel__body--flush { padding: .375rem 0; }

  /* ── KPI ledger strip ────────────────────────────────
     One panel divided by hairlines rather than five floating cards.
     Cell rules are drawn per-cell and suppressed on the first row /
     first column at each breakpoint, so the outer border stays single. */
  .strip {
    display: grid; grid-template-columns: repeat(2, 1fr);
    background: var(--surface);
    border: 1px solid var(--rule); border-radius: var(--r);
    overflow: hidden;
  }
  @media (min-width: 768px)  { .strip { grid-template-columns: repeat(3, 1fr); } }
  @media (min-width: 1280px) { .strip { grid-template-columns: repeat(5, 1fr); } }

  .kpi {
    padding: 1.0625rem 1.125rem 1.125rem;
    border-top: 1px solid var(--rule-soft);
    border-left: 1px solid var(--rule-soft);
  }
  @media (max-width: 767px) {
    .kpi:nth-child(-n+2) { border-top: 0; }
    .kpi:nth-child(2n+1) { border-left: 0; }
  }
  @media (min-width: 768px) and (max-width: 1279px) {
    .kpi:nth-child(-n+3) { border-top: 0; }
    .kpi:nth-child(3n+1) { border-left: 0; }
  }
  @media (min-width: 1280px) {
    .kpi:nth-child(-n+5) { border-top: 0; }
    .kpi:nth-child(5n+1) { border-left: 0; }
  }

  .kpi-label {
    font-family: var(--mono); font-size: .625rem; font-weight: 500;
    letter-spacing: .12em; text-transform: uppercase; color: var(--ink-3);
  }
  .kpi-value {
    margin-top: .625rem;
    font-size: 1.75rem; font-weight: 600; line-height: 1;
    letter-spacing: -.03em; color: var(--ink);
  }
  .kpi-foot {
    margin-top: .5rem; display: flex; align-items: center; gap: .375rem;
    font-size: .6875rem; color: var(--ink-3);
  }

  .trend {
    display: inline-flex; align-items: center; gap: .1875rem;
    font-family: var(--mono); font-size: .6875rem; font-weight: 600;
    font-variant-numeric: tabular-nums;
  }
  .trend svg { width: .5rem; height: .5rem; }
  .trend--up   { color: var(--pine-d); }
  .trend--down { color: var(--rose); }
  .trend--flat { color: var(--ink-4); }

  /* ── Badges ──────────────────────────────────────────── */
  .badge {
    display: inline-flex; align-items: center; gap: .25rem;
    border-radius: 4px; padding: .1875rem .375rem;
    font-family: var(--mono); font-size: .625rem; font-weight: 500;
    letter-spacing: .06em; text-transform: uppercase; line-height: 1.3;
  }
  .badge-admin   { background: var(--pine-tint);  color: var(--pine-d); }
  .badge-user    { background: #F1F2F4;           color: var(--ink-3); }
  .badge-on      { background: var(--pine-tint);  color: var(--pine-d); }
  .badge-off     { background: #F1F2F4;           color: var(--ink-3); }
  .badge-live    { background: var(--pine-tint);  color: var(--pine-d); }
  .badge-expired { background: var(--rose-tint);  color: var(--rose); }
  .badge-type    { background: var(--blue-tint);  color: var(--blue); }

  /* ── Country flags (admin/partials/_flag) ────────────── */
  .flag-img,
  .flag-unknown {
    display: inline-block; flex: 0 0 auto;
    width: 20px; height: 15px;
    border-radius: 2px;
    vertical-align: -2px;
  }
  .flag-img {
    object-fit: cover;
    /* A hairline edge so white-heavy flags (Japan, Poland) keep their shape on
       a white panel. `outline` with a negative offset paints over the image;
       an inset box-shadow would sit beneath it on a replaced element. */
    outline: 1px solid rgba(20, 22, 26, .12);
    outline-offset: -1px;
  }
  /* No flag for this row: hold the same 20px of space so names stay aligned. */
  .flag-unknown {
    background: #F4F5F6;
    border: 1px dashed var(--faint);
  }

  /* ── Tables ──────────────────────────────────────────── */
  .table-card {
    /* Scrolls sideways rather than clipping when a wide table can't fit its
       container (e.g. 9-column tables on a tablet). Below 768px the table
       restacks into cards instead — see the responsive block further down. */
    overflow-x: auto; -webkit-overflow-scrolling: touch;
    border-radius: var(--r); background: var(--surface);
    border: 1px solid var(--rule); box-shadow: none;
  }
  .table-card table { width: 100%; border-collapse: separate; border-spacing: 0; }
  .table-card th {
    background: var(--sunken); padding: .75rem 1.125rem; text-align: left;
    font-family: var(--mono); font-size: .625rem; font-weight: 500;
    letter-spacing: .12em; text-transform: uppercase; color: var(--ink-3);
    white-space: nowrap; border-bottom: 1px solid var(--rule);
  }
  .table-card td {
    padding: .9375rem 1.125rem; font-size: .8125rem; color: var(--ink-2);
    vertical-align: middle; border-top: 1px solid var(--rule-soft);
  }
  .table-card tbody tr:first-child td { border-top: none; }
  .table-card tbody tr { transition: background .1s ease; }
  .table-card tbody tr:hover td { background: #FAFBFB; }
  .table-card th:first-child, .table-card td:first-child { padding-left: 1.25rem; }
  .table-card th:last-child,  .table-card td:last-child  { padding-right: 1.25rem; }
  .table-card th.text-right, .table-card td.text-right { text-align: right; }
  .table-card td .inline-flex { white-space: nowrap; }

  /* ── Responsive tables: restack into labelled cards on phones ──────
     Below 768px a row can't stay a row without either clipping or a
     cramped sideways scroll, so each <tr> becomes a self-contained card.
     The column head is dropped in beside every value from the cell's
     `data-label`, so nothing is hidden and the row actions stay reachable.
     The first cell of a row is the card's identity block (avatar + name,
     post title, IP…) — it carries no data-label and sits full width at the
     top. The actions cell is tagged `.cell-actions` and forms the footer. */
  @media (max-width: 767px) {
    .table-card {
      overflow: visible; border: 0; background: transparent; border-radius: 0;
    }
    .table-card table,
    .table-card tbody,
    .table-card tr,
    .table-card td { display: block; width: auto; }
    /* Column heads are folded into each cell's label, so hide the real
       <thead> — but keep it in the accessibility tree (visually-hidden). */
    .table-card thead {
      position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
      overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
    }
    .table-card tbody tr {
      background: var(--surface);
      border: 1px solid var(--rule); border-radius: var(--r);
      padding: .375rem 1rem; margin-bottom: .75rem;
    }
    .table-card tbody tr:hover td { background: transparent; }
    .table-card td {
      display: flex; align-items: center; justify-content: space-between;
      gap: 1rem; max-width: none; text-align: right; white-space: normal;
      padding: .5625rem 0 !important;
      border-top: 1px solid var(--rule-soft) !important;
    }
    .table-card td::before {
      content: attr(data-label); flex: 0 0 auto;
      font-family: var(--mono); font-size: .625rem; font-weight: 500;
      letter-spacing: .12em; text-transform: uppercase; color: var(--ink-3);
      text-align: left;
    }
    /* Identity cell: no label, left-aligned, opens the card. */
    .table-card td:first-child {
      border-top: 0 !important;
      padding-top: .625rem !important; padding-bottom: .625rem !important;
      text-align: left;
    }
    .table-card td:first-child::before { display: none; }
    /* Actions cell: a full-width footer; controls flow left-to-right, wrapping. */
    .table-card td.cell-actions { padding-top: .625rem !important; }
    .table-card td.cell-actions::before { display: none; }
    .table-card td.cell-actions > .inline-flex { flex-wrap: wrap; }
  }

  /* ── Filter bars stack on phones ──────────────────────
     The GET filter forms are `flex flex-wrap`; under 640px every field
     (and its select/input) takes the full width so nothing is squeezed. */
  @media (max-width: 639px) {
    .admin-shell form.flex.flex-wrap > div { flex: 1 1 100%; min-width: 0; }
    .admin-shell form.flex.flex-wrap .input { width: 100%; }
  }

  /* ── Forms (re-skins the shared .input / .label) ────── */
  .admin-shell .label {
    display: block; margin-bottom: .375rem;
    font-family: var(--mono); font-size: .625rem; font-weight: 500;
    letter-spacing: .12em; text-transform: uppercase; color: var(--ink-3);
  }
  .admin-shell .input {
    display: block; width: 100%;
    border: 1px solid var(--rule); border-radius: var(--r-sm);
    background: var(--surface);
    padding: .5625rem .75rem;
    font-size: .8125rem; color: var(--ink);
    box-shadow: none;
    transition: border-color .12s ease, box-shadow .12s ease;
  }
  .admin-shell .input::placeholder { color: var(--ink-4); }
  .admin-shell .input:hover { border-color: #D3D6DB; }
  .admin-shell .input:focus {
    outline: none;
    border-color: var(--pine);
    box-shadow: 0 0 0 3px rgba(7,119,90,.12);
  }

  /* ── Buttons (re-skin the shared .btn-* used across admin) ── */
  .admin-shell .btn-primary,
  .admin-shell .btn-outline,
  .admin-shell .btn-ghost {
    display: inline-flex; align-items: center; justify-content: center; gap: .375rem;
    border-radius: var(--r-sm);
    padding: .5625rem .875rem;
    font-size: .8125rem; font-weight: 600;
    line-height: 1.25; white-space: nowrap;
    box-shadow: none; transform: none;
    transition: background .12s ease, border-color .12s ease, color .12s ease;
  }
  .admin-shell .btn-primary {
    background: var(--pine); border: 1px solid var(--pine); color: #fff;
  }
  .admin-shell .btn-primary:hover { background: var(--pine-d); border-color: var(--pine-d); color: #fff; box-shadow: none; transform: none; }
  .admin-shell .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
  .admin-shell .btn-outline {
    background: var(--surface); border: 1px solid var(--rule); color: var(--ink-2);
  }
  .admin-shell .btn-outline:hover { border-color: var(--ink-4); color: var(--ink); background: var(--surface); }
  .admin-shell .btn-ghost { background: transparent; border: 1px solid transparent; color: var(--ink-3); }
  .admin-shell .btn-ghost:hover { background: #F4F5F6; color: var(--ink); }
  .admin-shell .btn-primary:focus-visible,
  .admin-shell .btn-outline:focus-visible,
  .admin-shell .btn-ghost:focus-visible { outline: 2px solid var(--pine); outline-offset: 2px; }

  /* ── Compact row-action buttons ─────────────────────── */
  .btn-xs {
    display: inline-flex; align-items: center; justify-content: center; gap: .3rem;
    border: 1px solid var(--rule); background: var(--surface); color: var(--ink-2);
    border-radius: var(--r-sm); padding: .375rem .625rem;
    font-size: .75rem; font-weight: 600;
    line-height: 1.25; white-space: nowrap; cursor: pointer;
    transition: border-color .12s ease, color .12s ease, background .12s ease;
  }
  .btn-xs:hover { border-color: var(--ink-4); color: var(--ink); background: var(--surface); }
  .btn-xs:disabled { opacity: .45; cursor: not-allowed; }
  .btn-xs:focus-visible { outline: 2px solid var(--pine); outline-offset: 1px; }
  .btn-xs-primary { border-color: var(--pine); background: var(--pine); color: #fff; }
  .btn-xs-primary:hover { border-color: var(--pine-d); background: var(--pine-d); color: #fff; }
  .btn-xs-danger { color: var(--rose); border-color: #F1D6D3; }
  .btn-xs-danger:hover { color: var(--rose); background: var(--rose-tint); border-color: #E3B4AF; }
  .btn-xs-warn { color: var(--brass); border-color: #EBD9BF; }
  .btn-xs-warn:hover { color: var(--brass); background: var(--brass-tint); border-color: #DCC09B; }

  /* ── Pagination ──────────────────────────────────────── */
  .pager { display: flex; align-items: center; gap: .25rem; flex-wrap: wrap; }
  .pager a, .pager span {
    display: inline-flex; align-items: center; justify-content: center;
    height: 2rem; min-width: 2rem; padding: 0 .5rem; border-radius: var(--r-sm);
    font-size: .75rem; font-weight: 500;
  }
  .pager a { background: var(--surface); border: 1px solid var(--rule); color: var(--ink-2); transition: border-color .12s ease, color .12s ease; }
  .pager a:hover { border-color: var(--ink-4); color: var(--ink); }
  .pager span[aria-current] > span,
  .pager span.active { background: var(--ink); color: #fff; border-radius: var(--r-sm); }
  .pager span.disabled, .pager [aria-disabled] { color: #C6CAD0; }
  .pager svg { width: 1rem; height: 1rem; }

  /* ── Misc ────────────────────────────────────────────── */
  .avatar-circle {
    width: 34px; height: 34px; border-radius: 9999px; object-fit: cover;
    background: #E7EAEE; display: inline-grid; place-items: center;
    font-family: var(--mono); font-weight: 500; color: var(--ink-2); font-size: .8125rem;
    flex: 0 0 auto;
  }
  .flash {
    margin-bottom: 1.25rem; display: flex; align-items: center; gap: .625rem;
    border-radius: var(--r-sm);
    border: 1px solid #CBE2DA; border-left: 3px solid var(--pine);
    background: var(--pine-tint);
    padding: .75rem 1rem; font-size: .8125rem; font-weight: 500; color: var(--pine-d);
  }
  .field-error { margin-top: .3125rem; font-size: .75rem; font-weight: 500; color: var(--rose); }

  /* ── Row action menu (kebab dropdown) ────────────────────
     The panel is position:fixed and JS-positioned so it escapes the
     .table-card { overflow:hidden } clip. Selectors stay unscoped because
     the panel is reparented to <body> while open. */
  .row-menu { display: inline-block; }
  .row-menu__btn {
    display: inline-grid; place-items: center; width: 2rem; height: 2rem;
    border-radius: var(--r-sm); border: 1px solid var(--rule); background: var(--surface);
    color: var(--ink-3); cursor: pointer; transition: border-color .12s ease, color .12s ease;
  }
  .row-menu__btn:hover,
  .row-menu__btn[aria-expanded="true"] { border-color: var(--ink-4); color: var(--ink); }
  .row-menu__panel {
    position: fixed; z-index: 80; min-width: 11rem; padding: .3125rem;
    background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r);
    box-shadow: 0 12px 28px rgba(20,22,26,.10), 0 1px 3px rgba(20,22,26,.06);
    opacity: 0; transform: translateY(-4px); pointer-events: none;
    transition: opacity .12s ease, transform .12s ease;
  }
  .row-menu__panel.is-open { opacity: 1; transform: translateY(0); pointer-events: auto; }
  .row-menu__panel form { display: block; margin: 0; }
  .row-menu__item {
    display: flex; align-items: center; gap: .5rem; width: 100%;
    padding: .4375rem .5625rem; border: 0; border-radius: 5px; background: transparent;
    font-size: .8125rem; font-weight: 500; color: var(--ink-2); text-align: left; cursor: pointer;
    transition: background .1s ease, color .1s ease;
  }
  .row-menu__item svg { width: 1rem; height: 1rem; flex: 0 0 auto; color: var(--faint); }
  .row-menu__item:hover { background: #F4F5F6; color: var(--ink); }
  .row-menu__item:hover svg { color: var(--ink-3); }
  .row-menu__item:disabled { opacity: .45; cursor: not-allowed; color: var(--faint); }
  .row-menu__item:disabled:hover { background: transparent; color: var(--faint); }
  .row-menu__item--danger { color: var(--rose); }
  .row-menu__item--danger svg { color: var(--rose); }
  .row-menu__item--danger:hover { background: var(--rose-tint); color: var(--rose); }
  .row-menu__divider { height: 1px; margin: .25rem; background: var(--rule-soft); }
</style>
@stack('head')
</head>
<body class="admin-body">

@php
  $u = auth()->user();
  $initials = $u ? strtoupper(mb_substr($u->name, 0, 1)) : 'A';
@endphp

<div class="admin-shell">

  {{-- ─────────── SIDEBAR ─────────── --}}
  <aside class="admin-side" id="adminSide">
    <div class="admin-brand">
      <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
        <span class="admin-brand__mark">T</span>
        <span>
          <span class="admin-brand__name">Tanbat</span>
          <span class="admin-brand__kind">Console</span>
        </span>
      </a>
    </div>

    <nav>
      <div class="nav-section">Overview</div>
      <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        Dashboard
      </a>
      <a href="{{ route('admin.statistics.index') }}" class="nav-link {{ request()->routeIs('admin.statistics.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 15l3.5-4 3 2.5L20 7"/></svg>
        Statistics
      </a>

      <div class="nav-section">Manage</div>
      <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Users
      </a>
      <a href="{{ route('admin.posts.index') }}" class="nav-link {{ request()->routeIs('admin.posts.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
        Posts
      </a>
      <a href="{{ route('admin.books.index') }}" class="nav-link {{ request()->routeIs('admin.books.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        Books
      </a>
      <a href="{{ route('admin.tv.index') }}" class="nav-link {{ request()->routeIs('admin.tv.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
        TV Channels
      </a>
      <a href="{{ route('admin.categories.index') }}" class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
        Categories
      </a>
      <a href="{{ route('admin.ads.index') }}" class="nav-link {{ request()->routeIs('admin.ads.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-8v18l-18-8z"/><path d="M11.6 12.5a3 3 0 1 0-5.2 3"/></svg>
        Advertisements
      </a>

      <div class="nav-section">Integrations</div>
      <a href="{{ route('admin.book-search.index') }}" class="nav-link {{ request()->routeIs('admin.book-search.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Book Search Engine
      </a>
      <a href="{{ route('admin.reddit.index') }}" class="nav-link {{ request()->routeIs('admin.reddit.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0A12 12 0 1 0 24 12 12 12 0 0 0 12 0Zm6.3 13.7a1 1 0 0 1 0 1.4 6.7 6.7 0 0 1-9.4 0 1 1 0 0 1 1.4-1.4 4.7 4.7 0 0 0 6.6 0 1 1 0 0 1 1.4 0Zm-9.4-3a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Zm5 0a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Z"/></svg>
        Reddit
      </a>
      <a href="{{ route('admin.pinterest.index') }}" class="nav-link {{ request()->routeIs('admin.pinterest.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0a12 12 0 0 0-4.37 23.17c-.1-.94-.2-2.4.04-3.44.22-.93 1.4-5.96 1.4-5.96s-.36-.72-.36-1.78c0-1.67.97-2.92 2.17-2.92 1.02 0 1.51.77 1.51 1.69 0 1.03-.65 2.56-.99 3.98-.28 1.19.6 2.16 1.77 2.16 2.12 0 3.76-2.24 3.76-5.48 0-2.86-2.06-4.86-5-4.86-3.41 0-5.41 2.56-5.41 5.2 0 1.03.4 2.13.89 2.73.1.12.11.22.08.34l-.33 1.35c-.05.22-.17.27-.4.16-1.49-.69-2.42-2.87-2.42-4.62 0-3.76 2.73-7.21 7.88-7.21 4.13 0 7.35 2.95 7.35 6.88 0 4.11-2.59 7.42-6.18 7.42-1.21 0-2.34-.63-2.73-1.37l-.74 2.83c-.27 1.03-1 2.32-1.48 3.11A12 12 0 1 0 12 0Z"/></svg>
        Pinterest
      </a>
      <a href="{{ route('admin.telegram.index') }}" class="nav-link {{ request()->routeIs('admin.telegram.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0a12 12 0 1 0 0 24 12 12 0 0 0 0-24Zm5.6 8.2-1.9 8.9c-.14.63-.52.78-1.05.49l-2.9-2.14-1.4 1.35c-.16.15-.29.29-.58.29l.2-2.96 5.4-4.88c.24-.2-.05-.32-.36-.12l-6.68 4.2-2.87-.9c-.63-.2-.64-.63.13-.93l11.2-4.32c.52-.19.98.12.81.93Z"/></svg>
        Telegram
      </a>
      <a href="{{ route('admin.book-rss.index') }}" class="nav-link {{ request()->routeIs('admin.book-rss.*') ? 'active' : '' }}">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M6.18 15.64a2.18 2.18 0 1 1 0 4.36 2.18 2.18 0 0 1 0-4.36ZM4 4.44A15.56 15.56 0 0 1 19.56 20h-2.83A12.73 12.73 0 0 0 4 7.27V4.44Zm0 5.66A9.9 9.9 0 0 1 13.9 20h-2.83A7.07 7.07 0 0 0 4 12.93V10.1Z"/></svg>
        Book RSS
      </a>

      <div class="nav-section">Session</div>
      <a href="{{ url('/home') }}" class="nav-link">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
        View site
      </a>
      <form method="POST" action="{{ url('/auth/logout') }}">
        @csrf
        <button class="nav-link w-full text-left" type="submit">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign out
        </button>
      </form>
    </nav>
  </aside>

  <div class="admin-scrim" id="adminScrim" hidden></div>

  {{-- ─────────── MAIN ─────────── --}}
  <main class="min-w-0">
    <header class="admin-top">
      <div class="flex min-w-0 items-center gap-3">
        <button class="admin-burger" id="adminBurger" type="button" aria-label="Open navigation" aria-controls="adminSide" aria-expanded="false">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="min-w-0">
          <div class="admin-top__crumb">@yield('breadcrumb', 'Admin')</div>
          <h1 class="admin-top__title">@yield('heading', 'Dashboard')</h1>
        </div>
      </div>
      <div class="admin-user">
        <span class="hidden text-right sm:block">
          <span class="admin-user__name">{{ $u?->name }}</span>
          <span class="admin-user__handle">{{ '@' . $u?->username }}</span>
        </span>
        <span class="avatar-circle">{{ $initials }}</span>
      </div>
    </header>

    <div class="px-4 py-5 sm:px-6 sm:py-6">
      @if(session('status'))
        <div class="flash">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          {{ session('status') }}
        </div>
      @endif

      @yield('content')
    </div>
  </main>

</div>

{{-- Off-canvas navigation below 1024px. --}}
<script>
(function () {
  var side  = document.getElementById('adminSide');
  var scrim = document.getElementById('adminScrim');
  var btn   = document.getElementById('adminBurger');
  if (!side || !scrim || !btn) return;

  function setOpen(on) {
    side.classList.toggle('is-open', on);
    scrim.classList.toggle('is-open', on);
    scrim.hidden = !on;
    btn.setAttribute('aria-expanded', String(on));
  }

  btn.addEventListener('click', function () { setOpen(!side.classList.contains('is-open')); });
  scrim.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();
</script>

{{-- Row action menus (kebab dropdowns). Delegated, so it works for any number
     of `.row-menu` on the page without per-row handlers. The panel is moved to
     <body> and fixed-positioned on open so the table's overflow:hidden can't
     clip it; it's returned to its row on close. --}}
<script>
(function () {
  var open = null; // { panel, btn, home, next }

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
