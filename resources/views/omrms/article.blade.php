@extends('omrms.layout')

@php use App\Support\Omrms; @endphp

@section('title', $a['title'] . ' — OMRMS')

@push('head')
@php
    $ogImage = $a['cover'];
    $desc    = $a['description'];
    $author  = $a['author'];
    $keywords = $a['keywords'] ?? [];
    $published = $a['publishedAt'];
    $updated   = $a['updatedAt'];

    // Schema.org BlogPosting — the primary rich-result signal for Google.
    $ld = array_filter([
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $a['url']],
        'headline'         => \Illuminate\Support\Str::limit($a['title'], 110, ''),
        'description'      => $desc,
        'image'            => $ogImage ? [$ogImage] : null,
        'datePublished'    => optional($published)->toIso8601String(),
        'dateModified'     => optional($updated ?: $published)->toIso8601String(),
        'author'           => $author ? ['@type' => 'Person', 'name' => $author] : null,
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
<div class="omr-article">
  {{-- ─────────── MAIN ARTICLE ─────────── --}}
  <article class="omr-main">
    @if($a['category'])<div class="omr-eyebrow">{{ $a['category'] }}</div>@endif
    <h1 class="omr-title">{{ $a['title'] }}</h1>
    <div class="omr-byline">
      @if($a['author'])<span>By {{ $a['author'] }}</span><span class="dot"></span>@endif
      @if($a['publishedAt'])<span>{{ $a['publishedAt']->format('F j, Y') }}</span>@endif
    </div>

    @if($a['cover'])
      <figure class="omr-hero"><img src="{{ $a['cover'] }}" alt="{{ $a['title'] }}"></figure>
    @endif

    <div class="omr-prose">
      {!! $a['body'] !!}
    </div>

    <a class="omr-back" href="{{ Omrms::url('/') }}">← Back to all articles</a>
  </article>

  {{-- ─────────── RIGHT RAIL: 2 ADS + RELATED ─────────── --}}
  <aside class="omr-rail">
    <div class="omr-ad-wrap">
      <div class="omr-ad-label">Advertisement</div>
      @include('omrms.partials.ad-square')
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
              <b>{{ \Illuminate\Support\Str::limit($rel['title'], 70) }}</b>
            </a>
          @endforeach
        </div>
      </div>
    @endif

    <div class="omr-ad-wrap">
      <div class="omr-ad-label">Advertisement</div>
      @include('omrms.partials.ad-native')
    </div>
  </aside>
</div>
@endsection
