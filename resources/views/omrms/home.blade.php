@extends('omrms.layout')

@php use App\Support\Omrms; @endphp

@section('title', 'OMRMS — Latest Articles')

@push('head')
@php
    $home = Omrms::url('/');
    $desc = 'Read the latest articles on OMRMS — a growing library of stories and guides across every topic.';
    $ld = [
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => 'OMRMS',
        'url'      => $home,
    ];
@endphp
<link rel="canonical" href="{{ $articles->currentPage() > 1 ? $articles->url($articles->currentPage()) : $home }}">
<meta name="robots" content="index, follow, max-image-preview:large">
<meta name="description" content="{{ $desc }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="OMRMS">
<meta property="og:title" content="OMRMS — Latest Articles">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $home }}">
<meta name="twitter:card" content="summary">
@if($prev = $articles->previousPageUrl())<link rel="prev" href="{{ $prev }}">@endif
@if($next = $articles->nextPageUrl())<link rel="next" href="{{ $next }}">@endif
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
  <h1 class="omr-page-title">Latest Articles</h1>
  <p class="omr-page-sub u-sans">Handpicked reads, refreshed daily.</p>

  <div class="omr-grid">
    {{-- First cell is a native ad styled as a card --}}
    @include('omrms.partials.native-card')

    @forelse($articles as $post)
      @include('omrms.partials.article-card', ['post' => $post])
    @empty
      <p class="u-sans" style="color:var(--muted)">No articles published yet.</p>
    @endforelse
  </div>

  @if($articles->hasPages())
    <nav class="omr-pager">
      @if($articles->onFirstPage())
        <span class="is-disabled">← Newer</span>
      @else
        <a href="{{ $articles->previousPageUrl() }}" rel="prev">← Newer</a>
      @endif

      <span>Page {{ $articles->currentPage() }} of {{ $articles->lastPage() }}</span>

      @if($articles->hasMorePages())
        <a href="{{ $articles->nextPageUrl() }}" rel="next">Older →</a>
      @else
        <span class="is-disabled">Older →</span>
      @endif
    </nav>
  @endif
@endsection
