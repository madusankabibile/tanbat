@extends('admin.layout')
@section('title', 'Add book')
@section('breadcrumb', 'Manage › Books')
@section('heading', 'Add book')

@section('content')

<div class="mb-4">
  <a href="{{ route('admin.books.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">← Back to books</a>
</div>

<form method="POST" action="{{ route('admin.books.store') }}" class="grid gap-5 lg:grid-cols-3">
  @csrf

  <div class="card p-6 lg:col-span-2 grid gap-4">

    <div>
      <label class="label">Title <span class="text-rose-500">*</span></label>
      <input type="text" name="title" value="{{ old('title') }}" class="input" required maxlength="255" autofocus>
      @error('title') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <label class="label">Author</label>
        <input type="text" name="author" value="{{ old('author') }}" class="input" maxlength="255">
        @error('author') <p class="field-error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="label">Publisher</label>
        <input type="text" name="publisher" value="{{ old('publisher') }}" class="input" maxlength="255">
        @error('publisher') <p class="field-error">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
      <div>
        <label class="label">Year</label>
        <input type="text" name="year" value="{{ old('year') }}" class="input" maxlength="8" placeholder="2024">
        @error('year') <p class="field-error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="label">Language</label>
        <input type="text" name="language" value="{{ old('language') }}" class="input" maxlength="64" placeholder="English">
        @error('language') <p class="field-error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="label">Format</label>
        <input type="text" name="extension" value="{{ old('extension') }}" class="input" maxlength="16" placeholder="pdf, epub…">
        @error('extension') <p class="field-error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="label">Size</label>
        <input type="text" name="size" value="{{ old('size') }}" class="input" maxlength="32" placeholder="4.2 MB">
        @error('size') <p class="field-error">{{ $message }}</p> @enderror
      </div>
    </div>

    <div>
      <label class="label">Cover image URL</label>
      <input type="url" name="cover_url" value="{{ old('cover_url') }}" class="input" maxlength="1024" placeholder="https://…/cover.jpg">
      <p class="mt-1 text-xs text-slate-500">Required for Reddit / Pinterest cross-posting.</p>
      @error('cover_url') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="label">Download URL</label>
      <input type="url" name="download_url" value="{{ old('download_url') }}" class="input" maxlength="1024" placeholder="https://…">
      @error('download_url') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="label">Description</label>
      <textarea name="description" rows="5" class="input">{{ old('description') }}</textarea>
      @error('description') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="label">md5 <span class="font-normal text-slate-400">(optional)</span></label>
      <input type="text" name="md5" value="{{ old('md5') }}" class="input font-mono" maxlength="32" placeholder="Leave blank to auto-generate">
      <p class="mt-1 text-xs text-slate-500">The dedup key. Paste a real Anna's Archive md5 to match the auto-poster, or leave blank for a synthesized one.</p>
      @error('md5') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center justify-end gap-2 pt-2">
      <a href="{{ route('admin.books.index') }}" class="btn-outline">Cancel</a>
      <button class="btn-primary">Add book</button>
    </div>
  </div>

  <aside class="space-y-4">
    <div class="card p-5">
      <div class="text-sm font-bold text-slate-900">About manual books</div>
      <p class="mt-2 text-sm text-slate-600">
        The book is published as an <span class="font-semibold">anonymous</span> post — attributed to the
        shared system account, exactly like guest-submitted books.
      </p>
      <p class="mt-2 text-sm text-slate-600">
        It appears in the site feed and on the <a href="{{ route('books') }}" target="_blank" class="text-brand-600 hover:underline">Books</a> page
        immediately. You can then cross-post it to Reddit or Pinterest from the books list.
      </p>
    </div>
  </aside>
</form>

@endsection
