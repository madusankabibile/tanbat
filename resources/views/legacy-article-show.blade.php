@extends('layouts.app')
@section('title', $article->title . ' — Tanbat')

@php
    $coverUrl = $article->coverUrl();
    $canonical = route('legacy.article', ['postId' => $article->old_post_id, 'slug' => $article->slug]);
    $desc = \Illuminate\Support\Str::of(strip_tags((string) $article->body))->squish()->limit(200);
    $authorNm = $article->user?->name ?: ($article->author_name ?: $article->author_username);
    $authorUrl = $article->user?->username ? route('profile', $article->user->username) : null;
    $tags = $article->tagList();

    $ld = array_filter([
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
        'headline'         => \Illuminate\Support\Str::limit($article->title, 110, ''),
        'description'      => (string) $desc,
        'image'            => $coverUrl ? [$coverUrl] : null,
        'datePublished'    => optional($article->published_at)->toIso8601String(),
        'dateModified'     => optional($article->updated_at)->toIso8601String(),
        'author'           => $authorNm ? array_filter(['@type' => 'Person', 'name' => $authorNm, 'url' => $authorUrl]) : null,
        'publisher'        => [
            '@type' => 'Organization', 'name' => 'Tanbat',
            'logo'  => ['@type' => 'ImageObject', 'url' => asset('favicon.ico')],
        ],
        'articleSection'   => $article->category_name,
        'keywords'         => !empty($tags) ? implode(', ', $tags) : null,
    ], fn ($v) => !is_null($v) && $v !== '' && $v !== []);
@endphp

@push('head')
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<meta name="description" content="{{ $desc }}">
@if($authorNm)<meta name="author" content="{{ $authorNm }}">@endif
@if(!empty($tags))<meta name="keywords" content="{{ implode(', ', $tags) }}">@endif
<meta property="og:type" content="article">
<meta property="og:site_name" content="Tanbat">
<meta property="og:title" content="{{ $article->title }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $canonical }}">
@if($coverUrl)
<meta property="og:image" content="{{ $coverUrl }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="{{ $coverUrl }}">
@else
<meta name="twitter:card" content="summary">
@endif
<meta name="twitter:title" content="{{ $article->title }}">
<meta name="twitter:description" content="{{ $desc }}">
@if($article->published_at)<meta property="article:published_time" content="{{ $article->published_at->toIso8601String() }}">@endif
@if($article->category_name)<meta property="article:section" content="{{ $article->category_name }}">@endif
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<style>
  .prose-tanbat { color:#1E1B4B; font-size:17px; line-height:1.78; word-wrap:break-word; overflow-wrap:break-word; }
  .prose-tanbat h1,.prose-tanbat h2,.prose-tanbat h3 { font-weight:800; letter-spacing:-.02em; line-height:1.25; margin:1.6em 0 .6em; }
  .prose-tanbat h1{font-size:30px;} .prose-tanbat h2{font-size:24px;} .prose-tanbat h3{font-size:20px;}
  .prose-tanbat p{margin:.9em 0;}
  .prose-tanbat a{color:#6C63FF; text-decoration:underline; text-underline-offset:3px; word-break:break-word;}
  .prose-tanbat img{max-width:100%; height:auto; border-radius:12px; margin:1.4em auto; display:block; box-shadow:0 4px 16px rgba(20,20,50,.06);}
  .prose-tanbat blockquote{border-left:4px solid #6C63FF; background:#EEF0FF; border-radius:8px; padding:12px 18px; margin:1.4em 0; color:#36318A; font-style:italic;}
  .prose-tanbat pre{background:#0F172A; color:#E2E8F0; padding:14px 16px; border-radius:12px; overflow:auto; font-family:ui-monospace,Menlo,monospace; font-size:13px; line-height:1.65;}
  .prose-tanbat ul,.prose-tanbat ol{padding-left:1.4em; margin:1em 0;} .prose-tanbat li{margin:.35em 0;}
  .prose-tanbat table{max-width:100%; overflow-x:auto; display:block; border-collapse:collapse;}
  .prose-tanbat iframe{max-width:100%;}
</style>
@endpush

@section('content')
@auth
  @include('partials.navbar')
@else
  @includeIf('partials.guest-bar')
@endauth

<main class="mx-auto w-full max-w-3xl px-4 py-8 sm:py-12">
  <nav class="mb-6 text-sm text-slate-500">
    <a href="{{ url('/') }}" class="hover:text-brand-600">Home</a>
    <span class="mx-1.5 text-slate-300">/</span>
    <span>Blog</span>
    @if($article->category_name)
      <span class="mx-1.5 text-slate-300">/</span>
      <span class="text-slate-700">{{ $article->category_name }}</span>
    @endif
  </nav>

  <article>
    @if($article->category_name)
      <div class="mb-3"><span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-700">{{ $article->category_name }}</span></div>
    @endif

    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ $article->title }}</h1>

    <div class="mt-5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
      @if($authorNm)
        @if($authorUrl)
          <a href="{{ $authorUrl }}" class="font-semibold text-slate-700 hover:text-brand-600">{{ $authorNm }}</a>
        @else
          <span class="font-semibold text-slate-700">{{ $authorNm }}</span>
        @endif
        <span class="text-slate-300">·</span>
      @endif
      @if($article->published_at)
        <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->format('M j, Y') }}</time>
        <span class="text-slate-300">·</span>
      @endif
      <span>{{ number_format($article->views) }} views</span>
    </div>

    @if($coverUrl)
      <img src="{{ $coverUrl }}" class="mt-8 w-full rounded-2xl object-cover shadow-card" alt="{{ $article->title }}" loading="lazy">
    @endif

    <div class="prose-tanbat mt-10">{!! $article->body !!}</div>

    @if(!empty($tags))
      <div class="mt-10 flex flex-wrap items-center gap-2">
        @foreach($tags as $t)
          <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">#{{ $t }}</span>
        @endforeach
      </div>
    @endif
  </article>

  @if($related->isNotEmpty())
    <section class="mt-14 border-t border-slate-100 pt-8">
      <h2 class="mb-4 text-lg font-bold text-slate-900">Related articles</h2>
      <div class="grid gap-3 sm:grid-cols-2">
        @foreach($related as $r)
          <a href="{{ route('legacy.article', ['postId' => $r->old_post_id, 'slug' => $r->slug]) }}"
             class="flex gap-3 rounded-xl p-2 transition hover:bg-slate-50">
            @if($r->cover)
              <img src="{{ (\App\Models\LegacyArticle::make(['cover' => $r->cover]))->coverUrl() }}" class="h-16 w-16 flex-shrink-0 rounded-lg object-cover" alt="" loading="lazy">
            @else
              <span class="h-16 w-16 flex-shrink-0 rounded-lg bg-gradient-to-br from-brand-50 to-rose-50"></span>
            @endif
            <span class="min-w-0">
              <span class="line-clamp-2 text-sm font-semibold text-slate-800">{{ $r->title }}</span>
              @if($r->published_at)<span class="mt-1 block text-xs text-slate-400">{{ $r->published_at->format('M j, Y') }}</span>@endif
            </span>
          </a>
        @endforeach
      </div>
    </section>
  @endif
</main>
@endsection
