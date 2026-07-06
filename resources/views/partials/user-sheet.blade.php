{{-- Slide-up user preview. Opened by JS (openUserSheet(id)) when a user link is clicked on the home page. --}}
<div id="userSheet"
     class="user-sheet pointer-events-none fixed inset-0 z-[60] flex items-end justify-center sm:items-center"
     aria-hidden="true">
  {{-- Scrim --}}
  <div data-user-sheet-scrim
       class="user-sheet__scrim absolute inset-0 bg-slate-900/60 opacity-0 backdrop-blur-sm transition-opacity duration-200"></div>

  {{-- Panel --}}
  <div class="user-sheet__panel relative w-full max-w-md translate-y-full overflow-hidden rounded-t-3xl bg-white shadow-pop transition-transform duration-300 ease-out sm:rounded-3xl">
    {{-- Grab handle (mobile) --}}
    <div class="flex justify-center pt-2 sm:hidden">
      <span class="h-1 w-10 rounded-full bg-slate-300"></span>
    </div>

    {{-- Close --}}
    <button type="button" data-user-sheet-close
            class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
            aria-label="Close">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>

    {{-- Cover banner --}}
    <div class="user-sheet__cover h-24 bg-gradient-to-br from-brand-500 via-accent-500 to-brand-600 sm:h-28"></div>

    {{-- Identity block --}}
    <div class="-mt-10 px-5">
      <div id="userSheetAvatar" class="grid h-20 w-20 place-items-center overflow-hidden rounded-full border-4 border-white bg-gradient-to-br from-brand-500 to-accent-500 text-2xl font-bold text-white shadow-soft"></div>

      <div class="mt-3 flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div id="userSheetName" class="truncate text-lg font-bold text-slate-900"></div>
          <div id="userSheetHandle" class="truncate text-sm text-slate-500"></div>
          <div id="userSheetMeta" class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-500"></div>
        </div>
        <button id="userSheetFollow" type="button"
                class="hidden shrink-0 rounded-full bg-brand-600 px-4 py-1.5 text-xs font-semibold text-white shadow-soft transition hover:bg-brand-700 disabled:opacity-60"
                aria-pressed="false">Follow</button>
      </div>
    </div>

    {{-- Stats grid --}}
    <div class="mt-5 grid grid-cols-4 gap-1 border-t border-slate-100 bg-slate-50/70">
      @php
        $cells = [
          ['k' => 'posts',     'label' => 'Posts'],
          ['k' => 'followers', 'label' => 'Followers'],
          ['k' => 'following', 'label' => 'Following'],
          ['k' => 'likes',     'label' => 'Likes'],
        ];
      @endphp
      @foreach ($cells as $c)
        <div class="px-2 py-3 text-center">
          <div class="text-base font-bold text-slate-900" data-user-stat="{{ $c['k'] }}">0</div>
          <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $c['label'] }}</div>
        </div>
      @endforeach
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-2 border-t border-slate-100 px-5 py-3">
      <a id="userSheetViewBtn" href="#"
         class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
        View full profile
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
    </div>
  </div>
</div>

<style>
  .user-sheet.is-open                                 { pointer-events: auto; }
  .user-sheet.is-open .user-sheet__scrim              { opacity: 1; }
  .user-sheet.is-open .user-sheet__panel              { transform: translateY(0); }
  @media (min-width: 640px) {
    .user-sheet .user-sheet__panel                    { transform: translateY(24px) scale(.96); opacity: 0; }
    .user-sheet.is-open .user-sheet__panel            { transform: translateY(0) scale(1); opacity: 1; }
  }
</style>
