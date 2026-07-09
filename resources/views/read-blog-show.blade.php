@extends('layouts.app')
@section('title', $article->title . ' — Tanbat')

@php
    $canonical = url('/read-blog/' . $article->old_id . '_' . $article->slug . '.html');
    $desc = $article->excerpt ?: (string) \Illuminate\Support\Str::of(strip_tags((string) $article->body))->squish()->limit(200);
    $tags = $article->tagList();
    $published = $article->published_at ?: $article->created_at;

    $ld = array_filter([
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
        'headline'         => \Illuminate\Support\Str::limit($article->title, 110, ''),
        'description'      => (string) $desc,
        'datePublished'    => optional($published)->toIso8601String(),
        'dateModified'     => optional($article->updated_at)->toIso8601String(),
        'author'           => ['@type' => 'Organization', 'name' => 'Tanbat'],
        'publisher'        => [
            '@type' => 'Organization', 'name' => 'Tanbat',
            'logo'  => ['@type' => 'ImageObject', 'url' => asset('favicon.ico')],
        ],
        'articleSection'   => $article->category,
        'keywords'         => !empty($tags) ? implode(', ', $tags) : null,
    ], fn ($v) => !is_null($v) && $v !== '' && $v !== []);
@endphp

@push('head')
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<meta name="description" content="{{ $desc }}">
@if(!empty($tags))<meta name="keywords" content="{{ implode(', ', $tags) }}">@endif
<meta property="og:type" content="article">
<meta property="og:site_name" content="Tanbat">
<meta property="og:title" content="{{ $article->title }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $canonical }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $article->title }}">
<meta name="twitter:description" content="{{ $desc }}">
@if($published)<meta property="article:published_time" content="{{ $published->toIso8601String() }}">@endif
@if($article->category)<meta property="article:section" content="{{ $article->category }}">@endif
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<style>
  .prose-tanbat { color:#1E1B4B; font-size:17px; line-height:1.78; word-wrap:break-word; overflow-wrap:break-word; }
  .prose-tanbat h1,.prose-tanbat h2,.prose-tanbat h3 { font-weight:800; letter-spacing:-.02em; line-height:1.25; margin:1.6em 0 .6em; }
  .prose-tanbat h2{font-size:24px;} .prose-tanbat h3{font-size:20px;}
  .prose-tanbat p{margin:.9em 0;}
  .prose-tanbat a{color:#6C63FF; text-decoration:underline; text-underline-offset:3px; word-break:break-word;}
  .prose-tanbat blockquote{border-left:4px solid #6C63FF; background:#EEF0FF; border-radius:8px; padding:12px 18px; margin:1.4em 0; color:#36318A; font-style:italic;}
  .prose-tanbat ul,.prose-tanbat ol{padding-left:1.4em; margin:1em 0;} .prose-tanbat li{margin:.35em 0;}

  .rail-btn { display:grid; place-items:center; width:44px; height:44px; border-radius:14px; background:#fff; border:1px solid #E2E8F0; color:#475569; transition:transform .15s, box-shadow .15s, color .15s, border-color .15s; }
  .rail-btn:hover { transform:translateY(-1px); box-shadow:0 8px 20px rgba(108,99,255,.12); color:#6C63FF; border-color:#C5C9FF; }
  .rail-count { display:block; margin-top:4px; font-size:11px; font-weight:700; color:#64748b; text-align:center; }

  .rel-card { display:flex; gap:10px; padding:10px; border-radius:14px; transition:background .15s, transform .15s; }
  .rel-card:hover { background:#F7F8FF; transform:translateX(2px); }
  .rel-card .rel-ph { width:64px; height:64px; border-radius:10px; object-fit:cover; flex-shrink:0; background:linear-gradient(135deg,#EEF0FF,#FFE4EC); }

  .ad-slot { position:relative; border-radius:16px; border:1px dashed #C5C9FF; background:repeating-linear-gradient(135deg,#F7F8FF 0 12px,#EEF0FF 12px 24px); min-height:260px; display:grid; place-items:center; color:#6C63FF; font-size:12px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; }
</style>
@endpush

@section('content')
@auth
  @include('partials.navbar')
@else
  @includeIf('partials.guest-bar')
@endauth

@php
  $shareUrl = $canonical;
  $shareText = $article->title;
@endphp

<div class="mx-auto w-full max-w-[1400px] px-4 py-8 sm:px-6 lg:px-8">

  <a href="{{ url('/home') }}" class="mb-6 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-brand-600">
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Back to feed
  </a>

  <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">

    {{-- ─────────────────────── LEFT PANEL ─────────────────────── --}}
    <aside class="hidden lg:col-span-2 lg:block">
      <div class="sticky top-24 space-y-4">

        {{-- Author mini-card --}}
        <div class="card p-4 text-center">
          <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-lg font-bold text-white ring-2 ring-brand-500">T</span>
          <div class="mt-2 truncate text-sm font-bold text-slate-900">Tanbat</div>
          <div class="truncate text-xs text-slate-500">Editorial</div>
          <a href="{{ url('/home') }}" class="mt-3 inline-block w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-brand-400 hover:text-brand-600">Explore Tanbat</a>
        </div>

        {{-- Share rail --}}
        <div class="card flex flex-col items-center gap-4 py-5">
          <button type="button" id="copyLinkRail" class="rail-btn" aria-label="Copy link" title="Copy link">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
          </button>
          <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($shareText) }}" target="_blank" rel="noopener" class="rail-btn" aria-label="Share on X" title="Share on X">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2H21.5l-7.5 8.57L23 22h-6.84l-5.36-6.4L4.66 22H1.4l8.04-9.18L1 2h7.04l4.86 5.85L18.244 2zm-2.4 18h1.86L7.32 4H5.36l10.484 16z"/></svg>
          </a>
          <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" class="rail-btn" aria-label="Share on Facebook" title="Share on Facebook">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 10-11.56 9.88V14.9H7.9V12h2.54V9.8c0-2.5 1.5-3.9 3.78-3.9 1.1 0 2.25.2 2.25.2v2.47h-1.27c-1.25 0-1.64.78-1.64 1.57V12h2.8l-.45 2.9h-2.35v6.98A10 10 0 0022 12z"/></svg>
          </a>
          <a href="https://api.whatsapp.com/send?text={{ urlencode($shareText.' '.$shareUrl) }}" target="_blank" rel="noopener" class="rail-btn" aria-label="Share on WhatsApp" title="Share on WhatsApp">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.86 11.86 0 0012.04 0C5.4 0 .07 5.32.07 11.95c0 2.1.55 4.16 1.6 5.97L0 24l6.27-1.64a11.94 11.94 0 005.76 1.47h.01c6.62 0 12-5.32 12-11.95 0-3.2-1.25-6.2-3.52-8.4zM12.04 21.8h-.01a9.92 9.92 0 01-5.06-1.38l-.36-.22-3.72.97 1-3.62-.24-.37a9.93 9.93 0 01-1.52-5.23c0-5.49 4.47-9.95 9.96-9.95a9.93 9.93 0 017.04 2.92 9.91 9.91 0 012.92 7.04c0 5.5-4.46 9.96-9.96 9.96z"/></svg>
          </a>
          <span class="rail-count">Share</span>
        </div>

      </div>
    </aside>

    {{-- ─────────────────────── MAIN ARTICLE ─────────────────────── --}}
    <article class="lg:col-span-7">

      @if($article->category)
        <div class="mb-3"><span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-700">{{ $article->category }}</span></div>
      @endif

      <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">{{ $article->title }}</h1>

      @if($article->excerpt)
        <p class="mt-4 text-lg text-slate-600">{{ $article->excerpt }}</p>
      @endif

      <div class="mt-6 flex flex-wrap items-center gap-3">
        <span class="grid h-11 w-11 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white ring-2 ring-brand-500 lg:hidden">T</span>
        <div class="min-w-0">
          <div class="text-sm font-semibold text-slate-900 lg:hidden">Tanbat</div>
          <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            @if($published)
              <time datetime="{{ $published->toIso8601String() }}">{{ $published->format('M j, Y') }}</time>
              <span class="text-slate-300">·</span>
            @endif
            <span>{{ number_format($article->views) }} views</span>
          </div>
        </div>
      </div>

      <div class="prose-tanbat mt-10">{!! $article->body !!}</div>

      @if(!empty($tags))
        <div class="mt-10 flex flex-wrap items-center gap-2">
          @foreach($tags as $t)
            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">#{{ $t }}</span>
          @endforeach
        </div>
      @endif

      {{-- Mobile share row (left rail is hidden on small screens) --}}
      <div class="mt-10 flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-card lg:hidden">
        <span class="text-sm font-semibold text-slate-700">Share this article</span>
        <div class="flex items-center gap-2">
          <button type="button" id="copyLinkMobile" class="rail-btn !h-10 !w-10" aria-label="Copy link">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
          </button>
          <a href="https://api.whatsapp.com/send?text={{ urlencode($shareText.' '.$shareUrl) }}" target="_blank" rel="noopener" class="rail-btn !h-10 !w-10" aria-label="Share on WhatsApp">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.86 11.86 0 0012.04 0C5.4 0 .07 5.32.07 11.95c0 2.1.55 4.16 1.6 5.97L0 24l6.27-1.64a11.94 11.94 0 005.76 1.47h.01c6.62 0 12-5.32 12-11.95 0-3.2-1.25-6.2-3.52-8.4z"/></svg>
          </a>
        </div>
      </div>

    </article>

    {{-- ─────────────────────── RIGHT PANEL ─────────────────────── --}}
    <aside class="lg:col-span-3">
      <div class="sticky top-24 space-y-6">

        <div class="ad-slot">
          @include('partials.ad-banner')
        </div>

        {{-- Related articles --}}
        <div class="card overflow-hidden">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h3 class="text-sm font-bold text-slate-900">Related articles</h3>
            <a href="{{ url('/home') }}" class="text-xs font-semibold text-brand-600 hover:underline">More</a>
          </div>
          <div class="divide-y divide-slate-100">
            @forelse($related as $r)
              <a href="{{ url('/read-blog/' . $r->old_id . '_' . $r->slug . '.html') }}" class="rel-card">
                <div class="rel-ph grid place-items-center text-brand-500">
                  <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="line-clamp-2 text-sm font-semibold text-slate-900">{{ $r->title }}</div>
                  @if($r->published_at)
                    <div class="mt-1 text-[11px] text-slate-500">{{ $r->published_at->format('M j, Y') }}</div>
                  @endif
                </div>
              </a>
            @empty
              <div class="px-4 py-8 text-center text-xs text-slate-500">No related articles yet.</div>
            @endforelse
          </div>
        </div>

        @includeIf('partials.stat-counter')

      </div>
    </aside>

  </div>
</div>

@push('scripts')
<script>
(() => {
  const shareUrl = @json($shareUrl);
  async function copy() {
    try { await navigator.clipboard.writeText(shareUrl); window.Tanbat?.toast?.('Link copied!', 'ok'); }
    catch { window.Tanbat?.toast?.('Could not copy link', 'bad'); }
  }
  document.getElementById('copyLinkRail')?.addEventListener('click', copy);
  document.getElementById('copyLinkMobile')?.addEventListener('click', copy);
})();
</script>
@endpush
@endsection
