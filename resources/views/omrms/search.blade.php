@extends('omrms.layout')

@php use App\Support\Omrms; @endphp

@section('title', ($q !== '' ? 'Search: ' . $q : 'Search') . ' — OMRMS')

@push('head')
@php
    $base = Omrms::url('/search');
    $canonical = $q !== '' ? $base . '?q=' . urlencode($q) : $base;
    $desc = $q !== ''
        ? 'Articles on OMRMS matching “' . $q . '”.'
        : 'Search articles on OMRMS.';
@endphp
<link rel="canonical" href="{{ $canonical }}">
{{-- Indexable so these article-search pages can surface in Google. --}}
<meta name="robots" content="index, follow, max-image-preview:large">
<meta name="description" content="{{ $desc }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="OMRMS">
<meta property="og:title" content="{{ $q !== '' ? 'Search: ' . $q . ' — OMRMS' : 'Search — OMRMS' }}">
<meta property="og:url" content="{{ $canonical }}">
@if($articles && $articles->previousPageUrl())<link rel="prev" href="{{ $articles->previousPageUrl() }}">@endif
@if($articles && $articles->nextPageUrl())<link rel="next" href="{{ $articles->nextPageUrl() }}">@endif
@endpush

@section('content')
  @if($q === '')
    <h1 class="omr-page-title">Search articles</h1>
    <p class="omr-page-sub u-sans">Type a keyword in the search box above to find articles.</p>
  @else
    <p class="omr-crumb"><a href="{{ Omrms::url('/') }}">Home</a> · Search</p>
    <h1 class="omr-page-title">Results for “{{ $q }}”</h1>
    <p class="omr-page-sub u-sans">{{ number_format($articles->total()) }} article{{ $articles->total() === 1 ? '' : 's' }} found</p>

    @if($articles->total() === 0)
      <div class="omr-noresults u-sans">
        <p>No articles matched your search.</p>
        <a class="omr-back" href="{{ Omrms::url('/') }}">← Back to all articles</a>
      </div>
    @else
      <div class="omr-grid">
        @foreach($articles as $post)
          @include('omrms.partials.article-card', ['post' => $post])
        @endforeach
      </div>

      @if($articles->hasPages())
        <nav class="omr-pager">
          @if($articles->onFirstPage())
            <span class="is-disabled">← Prev</span>
          @else
            <a href="{{ $articles->previousPageUrl() }}" rel="prev">← Prev</a>
          @endif
          <span>Page {{ $articles->currentPage() }} of {{ $articles->lastPage() }}</span>
          @if($articles->hasMorePages())
            <a href="{{ $articles->nextPageUrl() }}" rel="next">Next →</a>
          @else
            <span class="is-disabled">Next →</span>
          @endif
        </nav>
      @endif
    @endif
  @endif
@endsection
