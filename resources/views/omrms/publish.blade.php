@extends('omrms.layout')

@php use App\Support\Omrms; @endphp

@section('title', 'How to Publish an Article — OMRMS')

@push('head')
<link rel="canonical" href="{{ Omrms::url('/how-to-publish') }}">
<meta name="robots" content="index, follow">
<meta name="description" content="How to publish your article so it appears on OMRMS — create a free account on Tanbat, write your article, and it goes live here automatically.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="OMRMS">
<meta property="og:title" content="How to Publish an Article — OMRMS">
<meta property="og:url" content="{{ Omrms::url('/how-to-publish') }}">
@endpush

@section('content')
<div class="omr-doc">
  <h1>Publish your article</h1>
  <p class="lead">
    OMRMS is powered by the <a class="inline" href="https://tanbat.com/" rel="noopener" target="_blank">Tanbat</a>
    community. Articles are written on Tanbat and appear here automatically — publishing is free and takes a few minutes.
  </p>

  <div class="omr-step">
    <div class="n">1</div>
    <div>
      <h3>Create a free Tanbat account (or log in)</h3>
      <p>Head to <a class="inline" href="https://tanbat.com/" rel="noopener" target="_blank">tanbat.com</a> and use
         <strong>Create free account</strong> to register, or <strong>log in</strong> if you already have one.</p>
    </div>
  </div>

  <div class="omr-step">
    <div class="n">2</div>
    <div>
      <h3>Open the article editor</h3>
      <p>Once logged in, go to <strong>Write an article</strong>. Give it a clear title, a cover image, pick a
         category, and write your content.</p>
    </div>
  </div>

  <div class="omr-step">
    <div class="n">3</div>
    <div>
      <h3>Publish</h3>
      <p>Hit <strong>Publish</strong>. Your article goes live on Tanbat and is automatically listed here on OMRMS —
         on the home page and under its category — with your name and profile linked back to you.</p>
    </div>
  </div>

  <p style="margin-top:26px">
    <a class="omr-cta" href="https://tanbat.com/articles/create" rel="noopener" target="_blank">Start writing on Tanbat →</a>
  </p>
  <p class="u-sans" style="color:var(--muted);font-size:13px;margin-top:14px">
    New here? <a class="inline" href="https://tanbat.com/" rel="noopener" target="_blank">Create your free account first</a>,
    then come back and start writing.
  </p>
</div>
@endsection
