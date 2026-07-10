{{--
    Reusable SEO / social meta block.

    Include it from inside a page's @push('head') so the tags land in <head>:

        @push('head')
          @include('partials._seo', [
            'title'       => $book->title,
            'description' => $summary,
            'url'         => $book->post->permalink(),
            'image'       => $book->cover_url,
            'type'        => 'book',
            'jsonLd'      => $ld,            // array, or list of arrays, or null
          ])
        @endpush

    Every parameter is optional; anything omitted falls back to config/seo.php
    or is skipped entirely (an absent image emits no og:image rather than a
    broken preview). This renders canonical + robots + description + Open Graph +
    Twitter Card + JSON-LD, i.e. the same surface the article views hand-roll —
    so give a page this partial instead of leaving it with only a <title>.

    Note: this does NOT emit the <title> element. Keep that in @section('title')
    so the browser tab and this block stay in sync from one source.
--}}
@php
    $seoName   = config('seo.site_name', config('app.name', 'Tanbat'));
    $seoLocale = config('seo.locale', 'en_US');

    // Canonical URL: default to the current path WITHOUT its query string, so
    // filters and tracking params never fragment a page into "duplicate" URLs.
    $seoUrl = $url ?? url()->current();

    // One description, cleaned and clamped to ~160 chars — long enough for a
    // rich snippet, short enough that Google won't truncate mid-word.
    $seoDescription = \Illuminate\Support\Str::of((string) ($description ?? config('seo.description')))
        ->stripTags()->squish()->limit(160);

    $seoTitle = trim((string) ($title ?? ''));          // og/twitter title; may be blank
    $seoType  = $type ?? 'website';
    $seoImage = $image ?? config('seo.image');
    $seoImageAlt = $imageAlt ?? ($seoTitle ?: $seoName);
    $seoRobots = $robots
        ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    $seoTwitter = config('seo.twitter');

    // Accept a single JSON-LD object or a list of them; drop empties.
    $seoJsonLd = collect(($jsonLd ?? []) === [] ? [] : (array_is_list($jsonLd ?? []) ? $jsonLd : [$jsonLd]))
        ->filter()
        ->values();
@endphp
<link rel="canonical" href="{{ $seoUrl }}">
<meta name="robots" content="{{ $seoRobots }}">
<meta name="description" content="{{ $seoDescription }}">
@isset($author)<meta name="author" content="{{ $author }}">@endisset

<meta property="og:type" content="{{ $seoType }}">
<meta property="og:site_name" content="{{ $seoName }}">
<meta property="og:locale" content="{{ $seoLocale }}">
@if($seoTitle !== '')<meta property="og:title" content="{{ $seoTitle }}">@endif
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoUrl }}">
@if($seoImage)
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:alt" content="{{ $seoImageAlt }}">
@endif

<meta name="twitter:card" content="{{ $seoImage ? 'summary_large_image' : 'summary' }}">
@if($seoTwitter)<meta name="twitter:site" content="{{ $seoTwitter }}">@endif
@if($seoTitle !== '')<meta name="twitter:title" content="{{ $seoTitle }}">@endif
<meta name="twitter:description" content="{{ $seoDescription }}">
@if($seoImage)
<meta name="twitter:image" content="{{ $seoImage }}">
<meta name="twitter:image:alt" content="{{ $seoImageAlt }}">
@endif
@foreach($seoJsonLd as $block)
<script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
