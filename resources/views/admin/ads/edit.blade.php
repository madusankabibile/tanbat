@extends('admin.layout')
@section('title', $isNew ? 'New advertisement' : 'Edit advertisement')
@section('breadcrumb', 'Manage › Advertisements')
@section('heading', $isNew ? 'New advertisement' : 'Edit advertisement')

@section('content')

<div class="mb-4">
  <a href="{{ route('admin.ads.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">← Back to advertisements</a>
</div>

<form method="POST"
      action="{{ $isNew ? route('admin.ads.store') : route('admin.ads.update', $ad) }}"
      enctype="multipart/form-data"
      class="grid gap-5 lg:grid-cols-3">
  @csrf
  @if(!$isNew) @method('PUT') @endif

  <div class="card p-6 lg:col-span-2 grid gap-4">

    <div>
      <label class="label">Title</label>
      <input type="text" name="title" value="{{ old('title', $ad->title) }}" class="input" required maxlength="160">
      @error('title') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="label">Body / description</label>
      <textarea name="body" rows="3" class="input" maxlength="2000">{{ old('body', $ad->body) }}</textarea>
      @error('body') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="label">Click-through URL</label>
      <input type="url" name="link_url" value="{{ old('link_url', $ad->link_url) }}" class="input" required maxlength="500" placeholder="https://example.com/landing">
      @error('link_url') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <label class="label">Placement</label>
        <select name="placement" class="input">
          @foreach($placements as $pl)
            <option value="{{ $pl }}" @selected(old('placement', $ad->placement) === $pl)>{{ ucfirst($pl) }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="label">Weight (1–1000)</label>
        <input type="number" name="weight" value="{{ old('weight', $ad->weight ?? 1) }}" min="1" max="1000" class="input">
        <p class="mt-1 text-xs text-slate-500">Higher weight ⇒ shown more often within the same placement.</p>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <label class="label">Starts at</label>
        <input type="datetime-local" name="starts_at"
               value="{{ old('starts_at', $ad->starts_at?->format('Y-m-d\TH:i')) }}" class="input">
      </div>
      <div>
        <label class="label">Ends at</label>
        <input type="datetime-local" name="ends_at"
               value="{{ old('ends_at', $ad->ends_at?->format('Y-m-d\TH:i')) }}" class="input">
        @error('ends_at') <p class="field-error">{{ $message }}</p> @enderror
      </div>
    </div>

    <div>
      <label class="label">Image (optional, max 4MB)</label>
      <input type="file" name="image" accept="image/*"
             class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-700">
      @error('image') <p class="field-error">{{ $message }}</p> @enderror

      @if(!$isNew && $ad->image_url)
        <div class="mt-3 flex items-center gap-3">
          <img src="{{ $ad->image_url }}" class="h-16 w-24 rounded-md object-cover" alt="">
          <label class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600">
            <input type="checkbox" name="remove_image" value="1" class="h-4 w-4 rounded border-slate-300 text-rose-600">
            Remove current image
          </label>
        </div>
      @endif
    </div>

    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
      <input type="hidden" name="is_active" value="0">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $ad->is_active ?? true))
             class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
      Advertisement is active
    </label>

    <div class="flex items-center justify-end gap-2 pt-2">
      <a href="{{ route('admin.ads.index') }}" class="btn-outline">Cancel</a>
      <button class="btn-primary">{{ $isNew ? 'Create advertisement' : 'Save changes' }}</button>
    </div>
  </div>

  <aside class="space-y-4">
    <div class="card p-5">
      <div class="text-sm font-bold text-slate-900">Live preview</div>
      <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
        @if(!$isNew && $ad->image_url)
          <img src="{{ $ad->image_url }}" class="aspect-[16/9] w-full object-cover" alt="">
        @else
          <div class="grid aspect-[16/9] place-items-center bg-gradient-to-br from-brand-100 to-accent-400/30 text-xs font-bold uppercase tracking-widest text-brand-700">Advertisement</div>
        @endif
        <div class="p-4">
          <div class="font-bold text-slate-900">{{ $ad->title ?: 'Your ad title' }}</div>
          @if($ad->body)<p class="mt-1 text-sm text-slate-600">{{ $ad->body }}</p>@endif
          <a href="{{ $ad->link_url ?: '#' }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-700">
            Open link →
          </a>
        </div>
      </div>
    </div>

    @if(!$isNew)
      <div class="card p-5">
        <div class="text-sm font-bold text-slate-900">Performance</div>
        <dl class="mt-3 grid grid-cols-3 gap-3 text-center">
          <div><dt class="text-xs uppercase text-slate-400">Impr.</dt><dd class="mt-1 text-lg font-extrabold text-slate-800">{{ number_format($ad->impressions) }}</dd></div>
          <div><dt class="text-xs uppercase text-slate-400">Clicks</dt><dd class="mt-1 text-lg font-extrabold text-slate-800">{{ number_format($ad->clicks) }}</dd></div>
          <div><dt class="text-xs uppercase text-slate-400">CTR</dt><dd class="mt-1 text-lg font-extrabold text-slate-800">{{ $ad->ctr }}%</dd></div>
        </dl>
      </div>
    @endif
  </aside>
</form>

@endsection
