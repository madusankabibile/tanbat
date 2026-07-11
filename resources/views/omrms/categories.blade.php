@extends('omrms.layout')

@php use App\Support\Omrms; @endphp

@section('title', 'Article Categories — OMRMS')

@push('head')
<link rel="canonical" href="{{ Omrms::url('/categories') }}">
<meta name="robots" content="index, follow">
<meta name="description" content="Browse OMRMS articles by category — pick a topic and dive in.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="OMRMS">
<meta property="og:title" content="Article Categories — OMRMS">
<meta property="og:url" content="{{ Omrms::url('/categories') }}">
@endpush

@section('content')
  <h1 class="omr-page-title">Categories</h1>
  <p class="omr-page-sub u-sans">Browse articles by topic.</p>

  <div class="omr-cat-grid">
    @forelse($categories as $cat)
      <a class="omr-cat-card" href="{{ Omrms::url('/category/' . $cat->slug) }}">
        <b>{{ $cat->name }}</b>
        <span class="omr-cat-count">{{ number_format($cat->articles_count) }}</span>
      </a>
    @empty
      <p class="u-sans" style="color:var(--muted)">No categories yet.</p>
    @endforelse
  </div>
@endsection
