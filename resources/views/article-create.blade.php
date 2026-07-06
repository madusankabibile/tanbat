@extends('layouts.app')
@section('title', 'New article — Tanbat')

@push('head')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
  .ql-editor { min-height: 400px; font-size: 16px; line-height: 1.7; color: #1E1B4B; }
  .ql-toolbar.ql-snow, .ql-container.ql-snow { border-color: #E5E7EB; }
  .ql-toolbar.ql-snow { border-top-left-radius: 12px; border-top-right-radius: 12px; background: #FAFBFF; }
  .ql-container.ql-snow { border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; }
</style>
@endpush

@section('content')
@include('partials.navbar')

<main class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
  <div class="mb-6 flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Write an article</h1>
      <p class="text-sm text-slate-500">Long-form, rich content. Published to your followers and the public feed.</p>
    </div>
    <a href="{{ url('/home') }}" class="btn-outline">Cancel</a>
  </div>

  <form id="articleForm" class="card p-6 sm:p-8 space-y-5">

    <div>
      <label class="label">Title <span class="text-rose-500">*</span></label>
      <input id="aTitle" type="text" required maxlength="240" placeholder="A catchy headline…"
        class="w-full border-0 border-b border-transparent bg-transparent px-0 py-2 text-2xl font-extrabold tracking-tight text-slate-900 focus:border-brand-400 focus:outline-none focus:ring-0">
    </div>

    {{-- Featured image --}}
    <div>
      <label class="label">Featured image <span class="text-rose-500">*</span></label>
      <label class="relative flex h-56 cursor-pointer items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 hover:border-brand-400 hover:bg-brand-50/40">
        <img id="aFeaturedPreview" class="hidden h-full w-full object-cover" alt="">
        <div id="aFeaturedHint" class="flex flex-col items-center text-sm text-slate-500">
          <svg class="mb-2 h-8 w-8 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <span><span class="font-semibold text-brand-600">Click to upload</span> · JPG / PNG / WEBP</span>
          <span class="mt-1 text-xs">Recommended 1600×900</span>
        </div>
        <input id="aFeatured" type="file" accept="image/*" required class="hidden">
      </label>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <label class="label">Category <span class="text-rose-500">*</span></label>
        <select id="aCategory" required class="input" data-categories></select>
      </div>
      <div>
        <label class="label">Tags <span class="text-slate-400 normal-case">(comma separated)</span></label>
        <input id="aTags" type="text" class="input" placeholder="design, travel, photography">
      </div>
    </div>

    <div>
      <label class="label">Short description <span class="text-rose-500">*</span> <span class="text-slate-400 normal-case">— max 100 words</span></label>
      <textarea id="aShort" rows="3" required maxlength="800"
        class="input resize-none" placeholder="A 1-2 sentence hook that previews the article…"></textarea>
      <div class="mt-1 text-right text-xs"><span id="aShortCount">0</span>/100 words</div>
    </div>

    <div>
      <label class="label">Article body <span class="text-rose-500">*</span></label>
      <div id="aEditor"></div>
    </div>

    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
      <a href="{{ url('/home') }}" class="btn-outline">Cancel</a>
      <button type="submit" id="aPublish" class="btn-primary px-6">Publish article</button>
    </div>
  </form>
</main>

@push('scripts')
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@vite(['resources/js/article-create.js'])
@endpush
@endsection
