{{-- shared tab styles for video source selector --}}
<style>
  .video-tab { color: #64748B; transition: background .15s, color .15s; }
  .video-tab:hover { color: #1E1B4B; }
  .video-tab.is-active { background: #fff; color: #1E1B4B; box-shadow: 0 1px 3px rgba(20,20,50,.08); }
</style>

{{-- ─────────── STATUS MODAL ─────────── --}}
<div id="modalStatus" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
  <div class="w-full max-w-xl rounded-3xl bg-white p-5 shadow-pop animate-fup">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 class="text-lg font-bold text-slate-900">Create status</h3>
      <button type="button" data-close-modal class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <form id="formStatus" class="mt-4 space-y-4">
      <div class="flex items-center gap-3">
        @php $u=auth()->user(); $av=$u?->avatarUrl(); @endphp
        @if($av)<img src="{{ $av }}" class="h-10 w-10 rounded-full object-cover" alt="">
        @else<span class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white">{{ strtoupper(substr($u->username ?? 'U',0,1)) }}</span>@endif
        <div>
          <div class="text-sm font-semibold text-slate-900">{{ $u->name }}</div>
          <div class="text-xs text-slate-500">Posting publicly</div>
        </div>
      </div>

      <div id="statusCanvas"
        class="relative min-h-[180px] rounded-2xl border border-slate-200 p-4 transition"
        style="background:#ffffff;color:#1E1B4B;">
        <textarea id="statusText" rows="4" required maxlength="2000"
          class="w-full resize-none border-0 bg-transparent text-lg font-medium placeholder:text-slate-400 focus:outline-none"
          style="color:inherit;background:transparent;"
          placeholder="What's on your mind?"></textarea>
        <img id="statusPreview" class="mt-3 hidden max-h-56 w-auto rounded-xl" alt="">
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div>
          <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Background</div>
          <div class="flex flex-wrap items-center gap-1.5" id="bgSwatches"></div>
        </div>
        <div class="ml-auto">
          <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Font</div>
          <div class="flex flex-wrap items-center gap-1.5" id="fontSwatches"></div>
        </div>
      </div>

      <div class="flex items-center justify-between border-t border-slate-100 pt-3">
        <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
          <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          Photo
          <input id="statusImage" type="file" accept="image/*" class="hidden">
        </label>
        <button type="submit" class="btn-primary px-6">Post</button>
      </div>
    </form>
  </div>
</div>

{{-- ─────────── IMAGE MODAL ─────────── --}}
<div id="modalImage" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
  <div class="w-full max-w-2xl rounded-3xl bg-white p-5 shadow-pop animate-fup">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 class="text-lg font-bold text-slate-900">Image post</h3>
      <button type="button" data-close-modal class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <form id="formImage" class="mt-4 space-y-4">
      <div>
        <label class="label">Photos <span class="text-rose-500">*</span></label>
        <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-200 p-6 text-sm text-slate-500 hover:border-brand-400 hover:bg-brand-50/50">
          <svg class="h-8 w-8 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <span><span class="font-semibold text-brand-600">Click to upload</span> or drop images</span>
          <span class="text-xs">JPG, PNG, WEBP · up to 10 files · 8MB each</span>
          <input id="imageFiles" type="file" accept="image/*" multiple required class="hidden">
        </label>
        <div id="imagePreviews" class="mt-3 grid grid-cols-3 gap-2"></div>
      </div>
      <div>
        <label class="label">Description <span class="text-slate-400 normal-case">(optional)</span></label>
        <textarea id="imageDesc" rows="3" class="input resize-none" placeholder="Tell people about these photos…"></textarea>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="label">Category <span class="text-rose-500">*</span></label>
          <select id="imageCategory" required class="input" data-categories></select>
        </div>
        <div>
          <label class="label">Audience <span class="text-rose-500">*</span></label>
          <div class="flex gap-2">
            <label class="flex-1 cursor-pointer">
              <input type="radio" name="imageAdult" value="0" class="peer sr-only" checked>
              <div class="rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm font-medium text-slate-700 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">General</div>
            </label>
            <label class="flex-1 cursor-pointer">
              <input type="radio" name="imageAdult" value="1" class="peer sr-only">
              <div class="rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm font-medium text-slate-700 peer-checked:border-rose-500 peer-checked:bg-rose-50 peer-checked:text-rose-700">18+</div>
            </label>
          </div>
        </div>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
        <button type="button" data-close-modal class="btn-outline">Cancel</button>
        <button type="submit" class="btn-primary">Publish</button>
      </div>
    </form>
  </div>
</div>

{{-- ─────────── VIDEO MODAL ─────────── --}}
<div id="modalVideo" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
  <div class="w-full max-w-2xl rounded-3xl bg-white p-5 shadow-pop animate-fup">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 class="text-lg font-bold text-slate-900">Video post</h3>
      <button type="button" data-close-modal class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="formVideo" class="mt-4 space-y-4">
      {{-- Source tabs --}}
      <div class="inline-flex rounded-xl bg-slate-100 p-1" role="tablist">
        <button type="button" data-video-tab="file"
          class="video-tab is-active px-4 py-1.5 text-sm font-semibold rounded-lg">
          Upload file
        </button>
        <button type="button" data-video-tab="embed"
          class="video-tab px-4 py-1.5 text-sm font-semibold rounded-lg">
          Embed URL
        </button>
      </div>

      {{-- File upload pane --}}
      <div data-video-pane="file" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="label">Video file <span class="text-rose-500">*</span></label>
          <label class="flex h-32 cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-slate-200 text-sm text-slate-500 hover:border-brand-400 hover:bg-brand-50/50">
            <svg class="h-7 w-7 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
            <span id="videoFileLabel" class="text-xs"><span class="font-semibold text-brand-600">Upload video</span> · MP4, WEBM, MOV</span>
            <input id="videoFile" type="file" accept="video/mp4,video/webm,video/quicktime" class="hidden">
          </label>
        </div>
        <div>
          <label class="label">Thumbnail <span class="text-rose-500">*</span></label>
          <label class="flex h-32 cursor-pointer flex-col items-center justify-center gap-1 overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 text-sm text-slate-500 hover:border-brand-400 hover:bg-brand-50/50">
            <img id="videoThumbPreview" class="hidden h-full w-full object-cover" alt="">
            <span id="videoThumbHint" class="flex flex-col items-center gap-1">
              <svg class="h-7 w-7 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <span class="text-xs"><span class="font-semibold text-brand-600">Upload thumbnail</span></span>
            </span>
            <input id="videoThumb" type="file" accept="image/*" class="hidden">
          </label>
        </div>
      </div>

      {{-- Embed URL pane --}}
      <div data-video-pane="embed" class="hidden space-y-3">
        <div>
          <label class="label">YouTube or Vimeo URL <span class="text-rose-500">*</span></label>
          <input id="videoEmbedUrl" type="url" class="input"
            placeholder="https://www.youtube.com/watch?v=… or https://vimeo.com/…">
          <p class="mt-1 text-xs text-slate-500">Supported: youtube.com, youtu.be, vimeo.com. We'll auto-fetch the thumbnail.</p>
        </div>
        <div id="videoEmbedPreview" class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
          <div class="aspect-video w-full bg-black">
            <img id="videoEmbedThumb" class="h-full w-full object-cover" alt="">
          </div>
          <div class="flex items-center justify-between px-3 py-2 text-xs text-slate-600">
            <span id="videoEmbedMeta">Ready to publish</span>
            <button type="button" id="videoEmbedClear" class="font-semibold text-rose-600 hover:underline">Remove</button>
          </div>
        </div>
      </div>
      <div>
        <label class="label">Description <span class="text-slate-400 normal-case">(optional)</span></label>
        <textarea id="videoDesc" rows="3" class="input resize-none" placeholder="Describe your video…"></textarea>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="label">Category <span class="text-rose-500">*</span></label>
          <select id="videoCategory" required class="input" data-categories></select>
        </div>
        <div>
          <label class="label">Audience <span class="text-rose-500">*</span></label>
          <div class="flex gap-2">
            <label class="flex-1 cursor-pointer">
              <input type="radio" name="videoAdult" value="0" class="peer sr-only" checked>
              <div class="rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm font-medium text-slate-700 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">General</div>
            </label>
            <label class="flex-1 cursor-pointer">
              <input type="radio" name="videoAdult" value="1" class="peer sr-only">
              <div class="rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm font-medium text-slate-700 peer-checked:border-rose-500 peer-checked:bg-rose-50 peer-checked:text-rose-700">18+</div>
            </label>
          </div>
        </div>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
        <button type="button" data-close-modal class="btn-outline">Cancel</button>
        <button type="submit" class="btn-primary">Publish</button>
      </div>
    </form>
  </div>
</div>
