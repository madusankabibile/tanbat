{{-- Shared fields for admin.tv.create / admin.tv.edit.
     $channel is the row being edited, or null when creating. --}}
@php
  $channel = $channel ?? null;
  $isEdit  = (bool) $channel;
  // The category lives on the parent post, not on tv_channels.
  $currentCategory = old('category_id', $isEdit ? $channel->post?->category_id : null);
@endphp

<div class="card p-6 lg:col-span-2 grid gap-4">

  <div class="grid gap-4 sm:grid-cols-[2fr,1fr]">
    <div>
      <label class="label">Name <span class="text-rose-500">*</span></label>
      <input type="text" name="name" value="{{ old('name', $channel->name ?? '') }}" class="input" required maxlength="255" autofocus>
      @error('name') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="label">Category <span class="text-rose-500">*</span></label>
      <select name="category_id" class="input" required>
        <option value="">Select a category…</option>
        @foreach($categories as $category)
          <option value="{{ $category->id }}" @selected((int) $currentCategory === $category->id)>{{ $category->name }}</option>
        @endforeach
      </select>
      @error('category_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>
  </div>

  @if($isEdit)
    <div>
      <label class="label">URL slug</label>
      <div class="flex items-center gap-2">
        <span class="whitespace-nowrap text-sm text-slate-500">{{ rtrim(config('app.url'), '/') }}/tv/</span>
        <input type="text" name="slug" value="{{ old('slug', $channel->slug) }}" class="input font-mono" maxlength="120">
      </div>
      <p class="mt-1 text-xs text-slate-500">Changing this breaks any link already pointing at the old address.</p>
      @error('slug') <p class="field-error">{{ $message }}</p> @enderror
    </div>
  @endif

  {{-- ───── Logo ───── --}}
  <div>
    <label class="label">Logo</label>
    <div class="grid gap-3 sm:grid-cols-[auto,1fr] sm:items-start">
      <div class="grid h-20 w-20 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
        @if($isEdit && $channel->logo_url)
          <img src="{{ $channel->logo_url }}" alt="" class="h-full w-full object-contain p-1.5">
        @else
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="text-slate-300"><rect x="2" y="7" width="20" height="14" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
        @endif
      </div>
      <div class="grid gap-3">
        <div>
          <input type="file" name="logo_file" accept="image/*" class="input">
          <p class="mt-1 text-xs text-slate-500">PNG, JPG, WEBP or SVG, up to 2&nbsp;MB. A transparent square reads best.</p>
          @error('logo_file') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
          <input type="url" name="logo_url" value="{{ old('logo_url') }}" class="input" maxlength="1024" placeholder="…or paste an image URL">
          <p class="mt-1 text-xs text-slate-500">An uploaded file wins if you provide both. Leave both blank to keep the current logo.</p>
          @error('logo_url') <p class="field-error">{{ $message }}</p> @enderror
        </div>
      </div>
    </div>
  </div>

  <div>
    <label class="label">Description</label>
    <textarea name="description" rows="5" class="input" placeholder="What this channel broadcasts — shown under the player.">{{ old('description', $channel->description ?? '') }}</textarea>
    @error('description') <p class="field-error">{{ $message }}</p> @enderror
  </div>

  {{-- ───── Stream ───── --}}
  <div>
    <label class="label">m3u8 stream link <span class="text-rose-500">*</span></label>
    <input type="text" name="stream_url" value="{{ old('stream_url', $channel->stream_url ?? '') }}"
           class="input font-mono text-xs" required maxlength="2048"
           placeholder="https://example.com/live/channel/index.m3u8">
    <p class="mt-1 text-xs text-slate-500">
      Stored server-side only. The public page never contains this URL — viewers get a signed,
      expiring proxy link instead, so a stream sniffer can't read the source.
    </p>
    @error('stream_url') <p class="field-error">{{ $message }}</p> @enderror
  </div>

  {{-- ───── Advanced upstream headers ───── --}}
  <details class="rounded-xl border border-slate-200 bg-slate-50/60 p-4" @if($errors->has('referer') || $errors->has('user_agent')) open @endif>
    <summary class="cursor-pointer text-sm font-bold text-slate-800">Advanced &mdash; upstream headers</summary>
    <p class="mt-2 text-xs text-slate-500">
      Only needed when the provider rejects our requests. Fill these in if playback returns
      a 403 from the origin.
    </p>
    <div class="mt-3 grid gap-4">
      <div>
        <label class="label">Referer sent upstream</label>
        <input type="text" name="referer" value="{{ old('referer', $channel->referer ?? '') }}" class="input font-mono text-xs" maxlength="1024" placeholder="https://provider.example/">
        @error('referer') <p class="field-error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="label">User-Agent sent upstream</label>
        <input type="text" name="user_agent" value="{{ old('user_agent', $channel->user_agent ?? '') }}" class="input font-mono text-xs" maxlength="512" placeholder="Leave blank for the default browser UA">
        @error('user_agent') <p class="field-error">{{ $message }}</p> @enderror
      </div>
    </div>
  </details>

  <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-800">
    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300"
           @checked(old('is_active', $isEdit ? $channel->is_active : true))>
    Live &mdash; visible at /tv and playable
  </label>

  <div class="flex items-center justify-end gap-2 pt-2">
    <a href="{{ route('admin.tv.index') }}" class="btn-outline">Cancel</a>
    <button class="btn-primary">{{ $isEdit ? 'Save changes' : 'Add TV post' }}</button>
  </div>
</div>

<aside class="space-y-4">
  <div class="card p-5">
    <div class="text-sm font-bold text-slate-900">How TV posts work</div>
    <p class="mt-2 text-sm text-slate-600">
      Each channel is a post of type <span class="font-semibold">tv</span>, attributed to the shared
      anonymous system account &mdash; the same way admin-added books are.
    </p>
    <p class="mt-2 text-sm text-slate-600">
      It gets its own public page at <span class="font-mono text-xs">/tv/{slug}</span> and appears on the
      <a href="{{ route('tv.index') }}" target="_blank" class="text-brand-600 hover:underline">TV</a> grid
      and in the left panel of the home feed. TV posts are kept out of the scrolling feed.
    </p>
  </div>

  <div class="card p-5">
    <div class="text-sm font-bold text-slate-900">Stream protection</div>
    <ul class="mt-2 space-y-1.5 text-sm text-slate-600">
      <li>&bull; The origin m3u8 never reaches the browser.</li>
      <li>&bull; Playback URLs expire after {{ config('tv.token_ttl', 90) }}s and are bound to one session.</li>
      <li>&bull; Manifests are rewritten so segment URLs point back at this site.</li>
      <li>&bull; The player page blocks devtools shortcuts and the context menu.</li>
    </ul>
    <p class="mt-3 text-xs text-slate-500">
      These stop casual grabbing &mdash; sniffer extensions, "copy stream URL", curl replays. They are
      not DRM: anyone determined enough can still capture what their own browser plays.
    </p>
  </div>
</aside>
