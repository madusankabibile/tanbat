@extends('omrms.layout')

@php use App\Support\Omrms; use Illuminate\Support\Str; @endphp

@section('title', $a['title'] . ' — OMRMS')

@push('head')
@php
    $ogImage  = $a['cover'];
    $desc     = $a['description'];
    $author   = $a['author'];
    $keywords = $a['keywords'] ?? [];
    $published = $a['publishedAt'];
    $updated   = $a['updatedAt'];

    // Schema.org BlogPosting — the primary rich-result signal for Google.
    $ld = array_filter([
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $a['url']],
        'headline'         => Str::limit($a['title'], 110, ''),
        'description'      => $desc,
        'image'            => $ogImage ? [$ogImage] : null,
        'datePublished'    => optional($published)->toIso8601String(),
        'dateModified'     => optional($updated ?: $published)->toIso8601String(),
        'author'           => $author ? array_filter([
            '@type' => 'Person', 'name' => $author, 'url' => $a['authorUrl'],
        ]) : null,
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => 'OMRMS',
            'url'   => Omrms::url('/'),
            'logo'  => ['@type' => 'ImageObject', 'url' => Omrms::url('/favicon.ico')],
        ],
        'articleSection'   => $a['category'],
        'keywords'         => !empty($keywords) ? implode(', ', $keywords) : null,
    ], fn ($v) => !is_null($v) && $v !== '' && $v !== []);
@endphp
<link rel="canonical" href="{{ $a['url'] }}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="description" content="{{ $desc }}">
@if($author)<meta name="author" content="{{ $author }}">@endif
@if(!empty($keywords))<meta name="keywords" content="{{ implode(', ', $keywords) }}">@endif
<meta property="og:type" content="article">
<meta property="og:site_name" content="OMRMS">
<meta property="og:locale" content="en_US">
<meta property="og:title" content="{{ $a['title'] }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $a['url'] }}">
@if($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:secure_url" content="{{ $ogImage }}">
<meta property="og:image:alt" content="{{ $a['title'] }}">
@endif
@if($published)<meta property="article:published_time" content="{{ $published->toIso8601String() }}">@endif
@if($updated)<meta property="article:modified_time" content="{{ $updated->toIso8601String() }}">@endif
@if($author)<meta property="article:author" content="{{ $author }}">@endif
@if($a['category'])<meta property="article:section" content="{{ $a['category'] }}">@endif
@foreach($keywords as $t)<meta property="article:tag" content="{{ $t }}">
@endforeach
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $a['title'] }}">
<meta name="twitter:description" content="{{ $desc }}">
@if($ogImage)<meta name="twitter:image" content="{{ $ogImage }}">@endif
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
@php [$half1, $half2] = Omrms::halves($a['body']); @endphp
<div class="omr-article">
  {{-- ─────────── MAIN ARTICLE ─────────── --}}
  <article class="omr-main">
    @if($a['category'])
      @if($a['categorySlug'])
        <a class="omr-eyebrow" href="{{ Omrms::url('/category/' . $a['categorySlug']) }}">{{ $a['category'] }}</a>
      @else
        <div class="omr-eyebrow">{{ $a['category'] }}</div>
      @endif
    @endif

    <h1 class="omr-title">{{ $a['title'] }}</h1>

    <div class="omr-byline">
      @if($a['author'])
        <span class="omr-author">
          @if($a['authorAvatar'])
            <img class="omr-avatar" src="{{ $a['authorAvatar'] }}" alt="{{ $a['author'] }}">
          @else
            <span class="omr-avatar omr-avatar-ph">{{ Str::substr($a['author'], 0, 1) }}</span>
          @endif
          @if($a['authorUrl'])
            <a href="{{ $a['authorUrl'] }}" rel="author">{{ $a['author'] }}</a>
          @else
            <span>{{ $a['author'] }}</span>
          @endif
        </span>
        <span class="dot"></span>
      @endif
      @if($a['publishedAt'])<span>{{ $a['publishedAt']->format('F j, Y') }}</span><span class="dot"></span>@endif
      <span>{{ $a['stats']['readTime'] }} min read</span>
    </div>

    @if($a['cover'])
      <figure class="omr-hero"><img src="{{ $a['cover'] }}" alt="{{ $a['title'] }}"></figure>
    @endif

    <div class="omr-prose">
      {!! $half1 !!}

      @if($half2 !== '')
        {{-- Native ad in the middle of the article — mobile only --}}
        <div class="omr-midad omr-mobile-only">
          <div class="omr-ad-label">Advertisement</div>
          @include('omrms.partials.ad-native')
        </div>

        {!! $half2 !!}
      @endif
    </div>

    {{-- ─────────── SOCIAL SHARE ─────────── --}}
    @php
        $shUrl   = rawurlencode($a['url']);
        $shTitle = rawurlencode($a['title']);
        $shImg   = rawurlencode((string) ($a['cover'] ?? ''));
        $shText  = rawurlencode($a['title'] . ' — ' . $a['url']);
    @endphp
    <section class="omr-share" aria-label="Share this article">
      <span class="omr-share-label">Share this article</span>
      <div class="omr-share-btns">
        <a class="omr-sh sh-fb" target="_blank" rel="noopener nofollow" aria-label="Share on Facebook"
           href="https://www.facebook.com/sharer/sharer.php?u={{ $shUrl }}">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 22 12Z"/></svg>
        </a>
        <a class="omr-sh sh-x" target="_blank" rel="noopener nofollow" aria-label="Share on X"
           href="https://twitter.com/intent/tweet?url={{ $shUrl }}&text={{ $shTitle }}">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 2H22l-7.3 8.3L23 22h-6.8l-5.3-6.9L4.8 22H2l7.8-8.9L1.5 2h6.9l4.8 6.4L18.9 2Zm-2.4 18h1.7L7.6 3.8H5.8L16.5 20Z"/></svg>
        </a>
        <a class="omr-sh sh-wa" target="_blank" rel="noopener nofollow" aria-label="Share on WhatsApp"
           href="https://api.whatsapp.com/send?text={{ $shText }}">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.3A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.2-.4.2-.4.6-1.2.1-.2 0-.4 0-.5l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3c-.3.3-.9.9-.9 2.1s.9 2.5 1 2.6c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.2.7 3 .6.5 0 1.4-.6 1.6-1.1.2-.6.2-1 .1-1.1 0-.1-.2-.2-.5-.3Z"/></svg>
        </a>
        <a class="omr-sh sh-tg" target="_blank" rel="noopener nofollow" aria-label="Share on Telegram"
           href="https://t.me/share/url?url={{ $shUrl }}&text={{ $shTitle }}">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M21.9 4.3 18.7 19.5c-.2 1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.3-4.9 9-8.1c.4-.3-.1-.5-.6-.2L6.4 13.4l-4.8-1.5c-1-.3-1-1 .2-1.5L20.6 3c.9-.3 1.6.2 1.3 1.3Z"/></svg>
        </a>
        <a class="omr-sh sh-in" target="_blank" rel="noopener nofollow" aria-label="Share on LinkedIn"
           href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shUrl }}">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.9 8.4H3.6V21h3.3V8.4ZM5.2 3a1.9 1.9 0 1 0 0 3.9 1.9 1.9 0 0 0 0-3.9ZM21 13.9c0-3.2-1.7-4.7-4-4.7-1.8 0-2.6 1-3.1 1.7V8.4H10.6V21h3.3v-7c0-.4 0-.7.1-1 .3-.7.9-1.4 1.9-1.4 1.4 0 1.9 1 1.9 2.6V21H21v-7.1Z"/></svg>
        </a>
        <a class="omr-sh sh-pin" target="_blank" rel="noopener nofollow" aria-label="Share on Pinterest"
           href="https://pinterest.com/pin/create/button/?url={{ $shUrl }}&media={{ $shImg }}&description={{ $shTitle }}">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-3.6 19.3c-.1-.8-.2-2 0-2.9l1.2-5s-.3-.6-.3-1.5c0-1.4.8-2.4 1.8-2.4.9 0 1.3.6 1.3 1.4 0 .9-.5 2.2-.8 3.4-.2 1 .5 1.8 1.5 1.8 1.8 0 3.1-1.9 3.1-4.6 0-2.4-1.7-4.1-4.2-4.1-2.8 0-4.5 2.1-4.5 4.3 0 .9.3 1.8.7 2.3v.5l-.3 1.1c0 .2-.2.3-.4.2-1.2-.6-2-2.4-2-3.8 0-3.1 2.2-5.9 6.5-5.9 3.4 0 6.1 2.4 6.1 5.7 0 3.4-2.2 6.2-5.2 6.2-1 0-2-.5-2.3-1.2l-.6 2.4c-.2.9-.8 2-1.2 2.6A10 10 0 1 0 12 2Z"/></svg>
        </a>
        <button type="button" class="omr-sh sh-copy" data-url="{{ $a['url'] }}" aria-label="Copy link">
          <svg class="ic-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
          <svg class="ic-ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span class="sh-copy-tip">Copied!</span>
        </button>
      </div>
    </section>

    <a class="omr-back" href="{{ Omrms::url('/') }}">← Back to all articles</a>
  </article>

  {{-- ─────────── RIGHT RAIL: AD + STATS + RELATED ─────────── --}}
  <aside class="omr-rail">
    <div class="omr-ad-wrap">
      <div class="omr-ad-label">Advertisement</div>
      @include('omrms.partials.ad-square')
    </div>

    {{-- Statistics card --}}
    <div class="omr-stats">
      <h3 class="omr-rail-h">Article stats</h3>
      <div class="omr-stat-grid">
        <div class="omr-stat"><b>{{ number_format($a['stats']['views']) }}</b><span>Views</span></div>
        <div class="omr-stat"><b>{{ $a['stats']['readTime'] }}</b><span>Min read</span></div>
        @if($a['stats']['likes'] > 0)
          <div class="omr-stat"><b>{{ number_format($a['stats']['likes']) }}</b><span>Likes</span></div>
        @endif
        @if($a['stats']['comments'] > 0)
          <div class="omr-stat"><b>{{ number_format($a['stats']['comments']) }}</b><span>Comments</span></div>
        @endif
        @if($a['publishedAt'])
          <div class="omr-stat"><b>{{ $a['publishedAt']->format('M Y') }}</b><span>Published</span></div>
        @endif
        @if($a['category'])
          <div class="omr-stat"><b style="font-size:14px">{{ Str::limit($a['category'], 16) }}</b><span>Category</span></div>
        @endif
      </div>
    </div>

    @if(!empty($a['related']))
      <div>
        <h3 class="omr-rail-h">Related articles</h3>
        <div class="omr-rel">
          @foreach(array_slice($a['related'], 0, 5) as $rel)
            <a href="{{ $rel['url'] }}">
              <span class="omr-rel-fig">
                @if($rel['cover'])<img src="{{ $rel['cover'] }}" alt="{{ $rel['title'] }}" loading="lazy">@endif
              </span>
              <b>{{ Str::limit($rel['title'], 70) }}</b>
            </a>
          @endforeach
        </div>
      </div>
    @endif
  </aside>
</div>

<script>
  (function () {
    document.querySelectorAll('.sh-copy').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-url');
        var done = function () {
          btn.classList.add('is-copied');
          setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(done, done);
        } else {
          var t = document.createElement('textarea');
          t.value = url; document.body.appendChild(t); t.select();
          try { document.execCommand('copy'); } catch (e) {}
          document.body.removeChild(t); done();
        }
      });
    });
  })();
</script>
@endsection
