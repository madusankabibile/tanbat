@extends('omrms.layout')

@php use App\Support\Omrms; @endphp

@section('title', $category->name . ' Articles — OMRMS')

@push('head')
@php $curl = $articles->currentPage() > 1 ? $articles->url($articles->currentPage()) : Omrms::url('/category/' . $category->slug); @endphp
<link rel="canonical" href="{{ $curl }}">
<meta name="robots" content="index, follow, max-image-preview:large">
<meta name="description" content="{{ $category->name }} articles on OMRMS.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="OMRMS">
<meta property="og:title" content="{{ $category->name }} Articles — OMRMS">
<meta property="og:url" content="{{ Omrms::url('/category/' . $category->slug) }}">
@if($prev = $articles->previousPageUrl())<link rel="prev" href="{{ $prev }}">@endif
@if($next = $articles->nextPageUrl())<link rel="next" href="{{ $next }}">@endif
@endpush

@section('content')
  <p class="omr-crumb"><a href="{{ Omrms::url('/categories') }}">Categories</a> · {{ $category->name }}</p>
  <h1 class="omr-page-title">{{ $category->name }}</h1>
  <p class="omr-page-sub u-sans">{{ number_format($articles->total()) }} article{{ $articles->total() === 1 ? '' : 's' }}</p>

  <div class="omr-grid">
    @forelse($articles as $post)
      @include('omrms.partials.article-card', ['post' => $post])
    @empty
      <p class="u-sans" style="color:var(--muted)">No articles in this category yet.</p>
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
