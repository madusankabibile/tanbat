@extends('layouts.app')
@section('title', $channel->name . ' — Live TV on Tanbat')

@push('head')
@php
    $tvUrl   = route('tv.show', $channel->slug);
    $tvImage = $channel->logo_url;

    $tvDesc = $channel->description
        ? \App\Support\Seo::describe($channel->description)
        : 'Watch ' . $channel->name . ' live online, free on Tanbat.';

    // schema.org/BroadcastChannel wrapped in a WatchAction-friendly VideoObject
    // is over-fitting; TelevisionChannel is the honest type for this page.
    $tvLd = array_filter([
        '@context'    => 'https://schema.org',
        '@type'       => 'TelevisionChannel',
        'name'        => $channel->name,
        'description' => $tvDesc,
        'image'       => $tvImage ?: null,
        'url'         => $tvUrl,
    ], fn ($v) => !is_null($v) && $v !== '');
@endphp
@include('partials._seo', [
    'title'       => $channel->name,
    'description' => $tvDesc,
    'url'         => $tvUrl,
    'image'       => $tvImage,
    'imageAlt'    => $channel->name . ' logo',
    'type'        => 'video.other',
    'jsonLd'      => $tvLd,
])
{{-- hls.js drives playback everywhere except Safari/iOS, which speak HLS
     natively. Deferred so it never gates first paint; tv-player.js waits for it. --}}
<script defer src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
@endpush

@section('content')
@auth
  @include('partials.navbar')
@else
  @include('partials.guest-bar')
@endauth

@php
  $protect = config('tv.protect');
@endphp

<div class="tv-shell mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  {{-- ═══════════ LEFT / MAIN ═══════════ --}}
  <main class="tv-main">

    <nav class="tv-crumbs">
      <a href="{{ url('/home') }}">Home</a>
      <span>/</span>
      <a href="{{ route('tv.index') }}">TV</a>
      <span>/</span>
      <strong>{{ $channel->name }}</strong>
    </nav>

    {{-- ───── Player ─────
         Note what is NOT here: a stream URL. tv-player.js POSTs for a signed,
         expiring session and feeds the result to hls.js, so the <video> ends up
         on a blob: source and the origin manifest never touches the page. --}}
    <section
      class="tv-player"
      data-tv-player
      data-session="{{ route('tv.session', $channel->slug) }}"
      data-block-shortcuts="{{ $protect['block_shortcuts'] ? '1' : '0' }}"
      data-detect-devtools="{{ $protect['detect_devtools'] ? '1' : '0' }}"
      data-debugger-trap="{{ $protect['debugger_trap'] ? '1' : '0' }}"
    >
      <video
        data-tv-video
        class="tv-video"
        playsinline
        controls
        preload="none"
        controlslist="nodownload noremoteplayback noplaybackrate"
        disablepictureinpicture
        @if($channel->logo_url) poster="{{ $channel->logo_url }}" @endif
      ></video>

      <div class="tv-overlay" data-tv-overlay hidden>
        <span class="tv-spinner" data-tv-spinner hidden aria-hidden="true"></span>
        <p class="tv-status" data-tv-status role="status"></p>
        <button type="button" class="tv-play" data-tv-play hidden aria-label="Play">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.54.84l10.28-6.86a1 1 0 0 0 0-1.68L9.54 4.3A1 1 0 0 0 8 5.14Z"/></svg>
        </button>
        <button type="button" class="tv-retry" data-tv-retry hidden>Try again</button>
      </div>

      <noscript>
        <div class="tv-overlay" style="display:flex">
          <p class="tv-status">Live TV needs JavaScript enabled.</p>
        </div>
      </noscript>
    </section>

    {{-- ───── Name + description ───── --}}
    <section class="tv-meta">
      <div class="tv-meta-head">
        @if($channel->logo_url)
          <img src="{{ $channel->logo_url }}" alt="{{ $channel->name }} logo" class="tv-logo" loading="lazy">
        @else
          <span class="tv-logo tv-logo--empty" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
          </span>
        @endif

        <div class="tv-title-block">
          <h1 class="tv-title">{{ $channel->name }}</h1>
          <div class="tv-badges">
            <span class="tv-live" data-tv-live><i></i>Live</span>
            @if($channel->post?->category)
              <a href="{{ route('tv.index', ['category' => $channel->post->category->slug]) }}" class="tv-cat">{{ $channel->post->category->name }}</a>
            @endif
            <span class="tv-views">{{ number_format($stats['views']) }} views</span>
          </div>
        </div>

        <button type="button" class="tv-share" data-tv-share data-url="{{ $tvUrl }}" data-title="{{ $channel->name }}">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="10.5" x2="15.4" y2="6.5"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/></svg>
          Share
        </button>
      </div>

      @if($channel->description)
        <div class="tv-desc">{!! nl2br(e($channel->description)) !!}</div>
      @endif
    </section>

    {{-- ───── Native banner, directly under the description ───── --}}
    <section class="tv-ad-native">
      <span class="tv-ad-tag">Sponsored</span>
      @include('partials.native-banner')
    </section>

  </main>

  {{-- ═══════════ RIGHT PANEL ═══════════ --}}
  <aside class="tv-aside">
    <div class="tv-aside-sticky">

      {{-- Ad space (top of the rail) --}}
      <section class="tv-card tv-ad-card">
        <span class="tv-ad-tag">Sponsored</span>
        @include('partials.ad-banner')
      </section>

      {{-- Related channels --}}
      <section class="tv-card">
        <div class="tv-card-head">
          <h2>More channels</h2>
          <a href="{{ route('tv.index') }}">See all</a>
        </div>

        @if($related->isEmpty())
          <p class="tv-empty">No other channels are live right now.</p>
        @else
          <ul class="tv-related">
            @foreach($related as $r)
              <li>
                <a href="{{ route('tv.show', $r->slug) }}" class="tv-related-item">
                  @if($r->logo_url)
                    <img src="{{ $r->logo_url }}" alt="" loading="lazy">
                  @else
                    <span class="tv-related-noimg" aria-hidden="true">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="7" width="20" height="14" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
                    </span>
                  @endif
                  <span class="tv-related-text">
                    <strong>{{ $r->name }}</strong>
                    <span>{{ number_format($r->views) }} views</span>
                  </span>
                </a>
              </li>
            @endforeach
          </ul>
        @endif
      </section>

      {{-- Statistics --}}
      <section class="tv-card">
        <div class="tv-card-head">
          <h2>Statistics</h2>
        </div>
        <dl class="tv-stats">
          <div>
            <dt>This channel</dt>
            <dd>{{ number_format($stats['views']) }}</dd>
          </div>
          <div>
            <dt>Channels live</dt>
            <dd>{{ number_format($stats['channels']) }}</dd>
          </div>
          <div>
            <dt>All TV views</dt>
            <dd>{{ number_format($stats['total']) }}</dd>
          </div>
          <div>
            <dt>Added</dt>
            <dd class="tv-stats-date">{{ $stats['added']?->format('M j, Y') ?? '—' }}</dd>
          </div>
        </dl>
      </section>

      {{-- Live visitors (the site-wide widget) --}}
      @include('partials.stat-counter')

    </div>
  </aside>
</div>

@auth
  @include('partials.user-sheet')
  @include('partials.share-modal')
@endauth

<style>
/* ══════════════ /tv/{slug} — player page ══════════════ */
.tv-shell { display: grid; grid-template-columns: 1fr; gap: 20px; }
.tv-main  { min-width: 0; max-width: 900px; margin: 0 auto; width: 100%; }
.tv-aside { display: none; }
@media (min-width: 1024px) {
  .tv-shell { grid-template-columns: minmax(0, 1fr) 320px; align-items: start; }
  .tv-main  { max-width: none; margin: 0; }
  .tv-aside { display: block; }
}
.tv-aside-sticky { position: sticky; top: 88px; display: flex; flex-direction: column; gap: 16px; }

/* ───── Breadcrumb ───── */
.tv-crumbs {
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
  margin-bottom: 12px; font-size: 12.5px; color: #64748B;
}
.tv-crumbs a { color: #6366F1; font-weight: 600; }
.tv-crumbs a:hover { text-decoration: underline; }
.tv-crumbs strong { color: #1E1B4B; font-weight: 700; min-width: 0; }

/* ───── Player ───── */
.tv-player {
  position: relative; overflow: hidden; border-radius: 16px;
  background: #0B1020; aspect-ratio: 16 / 9;
  box-shadow: 0 12px 32px rgba(11, 16, 32, .22);
}
.tv-video { width: 100%; height: 100%; display: block; background: #0B1020; object-fit: contain; }

/* The curtain drawn when the devtools heuristic fires. Blurring the frame
   rather than hiding it keeps the pause legible as deliberate, not broken. */
.tv-player.is-curtained .tv-video { filter: blur(22px); transform: scale(1.04); }

.tv-overlay {
  position: absolute; inset: 0; z-index: 2;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
  padding: 20px; text-align: center;
  background: radial-gradient(circle at 50% 45%, rgba(15, 23, 42, .72), rgba(11, 16, 32, .92));
  color: #E2E8F0; backdrop-filter: blur(2px);
}
.tv-overlay[hidden] { display: none; }
.tv-status { margin: 0; font-size: 14px; font-weight: 600; max-width: 34ch; line-height: 1.5; }
.tv-spinner {
  height: 34px; width: 34px; border-radius: 9999px;
  border: 3px solid rgba(226, 232, 240, .25); border-top-color: #818CF8;
  animation: tv-spin .8s linear infinite;
}
@keyframes tv-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .tv-spinner { animation-duration: 2.4s; } }
.tv-retry {
  border: 0; border-radius: 9999px; padding: 9px 20px; cursor: pointer;
  background: #6366F1; color: #fff; font-size: 13px; font-weight: 700;
}
.tv-retry:hover { background: #4F46E5; }

/* Shown when autoplay is blocked — the overlay hides the native play control,
   so this is the only thing the viewer can actually click. */
.tv-play {
  display: grid; place-items: center; cursor: pointer;
  height: 68px; width: 68px; padding: 0; border: 0; border-radius: 9999px;
  background: rgba(99, 102, 241, .95); color: #fff;
  box-shadow: 0 8px 24px rgba(11, 16, 32, .45);
  transition: transform .15s ease, background .15s ease;
}
.tv-play svg { height: 32px; width: 32px; margin-left: 3px; }
.tv-play:hover { background: #4F46E5; transform: scale(1.06); }
.tv-play[hidden] { display: none; }
@media (prefers-reduced-motion: reduce) { .tv-play { transition: none; } }

/* ───── Name + description ───── */
.tv-meta {
  margin-top: 16px; padding: 18px;
  background: #fff; border: 1px solid #E5E7EB; border-radius: 16px;
  box-shadow: 0 1px 2px rgba(20, 20, 50, .04);
}
.tv-meta-head { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.tv-logo {
  height: 56px; width: 56px; flex: none; border-radius: 12px;
  object-fit: contain; background: #F8FAFC; border: 1px solid #E5E7EB; padding: 5px;
}
.tv-logo--empty { display: grid; place-items: center; color: #CBD5E1; padding: 12px; }
.tv-logo--empty svg { height: 100%; width: 100%; }
.tv-title-block { min-width: 0; flex: 1; }
.tv-title { margin: 0; font-size: 21px; font-weight: 800; color: #1E1B4B; line-height: 1.25; }
@media (min-width: 640px) { .tv-title { font-size: 25px; } }
.tv-badges { display: flex; align-items: center; gap: 12px; margin-top: 6px; flex-wrap: wrap; }
.tv-live {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 10.5px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase;
  color: #94A3B8;
}
.tv-live i { height: 7px; width: 7px; border-radius: 9999px; background: #CBD5E1; }
/* Only turns red once the player reports it is actually playing. */
.tv-live.is-live { color: #DC2626; }
.tv-live.is-live i { background: #DC2626; animation: tv-pulse 1.8s infinite; }
@keyframes tv-pulse {
  0%   { box-shadow: 0 0 0 0 rgba(220, 38, 38, .5); }
  70%  { box-shadow: 0 0 0 6px rgba(220, 38, 38, 0); }
  100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
}
.tv-cat {
  display: inline-flex; align-items: center; border-radius: 9999px;
  padding: 3px 10px; background: #EEF2FF; color: #4338CA;
  font-size: 11px; font-weight: 700;
}
.tv-cat:hover { background: #E0E7FF; }
.tv-views { font-size: 12.5px; font-weight: 600; color: #64748B; }
.tv-share {
  display: inline-flex; align-items: center; gap: 7px; flex: none;
  border: 1px solid #E5E7EB; border-radius: 9999px; padding: 8px 16px;
  background: #fff; color: #4338CA; font-size: 13px; font-weight: 700; cursor: pointer;
}
.tv-share:hover { background: #EEF2FF; border-color: #C7D2FE; }
.tv-desc {
  margin-top: 14px; padding-top: 14px; border-top: 1px solid #F1F5F9;
  font-size: 14.5px; line-height: 1.7; color: #475569; overflow-wrap: anywhere;
}

/* ───── Ads ───── */
.tv-ad-native { margin-top: 16px; }
.tv-ad-tag {
  display: block; margin-bottom: 8px;
  font-size: 9.5px; font-weight: 800; letter-spacing: .7px; text-transform: uppercase; color: #94A3B8;
}
.tv-ad-card { display: flex; flex-direction: column; align-items: center; }
.native-banner { min-height: 90px; }

/* ───── Right-rail cards ───── */
.tv-card {
  background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 14px 16px;
  box-shadow: 0 1px 2px rgba(20, 20, 50, .04);
}
.tv-card-head {
  display: flex; align-items: baseline; justify-content: space-between; gap: 10px;
  margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #F1F5F9;
}
.tv-card-head h2 { margin: 0; font-size: 13px; font-weight: 800; color: #1E1B4B; }
.tv-card-head a  { font-size: 11.5px; font-weight: 700; color: #6366F1; }
.tv-card-head a:hover { text-decoration: underline; }
.tv-empty { margin: 0; font-size: 13px; color: #94A3B8; }

.tv-related { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; max-height: 420px; overflow-y: auto; }
.tv-related-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px; border-radius: 10px; transition: background .15s ease;
}
.tv-related-item:hover { background: #F8FAFC; }
.tv-related-item img,
.tv-related-noimg {
  height: 40px; width: 40px; flex: none; border-radius: 9px;
  object-fit: contain; background: #F1F5F9; padding: 4px;
}
.tv-related-noimg { display: grid; place-items: center; color: #CBD5E1; }
.tv-related-noimg svg { height: 18px; width: 18px; }
.tv-related-text { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.tv-related-text strong {
  font-size: 13px; font-weight: 700; color: #1E1B4B;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.tv-related-text span { font-size: 11px; color: #94A3B8; }

.tv-stats { margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 12px 10px; }
.tv-stats dt { font-size: 10.5px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; color: #94A3B8; }
.tv-stats dd { margin: 2px 0 0; font-size: 19px; font-weight: 800; color: #1E1B4B; font-variant-numeric: tabular-nums; }
.tv-stats dd.tv-stats-date { font-size: 13px; font-weight: 700; color: #475569; }
</style>

@push('scripts')
@vite(['resources/js/tv-player.js'])
<script>
// Share button: the Web Share sheet on mobile, clipboard everywhere else.
document.querySelector('[data-tv-share]')?.addEventListener('click', async function () {
  const url = this.dataset.url, title = this.dataset.title;
  if (navigator.share) {
    try { await navigator.share({ title, url }); return; } catch { /* dismissed */ }
  }
  try {
    await navigator.clipboard.writeText(url);
    const original = this.lastChild.textContent;
    this.lastChild.textContent = ' Link copied';
    setTimeout(() => { this.lastChild.textContent = original; }, 1600);
  } catch { /* clipboard blocked — the URL bar still has it */ }
});
</script>
@endpush

@endsection
