@extends('layouts.app')
@section('title', $post->title . ' — Tanbat')

@push('head')
{{-- Open Graph / Twitter Card tags so link previews (Facebook, X, WhatsApp…)
     use the article's featured image + title, not whatever image the scraper
     stumbles on first (which was defaulting to the author's avatar). --}}
@php
    $ogImage  = $post->featured_image_url ?: optional($post->user)->avatarUrl();
    $ogDesc   = (string) \Illuminate\Support\Str::of(strip_tags((string) ($post->short_description ?: $post->body)))
                  ->squish()->limit(200);
    $ogUrl    = route('articles.show', $post->slug);
    $authorNm = optional($post->user)->name ?: optional($post->user)->username;
    $authorUrl = optional($post->user)->username ? route('profile', $post->user->username) : null;
    $section  = optional($post->category)->name;
    $tagNames = $post->relationLoaded('tags') ? $post->tags->pluck('name')->filter()->values() : collect();

    // Schema.org BlogPosting — the primary rich-result signal for Google.
    $ld = array_filter([
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $ogUrl],
        'headline'         => \Illuminate\Support\Str::limit($post->title, 110, ''),
        'description'      => $ogDesc,
        'image'            => $post->featured_image_url ? [$post->featured_image_url] : null,
        'datePublished'    => optional($post->created_at)->toIso8601String(),
        'dateModified'     => optional($post->updated_at)->toIso8601String(),
        'author'           => $authorNm ? array_filter([
            '@type' => 'Person', 'name' => $authorNm, 'url' => $authorUrl,
        ]) : null,
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => 'Tanbat',
            'logo'  => ['@type' => 'ImageObject', 'url' => asset('favicon.ico')],
        ],
        'articleSection'   => $section,
        'keywords'         => $tagNames->isNotEmpty() ? $tagNames->implode(', ') : null,
    ], fn ($v) => !is_null($v) && $v !== '' && $v !== []);
@endphp
<link rel="canonical" href="{{ $ogUrl }}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="description" content="{{ $ogDesc }}">
@if($authorNm)<meta name="author" content="{{ $authorNm }}">@endif
@if($tagNames->isNotEmpty())<meta name="keywords" content="{{ $tagNames->implode(', ') }}">@endif
<meta property="og:type" content="article">
<meta property="og:site_name" content="Tanbat">
<meta property="og:locale" content="en_US">
<meta property="og:title" content="{{ $post->title }}">
<meta property="og:description" content="{{ $ogDesc }}">
<meta property="og:url" content="{{ $ogUrl }}">
@if($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:alt" content="{{ $post->title }}">
@endif
@if($post->created_at)<meta property="article:published_time" content="{{ $post->created_at->toIso8601String() }}">@endif
@if($post->updated_at)<meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">@endif
@if($authorNm)<meta property="article:author" content="{{ $authorNm }}">@endif
@if($section)<meta property="article:section" content="{{ $section }}">@endif
@foreach($tagNames as $t)<meta property="article:tag" content="{{ $t }}">
@endforeach
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $post->title }}">
<meta name="twitter:description" content="{{ $ogDesc }}">
@if($ogImage)
<meta name="twitter:image" content="{{ $ogImage }}">
<meta name="twitter:image:alt" content="{{ $post->title }}">
@endif
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<style>
  .prose-tanbat {
    color: #1E1B4B;
    font-size: 17px; line-height: 1.78;
  }
  .prose-tanbat h1, .prose-tanbat h2, .prose-tanbat h3 {
    font-weight: 800; letter-spacing: -0.02em; line-height: 1.25;
    margin: 1.6em 0 .6em;
  }
  .prose-tanbat h1 { font-size: 30px; }
  .prose-tanbat h2 { font-size: 24px; }
  .prose-tanbat h3 { font-size: 20px; }
  .prose-tanbat p { margin: .9em 0; }
  .prose-tanbat a { color: #6C63FF; text-decoration: underline; text-underline-offset: 3px; }
  .prose-tanbat img { max-width: 100%; height: auto; border-radius: 12px; margin: 1.4em auto; box-shadow: 0 4px 16px rgba(20,20,50,.06); }
  .prose-tanbat blockquote {
    border-left: 4px solid #6C63FF; background: #EEF0FF;
    border-radius: 8px; padding: 12px 18px; margin: 1.4em 0;
    color: #36318A; font-style: italic;
  }
  .prose-tanbat pre {
    background: #0F172A; color: #E2E8F0;
    padding: 14px 16px; border-radius: 12px; overflow: auto;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 13px; line-height: 1.65;
  }
  .prose-tanbat ul, .prose-tanbat ol { padding-left: 1.4em; margin: 1em 0; }
  .prose-tanbat li { margin: .35em 0; }

  /* Engagement rail */
  .rail-btn {
    display: grid; place-items: center;
    width: 44px; height: 44px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid #E2E8F0;
    color: #475569;
    transition: transform .15s, box-shadow .15s, color .15s, border-color .15s, background .15s;
  }
  .rail-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(108,99,255,.12); color: #6C63FF; border-color: #C5C9FF; }
  .rail-btn.is-active {
    background: linear-gradient(135deg,#FFE4EC 0%,#FFD2DE 100%);
    border-color: #FFB3C4; color: #E54F6E;
  }
  .rail-count { display: block; margin-top: 4px; font-size: 11px; font-weight: 700; color: #64748b; text-align: center; }

  /* ── Facebook-style reactions ── */
  .reaction-wrap { position: relative; display: inline-flex; }
  .re-emoji { line-height: 1; }
  #likeBtn .re-emoji { font-size: 22px; }
  #likeBtnMobile .re-emoji { font-size: 18px; }
  .re-emoji:empty { display: none; }
  .is-active .re-default { display: none; }
  .reaction-pop {
    position: absolute; bottom: calc(100% + 8px); left: 0;
    display: flex; gap: 2px; padding: 5px 7px;
    background: #fff; border-radius: 9999px;
    box-shadow: 0 12px 32px rgba(15,23,42,.20); border: 1px solid #EEF2F7;
    opacity: 0; pointer-events: none;
    transform: translateY(8px) scale(.9); transform-origin: bottom left;
    transition: opacity .15s ease, transform .15s ease;
    z-index: 50; white-space: nowrap;
  }
  .reaction-pop::after {
    content: ''; position: absolute; left: 0; right: 0; top: 100%; height: 12px;
  }
  .reaction-wrap:hover .reaction-pop,
  .reaction-wrap:focus-within .reaction-pop,
  .reaction-wrap.is-open .reaction-pop {
    opacity: 1; pointer-events: auto; transform: translateY(0) scale(1);
  }
  .reaction-wrap.is-dismissed .reaction-pop {
    opacity: 0 !important; pointer-events: none !important; transform: translateY(8px) scale(.9) !important;
  }
  .reaction-pop .reaction-pop-btn {
    display: grid; place-items: center;
    width: 40px; height: 40px; padding: 0;
    border-radius: 9999px; background: transparent;
    font-size: 26px; line-height: 1; cursor: pointer;
    border: 0; transition: transform .12s ease, background .12s ease;
  }
  .reaction-pop .reaction-pop-btn:hover { transform: translateY(-7px) scale(1.3); background: #F8FAFC; }

  /* Related card */
  .rel-card {
    display: flex; gap: 10px;
    padding: 10px; border-radius: 14px;
    transition: background .15s, transform .15s;
  }
  .rel-card:hover { background: #F7F8FF; transform: translateX(2px); }
  .rel-card img, .rel-card .rel-ph {
    width: 64px; height: 64px; border-radius: 10px;
    object-fit: cover; flex-shrink: 0;
    background: linear-gradient(135deg,#EEF0FF,#FFE4EC);
  }

  /* Ad slot */
  .ad-slot {
    position: relative;
    border-radius: 16px;
    border: 1px dashed #C5C9FF;
    background: repeating-linear-gradient(135deg, #F7F8FF 0 12px, #EEF0FF 12px 24px);
    min-height: 260px;
    display: grid; place-items: center;
    color: #6C63FF; font-size: 12px; font-weight: 600;
    letter-spacing: .12em; text-transform: uppercase;
  }
  .ad-slot::after {
    content: 'AD';
    position: absolute; top: 10px; right: 10px;
    font-size: 9px; font-weight: 800; letter-spacing: .15em;
    color: #94a3b8; background: #fff; padding: 3px 7px;
    border-radius: 6px; border: 1px solid #E2E8F0;
  }

  /* Comment */
  .reply-form[hidden] { display: none; }
</style>
@endpush

@section('content')
@auth
  @include('partials.navbar')
@endauth

@php
  $u = $post->user;
  $av = $u?->avatarUrl();
  $cat = $post->category;
  $shareUrl = url()->current();
  $shareText = $post->title;
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
          @if($av)
            <img src="{{ $av }}" class="mx-auto h-16 w-16 rounded-full object-cover ring-2 ring-brand-500" alt="">
          @else
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-lg font-bold text-white ring-2 ring-brand-500">{{ strtoupper(substr($u->username ?? 'U', 0, 1)) }}</span>
          @endif
          <div class="mt-2 truncate text-sm font-bold text-slate-900">{{ $u->name }}</div>
          <div class="truncate text-xs text-slate-500">&#64;{{ $u->username }}</div>
          <a href="{{ url('/'.$u->username) }}" class="mt-3 inline-block w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-brand-400 hover:text-brand-600">View profile</a>
        </div>

        {{-- Engagement rail --}}
        <div class="card flex flex-col items-center gap-4 py-5">
          @php $reMap = ['like'=>'👍','love'=>'❤️','haha'=>'😆','wow'=>'😮','sad'=>'😢','angry'=>'😡']; @endphp
          <div class="reaction-wrap">
            <div class="reaction-pop" role="menu" aria-label="Pick a reaction">
              @foreach ($reMap as $rk => $rEmoji)
                <button type="button" class="reaction-pop-btn" data-react="{{ $rk }}" title="{{ ucfirst($rk) }}" aria-label="{{ ucfirst($rk) }}"><span class="re">{{ $rEmoji }}</span></button>
              @endforeach
            </div>
            <button type="button" id="likeBtn"
              class="rail-btn {{ $liked ? 'is-active' : '' }}"
              data-reaction="{{ $myReaction ?? '' }}"
              aria-label="React to article">
              <span class="re-emoji">{{ $myReaction ? $reMap[$myReaction] : '' }}</span>
              <svg class="re-default h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>
            </button>
          </div>
          <span class="rail-count" id="likeCount">{{ number_format($post->likes_count ?? 0) }}</span>

          <button type="button" class="rail-btn" onclick="document.getElementById('aCommentBody')?.focus()" aria-label="Comment">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          </button>
          <span class="rail-count" id="commentCount">{{ number_format($post->comments_count ?? $post->comments->count()) }}</span>

          <div class="relative" data-menu="share">
            <button type="button" class="rail-btn" data-menu-trigger aria-label="Share">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </button>
            <div data-menu-panel class="absolute left-14 top-0 z-20 hidden w-48 origin-top-left rounded-xl border border-slate-200 bg-white p-1.5 shadow-pop animate-fup">
              <button type="button" class="share-act flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-brand-50" data-share="copy">
                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                Copy link
              </button>
              <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($shareText) }}" target="_blank" rel="noopener" class="share-act flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-brand-50" data-share="x">
                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2H21.5l-7.5 8.57L23 22h-6.84l-5.36-6.4L4.66 22H1.4l8.04-9.18L1 2h7.04l4.86 5.85L18.244 2zm-2.4 18h1.86L7.32 4H5.36l10.484 16z"/></svg>
                Share on X
              </a>
              <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" class="share-act flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-brand-50" data-share="fb">
                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 10-11.56 9.88V14.9H7.9V12h2.54V9.8c0-2.5 1.5-3.9 3.78-3.9 1.1 0 2.25.2 2.25.2v2.47h-1.27c-1.25 0-1.64.78-1.64 1.57V12h2.8l-.45 2.9h-2.35v6.98A10 10 0 0022 12z"/></svg>
                Share on Facebook
              </a>
              <a href="https://api.whatsapp.com/send?text={{ urlencode($shareText.' '.$shareUrl) }}" target="_blank" rel="noopener" class="share-act flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-brand-50" data-share="wa">
                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.86 11.86 0 0012.04 0C5.4 0 .07 5.32.07 11.95c0 2.1.55 4.16 1.6 5.97L0 24l6.27-1.64a11.94 11.94 0 005.76 1.47h.01c6.62 0 12-5.32 12-11.95 0-3.2-1.25-6.2-3.52-8.4zM12.04 21.8h-.01a9.92 9.92 0 01-5.06-1.38l-.36-.22-3.72.97 1-3.62-.24-.37a9.93 9.93 0 01-1.52-5.23c0-5.49 4.47-9.95 9.96-9.95a9.93 9.93 0 017.04 2.92 9.91 9.91 0 012.92 7.04c0 5.5-4.46 9.96-9.96 9.96z"/></svg>
                Share on WhatsApp
              </a>
            </div>
          </div>
          <span class="rail-count">Share</span>
        </div>

      </div>
    </aside>

    {{-- ─────────────────────── MAIN ARTICLE ─────────────────────── --}}
    <article class="lg:col-span-7">

      @if($cat)
        <div class="mb-3"><span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-700">{{ $cat->name }}</span></div>
      @endif

      <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">{{ $post->title }}</h1>

      @if($post->short_description)
        <p class="mt-4 text-lg text-slate-600">{{ $post->short_description }}</p>
      @endif

      <div class="mt-6 flex flex-wrap items-center gap-3">
        @if($av)
          <img src="{{ $av }}" class="h-11 w-11 rounded-full object-cover ring-2 ring-brand-500 lg:hidden" alt="">
        @endif
        <div class="min-w-0">
          <div class="text-sm font-semibold text-slate-900 lg:hidden">{{ $u->name }}</div>
          <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span class="lg:hidden">&#64;{{ $u->username }}</span>
            <span class="text-slate-300 lg:hidden">·</span>
            <span>{{ $post->created_at->diffForHumans() }}</span>
            <span class="text-slate-300">·</span>
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-extrabold text-rose-600 ring-1 ring-rose-200/80 shadow-[0_1px_0_rgba(225,29,72,.08)]"
                  style="background: linear-gradient(135deg,#FFE4EC 0%,#FFF1D6 100%);"
                  title="{{ number_format($post->views_count) }} views">
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
              <span class="text-rose-700">{{ number_format($post->views_count) }}</span>
              <span class="text-[10px] uppercase tracking-wider text-pink-600/90">views</span>
            </span>
          </div>
        </div>
      </div>

      @if($post->featured_image_url)
        <img src="{{ $post->featured_image_url }}" class="mt-8 aspect-[16/9] w-full rounded-2xl object-cover shadow-card" alt="">
      @endif

      <div class="prose-tanbat mt-10">{!! $post->body !!}</div>

      @if($post->tags->count())
        <div class="mt-10 flex flex-wrap items-center gap-2">
          @foreach($post->tags as $t)
            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">#{{ $t->name }}</span>
          @endforeach
        </div>
      @endif

      {{-- Mobile/Tablet engagement row (the left rail is hidden on small screens) --}}
      <div class="mt-10 flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-card lg:hidden">
        <div class="flex items-center gap-2">
          <div class="reaction-wrap">
            <div class="reaction-pop" role="menu" aria-label="Pick a reaction">
              @foreach ($reMap as $rk => $rEmoji)
                <button type="button" class="reaction-pop-btn" data-react="{{ $rk }}" title="{{ ucfirst($rk) }}" aria-label="{{ ucfirst($rk) }}"><span class="re">{{ $rEmoji }}</span></button>
              @endforeach
            </div>
            <button type="button" id="likeBtnMobile"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:border-rose-300 hover:text-rose-600 {{ $liked ? '!border-rose-300 !text-rose-600 !bg-rose-50' : '' }}"
              data-reaction="{{ $myReaction ?? '' }}">
              <span class="re-emoji">{{ $myReaction ? $reMap[$myReaction] : '' }}</span>
              <svg class="re-default h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>
              <span class="re-label">{{ $myReaction ? ucfirst($myReaction) : 'Like' }}</span>
              <span id="likeCountMobile">{{ number_format($post->likes_count ?? 0) }}</span>
            </button>
          </div>
          <button type="button" onclick="document.getElementById('aCommentBody')?.focus()" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:border-brand-400 hover:text-brand-600">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            <span>{{ number_format($post->comments_count ?? $post->comments->count()) }}</span>
          </button>
        </div>
        <button type="button" id="shareBtnMobile" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 px-4 py-1.5 text-sm font-semibold text-white shadow-soft hover:-translate-y-0.5 hover:shadow-pop">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
          Share
        </button>
      </div>

      {{-- ─────────────────────── COMMENTS ─────────────────────── --}}
      <section class="mt-14 border-t border-slate-200 pt-10">
        <h2 class="text-xl font-bold text-slate-900">
          Comments <span id="commentHeadCount" class="text-slate-400">({{ number_format($post->comments_count ?? $post->comments->count()) }})</span>
        </h2>

        @auth
          @php $me = auth()->user(); $meAv = $me->avatarUrl(); @endphp
          <form id="aCommentForm" class="mt-5 flex items-start gap-3">
            @if($meAv)
              <img src="{{ $meAv }}" class="h-9 w-9 shrink-0 rounded-full object-cover" alt="">
            @else
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-xs font-bold text-white">{{ strtoupper(substr($me->username ?? 'U', 0, 1)) }}</span>
            @endif
            <div class="flex-1">
              <textarea id="aCommentBody" required maxlength="1000" rows="2"
                placeholder="Share your thoughts…"
                class="input w-full resize-none"></textarea>
              <div class="mt-2 flex justify-end">
                <button type="submit" class="btn-primary">Post comment</button>
              </div>
            </div>
          </form>
        @else
          <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <a href="{{ url('/') }}" class="font-semibold text-brand-600 hover:underline">Sign in</a> to leave a comment.
          </div>
        @endauth

        <div id="aComments" class="mt-8 space-y-6">
          @foreach($post->comments as $c)
            @php $cav = $c->user?->avatarUrl(); @endphp
            <div class="comment-thread" data-comment-id="{{ $c->id }}">
              <div class="flex gap-3">
                @if($cav)
                  <img src="{{ $cav }}" class="h-10 w-10 shrink-0 rounded-full object-cover" alt="">
                @else
                  <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-xs font-bold text-white">{{ strtoupper(substr($c->user->username ?? 'U', 0, 1)) }}</span>
                @endif
                <div class="flex-1">
                  <div class="rounded-2xl bg-slate-50 px-4 py-3">
                    <div class="flex items-baseline gap-2">
                      <a href="{{ url('/'.$c->user->username) }}" class="text-sm font-semibold text-slate-900 hover:text-brand-600">{{ $c->user->username }}</a>
                      <span class="text-xs text-slate-400">{{ $c->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-700">{{ $c->body }}</p>
                  </div>
                  @auth
                    <div class="mt-1 flex items-center gap-3 pl-2 text-xs">
                      <button type="button" class="reply-trigger font-semibold text-slate-500 hover:text-brand-600" data-parent="{{ $c->id }}">Reply</button>
                    </div>
                    <form class="reply-form mt-3 flex items-start gap-2 pl-2" data-parent="{{ $c->id }}" hidden>
                      <input type="text" maxlength="1000" required placeholder="Reply to &#64;{{ $c->user->username }}…" class="input flex-1 text-sm">
                      <button type="submit" class="btn-primary !py-2 !px-3 text-xs">Reply</button>
                      <button type="button" class="reply-cancel rounded-lg px-2 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-100">Cancel</button>
                    </form>
                  @endauth

                  {{-- Nested replies --}}
                  <div class="reply-list mt-3 space-y-3 border-l-2 border-slate-100 pl-4">
                    @foreach($c->replies as $r)
                      @php $rav = $r->user?->avatarUrl(); @endphp
                      <div class="flex gap-3" data-reply-id="{{ $r->id }}">
                        @if($rav)
                          <img src="{{ $rav }}" class="h-8 w-8 shrink-0 rounded-full object-cover" alt="">
                        @else
                          <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-[11px] font-bold text-white">{{ strtoupper(substr($r->user->username ?? 'U', 0, 1)) }}</span>
                        @endif
                        <div class="flex-1 rounded-2xl bg-white border border-slate-100 px-3 py-2">
                          <div class="flex items-baseline gap-2">
                            <span class="text-xs font-semibold text-slate-900">{{ $r->user->username }}</span>
                            <span class="text-[11px] text-slate-400">{{ $r->created_at->diffForHumans() }}</span>
                          </div>
                          <p class="mt-1 text-sm text-slate-700">{{ $r->body }}</p>
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>
              </div>
            </div>
          @endforeach

          @if($post->comments->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center text-sm text-slate-500">
              Be the first to share your thoughts.
            </div>
          @endif
        </div>
      </section>
    </article>

    {{-- ─────────────────────── RIGHT PANEL ─────────────────────── --}}
    <aside class="lg:col-span-3">
      <div class="sticky top-24 space-y-6">

        {{-- Advertisement slot --}}
        <div class="ad-slot">
          @include('partials.ad-banner')
        </div>
        
        {{-- Secondary ad slot --}}
        <div class="ad-slot" style="min-height: 250px;">
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
              @php
                $rUser = $r->user;
                $rCat  = $r->category;
              @endphp
              <a href="{{ route('articles.show', $r->slug) }}" class="rel-card">
                @if($r->featured_image_url)
                  <img src="{{ $r->featured_image_url }}" alt="">
                @else
                  <div class="rel-ph grid place-items-center text-brand-500">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  </div>
                @endif
                <div class="min-w-0 flex-1">
                  @if($rCat)
                    <div class="mb-0.5 truncate text-[10px] font-bold uppercase tracking-wider text-brand-600">{{ $rCat->name }}</div>
                  @endif
                  <div class="line-clamp-2 text-sm font-semibold text-slate-900">{{ $r->title }}</div>
                  <div class="mt-1 flex items-center gap-1.5 text-[11px] text-slate-500">
                    <span class="truncate">&#64;{{ $rUser?->username }}</span>
                    <span class="text-slate-300">·</span>
                    <span>{{ $r->created_at->diffForHumans() }}</span>
                  </div>
                </div>
              </a>
            @empty
              <div class="px-4 py-8 text-center text-xs text-slate-500">No related articles yet.</div>
            @endforelse
          </div>
        </div>
        {{-- Visitor stats (bottom of rail) --}}
        @include('partials.stat-counter')


      </div>
    </aside>

  </div>
</div>

@push('scripts')
<script>
(() => {
  const shareUrl  = @json($shareUrl);
  const shareText = @json($shareText);
  const postId    = @json($post->id);
  const csrf      = window.__APP__?.csrf;
  const authed    = !!window.__APP__?.user;
  const me        = window.__APP__?.user;

  /* ─────────── Share — global bindMenus() (app.js) already toggles ─────────── */

  // Copy link
  document.querySelectorAll('[data-share="copy"]').forEach(btn => {
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(shareUrl);
        window.Tanbat?.toast?.('Link copied!', 'ok');
      } catch { window.Tanbat?.toast?.('Could not copy link', 'bad'); }
      recordShare();
    });
  });
  // Notify backend on social shares
  document.querySelectorAll('.share-act[data-share]:not([data-share="copy"])').forEach(a => {
    a.addEventListener('click', () => recordShare());
  });

  // Mobile share button — uses native share API if available, otherwise copies the link
  document.getElementById('shareBtnMobile')?.addEventListener('click', async () => {
    if (navigator.share) {
      try { await navigator.share({ title: shareText, url: shareUrl }); recordShare(); } catch {}
    } else {
      try { await navigator.clipboard.writeText(shareUrl); window.Tanbat?.toast?.('Link copied!', 'ok'); recordShare(); } catch {}
    }
  });

  async function recordShare() {
    if (!authed) return;
    try {
      await fetch(`${window.__APP__.urls.api.posts}/${postId}/share`, {
        method: 'POST',
        headers: { 'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf },
        credentials: 'same-origin',
      });
    } catch {}
  }

  /* ─────────── Reactions ─────────── */
  const REACTIONS = {
    like:  { e: '👍', l: 'Like' },  love: { e: '❤️', l: 'Love' },
    haha:  { e: '😆', l: 'Haha' },  wow:  { e: '😮', l: 'Wow'  },
    sad:   { e: '😢', l: 'Sad'  },  angry:{ e: '😡', l: 'Angry'},
  };
  let myReaction = @json($myReaction);

  function syncReactionUI(reaction, count) {
    myReaction = reaction || null;
    const r = myReaction ? REACTIONS[myReaction] : null;
    ['likeBtn', 'likeBtnMobile'].forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      el.dataset.reaction = myReaction || '';
      el.classList.toggle('is-active', !!r);
      const em = el.querySelector('.re-emoji'); if (em) em.textContent = r ? r.e : '';
      const lbl = el.querySelector('.re-label'); if (lbl) lbl.textContent = r ? r.l : 'Like';
    });
    const m = document.getElementById('likeBtnMobile');
    if (m) {
      m.classList.toggle('!border-rose-300', !!r);
      m.classList.toggle('!text-rose-600', !!r);
      m.classList.toggle('!bg-rose-50', !!r);
    }
    if (typeof count === 'number') {
      const c1 = document.getElementById('likeCount');
      const c2 = document.getElementById('likeCountMobile');
      if (c1) c1.textContent = count.toLocaleString();
      if (c2) c2.textContent = count.toLocaleString();
    }
  }

  async function react(reactionKey) {
    if (!authed) { window.Tanbat?.toast?.('Sign in to react to this article', 'bad'); return; }
    try {
      const r = await fetch(`${window.__APP__.urls.api.posts}/${postId}/like`, {
        method: 'POST',
        headers: {
          'Content-Type':'application/json','Accept':'application/json',
          'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ reaction: reactionKey }),
      });
      const data = await r.json();
      if (!r.ok) throw new Error(data?.message || 'Error');
      syncReactionUI(data.reaction, data.likes_count);
    } catch (err) { window.Tanbat?.toast?.(err.message, 'bad'); }
  }

  // Main button: tap toggles (re-send current reaction to clear, else 'like').
  document.getElementById('likeBtn')?.addEventListener('click', () => react(myReaction || 'like'));
  document.getElementById('likeBtnMobile')?.addEventListener('click', () => react(myReaction || 'like'));

  // Picker emoji choices.
  function dismissPanel(wrap) {
    if (!wrap) return;
    wrap.classList.remove('is-open');
    if (wrap.contains(document.activeElement)) document.activeElement.blur();
    wrap.classList.add('is-dismissed');
    wrap.addEventListener('pointerleave', () => wrap.classList.remove('is-dismissed'), { once: true });
  }
  document.querySelectorAll('.reaction-pop-btn').forEach(b => b.addEventListener('click', (e) => {
    e.preventDefault(); e.stopPropagation();
    dismissPanel(b.closest('.reaction-wrap'));
    react(b.dataset.react);
  }));

  // Long-press opens the picker on touch (desktop uses hover).
  document.querySelectorAll('.reaction-wrap').forEach(wrap => {
    let t = null;
    wrap.addEventListener('pointerdown', (e) => {
      if (e.pointerType === 'mouse') return;
      t = setTimeout(() => wrap.classList.add('is-open'), 350);
    });
    ['pointerup','pointerleave','pointercancel'].forEach(ev =>
      wrap.addEventListener(ev, () => { if (t) { clearTimeout(t); t = null; } }));
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.reaction-wrap')) {
      document.querySelectorAll('.reaction-wrap.is-open').forEach(w => w.classList.remove('is-open'));
    }
  });

  /* ─────────── Comments ─────────── */
  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function avatarHtml(user, size = 'h-10 w-10', text = 'text-xs') {
    if (user?.profile_picture) {
      return `<img src="${escapeHtml(user.profile_picture)}" class="${size} shrink-0 rounded-full object-cover" alt="">`;
    }
    const initial = (user?.username || 'U').charAt(0).toUpperCase();
    return `<span class="grid ${size} shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 ${text} font-bold text-white">${escapeHtml(initial)}</span>`;
  }
  function bumpHeadCount() {
    const el = document.getElementById('commentHeadCount');
    const el2 = document.getElementById('commentCount');
    const m = el?.textContent.match(/\d+/);
    const next = (m ? parseInt(m[0], 10) : 0) + 1;
    if (el) el.textContent = `(${next.toLocaleString()})`;
    if (el2) el2.textContent = next.toLocaleString();
  }
  function renderTopComment(c) {
    const tpl = document.createElement('div');
    tpl.className = 'comment-thread';
    tpl.dataset.commentId = c.id;
    tpl.innerHTML = `
      <div class="flex gap-3">
        ${avatarHtml(c.user, 'h-10 w-10')}
        <div class="flex-1">
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <div class="flex items-baseline gap-2">
              <span class="text-sm font-semibold text-slate-900">${escapeHtml(c.user?.username)}</span>
              <span class="text-xs text-slate-400">${escapeHtml(c.created_at)}</span>
            </div>
            <p class="mt-1 text-sm text-slate-700">${escapeHtml(c.body)}</p>
          </div>
          ${authed ? `
          <div class="mt-1 flex items-center gap-3 pl-2 text-xs">
            <button type="button" class="reply-trigger font-semibold text-slate-500 hover:text-brand-600" data-parent="${c.id}">Reply</button>
          </div>
          <form class="reply-form mt-3 flex items-start gap-2 pl-2" data-parent="${c.id}" hidden>
            <input type="text" maxlength="1000" required placeholder="Reply to @${escapeHtml(c.user?.username)}…" class="input flex-1 text-sm">
            <button type="submit" class="btn-primary !py-2 !px-3 text-xs">Reply</button>
            <button type="button" class="reply-cancel rounded-lg px-2 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-100">Cancel</button>
          </form>` : ''}
          <div class="reply-list mt-3 space-y-3 border-l-2 border-slate-100 pl-4"></div>
        </div>
      </div>`;
    return tpl;
  }
  function renderReply(c) {
    const tpl = document.createElement('div');
    tpl.className = 'flex gap-3';
    tpl.dataset.replyId = c.id;
    tpl.innerHTML = `
      ${avatarHtml(c.user, 'h-8 w-8', 'text-[11px]')}
      <div class="flex-1 rounded-2xl bg-white border border-slate-100 px-3 py-2">
        <div class="flex items-baseline gap-2">
          <span class="text-xs font-semibold text-slate-900">${escapeHtml(c.user?.username)}</span>
          <span class="text-[11px] text-slate-400">${escapeHtml(c.created_at)}</span>
        </div>
        <p class="mt-1 text-sm text-slate-700">${escapeHtml(c.body)}</p>
      </div>`;
    return tpl;
  }

  async function postComment(body, parentId) {
    const r = await fetch(`${window.__APP__.urls.api.posts}/${postId}/comments`, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json','Accept':'application/json',
        'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ body, parent_id: parentId || null }),
    });
    const data = await r.json();
    if (!r.ok) throw new Error(data?.message || 'Error');
    return data.comment;
  }

  // Top-level comment
  const form = document.getElementById('aCommentForm');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('aCommentBody');
    const body = input.value.trim();
    if (!body) return;
    try {
      const c = await postComment(body, null);
      const empty = document.querySelector('#aComments .rounded-2xl.border-dashed');
      if (empty) empty.remove();
      document.getElementById('aComments').prepend(renderTopComment(c));
      input.value = '';
      bumpHeadCount();
      window.Tanbat?.toast?.('Comment posted!', 'ok');
    } catch (err) { window.Tanbat?.toast?.(err.message, 'bad'); }
  });

  // Reply trigger (event delegation — handles both pre-rendered & new comments)
  document.getElementById('aComments')?.addEventListener('click', (e) => {
    const trig = e.target.closest('.reply-trigger');
    if (trig) {
      const pid = trig.dataset.parent;
      const thread = trig.closest('.comment-thread');
      const f = thread?.querySelector(`.reply-form[data-parent="${pid}"]`);
      if (f) { f.hidden = false; f.querySelector('input')?.focus(); }
      return;
    }
    const cancel = e.target.closest('.reply-cancel');
    if (cancel) {
      const f = cancel.closest('.reply-form');
      if (f) { f.hidden = true; f.querySelector('input').value = ''; }
    }
  });

  // Reply submit (delegated)
  document.getElementById('aComments')?.addEventListener('submit', async (e) => {
    const f = e.target.closest('.reply-form');
    if (!f) return;
    e.preventDefault();
    const pid = f.dataset.parent;
    const inp = f.querySelector('input');
    const body = inp.value.trim();
    if (!body) return;
    try {
      const c = await postComment(body, pid);
      const thread = f.closest('.comment-thread');
      thread?.querySelector('.reply-list')?.append(renderReply(c));
      inp.value = '';
      f.hidden = true;
      bumpHeadCount();
      window.Tanbat?.toast?.('Reply posted!', 'ok');
    } catch (err) { window.Tanbat?.toast?.(err.message, 'bad'); }
  });

})();
</script>
@endpush
@endsection
