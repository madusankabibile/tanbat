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
@endsection
