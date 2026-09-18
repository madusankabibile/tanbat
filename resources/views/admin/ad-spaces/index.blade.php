@extends('admin.layout')
@section('title', 'Ad Spaces')
@section('breadcrumb', 'Manage › Ad Spaces')
@section('heading', 'Advertisement Spaces')

@section('content')

{{-- ─────────── TAB NAVIGATION ─────────── --}}
<div class="mb-5 flex border-b border-slate-200">
  <a href="{{ route('admin.ad-spaces.index') }}"
     class="border-b-2 border-brand-600 px-4 py-2.5 text-sm font-bold text-brand-600">
    Ad Spaces (Placements)
  </a>
  <a href="{{ route('admin.ads.index') }}"
     class="border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700">
    Direct Campaigns ({{ $campaignCount }})
  </a>
</div>

{{-- ─────────── HEADER SUMMARY ─────────── --}}
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
  <div>
    <p class="text-sm text-slate-600">
      Manage the hard-coded ad placements across Tanbat and OMRMS. Enable or disable any slot, paste network scripts (AdSense, HighPerformanceFormat, EffectiveCPMNetwork), or rotate direct campaigns.
    </p>
  </div>
  <div class="flex items-center gap-2">
    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">
      <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
      {{ $activeCount }} of {{ $totalCount }} spaces active
    </span>
    <form action="{{ route('admin.ad-spaces.reset') }}" method="POST" onsubmit="return confirm('Reset ALL ad spaces back to their hardcoded factory defaults?');">
      @csrf
      <button type="submit" class="btn-outline text-xs px-3 py-1.5 text-slate-600 hover:text-rose-600">
        Reset all to defaults
      </button>
    </form>
  </div>
</div>

@if (session('status'))
  <div class="mb-5 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
    <span>{{ session('status') }}</span>
  </div>
@endif

<form action="{{ route('admin.ad-spaces.update') }}" method="POST">
  @csrf
  @method('PUT')

  {{-- ─────────── GROUPS LOOP ─────────── --}}
  @foreach ($groups as $gKey => $group)
    <div class="mb-8">
      <div class="mb-3 flex items-baseline justify-between border-b border-slate-200 pb-2">
        <div>
          <h2 class="text-base font-extrabold text-slate-900">{{ $group['label'] }}</h2>
          <p class="text-xs text-slate-500">{{ $group['description'] }}</p>
        </div>
      </div>

      <div class="grid gap-5">
        @foreach ($group['spaces'] as $sKey => $space)
          <div class="card p-5 transition-shadow hover:shadow-md {{ !$space['enabled'] ? 'opacity-85 bg-slate-50/50' : '' }}" id="space-card-{{ $sKey }}">
            
            {{-- Card Head --}}
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3.5">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-sm font-bold text-slate-900">{{ $space['name'] }}</h3>
                  @if($space['enabled'])
                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Live
                    </span>
                  @else
                    <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">
                      Paused / Disabled
                    </span>
                  @endif
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ $space['description'] }}</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-400">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <span>{{ $space['locations'] }}</span>
                </div>
              </div>

              {{-- Enable toggle --}}
              <div class="flex items-center gap-2">
                <label class="relative inline-flex cursor-pointer items-center">
                  <input type="hidden" name="spaces[{{ $sKey }}][enabled]" value="0">
                  <input type="checkbox" name="spaces[{{ $sKey }}][enabled]" value="1"
                         class="peer sr-only"
                         {{ $space['enabled'] ? 'checked' : '' }}
                         onchange="document.getElementById('space-card-{{ $sKey }}').classList.toggle('opacity-85', !this.checked);">
                  <div class="h-6 w-11 rounded-full bg-slate-200 peer-focus:outline-none peer-checked:bg-brand-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                </label>
                <span class="text-xs font-semibold text-slate-700">Active</span>
              </div>
            </div>

            {{-- Card Body --}}
            <div class="pt-4 space-y-4">

              {{-- Display Mode Selector (for spaces that support direct campaigns) --}}
              @if(in_array($sKey, ['sidebar', 'assistant']))
                <div class="flex flex-wrap items-center gap-3">
                  <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Ad Source:</label>
                  <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-xs">
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 {{ ($space['mode'] ?? 'code') === 'code' ? 'bg-brand-50 font-bold text-brand-700' : 'text-slate-600' }}">
                      <input type="radio" name="spaces[{{ $sKey }}][mode]" value="code" {{ ($space['mode'] ?? 'code') === 'code' ? 'checked' : '' }} class="sr-only" onchange="this.form.dispatchEvent(new Event('change'));">
                      Custom Script / HTML
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 {{ ($space['mode'] ?? '') === 'campaign' ? 'bg-brand-50 font-bold text-brand-700' : 'text-slate-600' }}">
                      <input type="radio" name="spaces[{{ $sKey }}][mode]" value="campaign" {{ ($space['mode'] ?? '') === 'campaign' ? 'checked' : '' }} class="sr-only" onchange="this.form.dispatchEvent(new Event('change'));">
                      Direct Campaign Banner
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 {{ ($space['mode'] ?? '') === 'auto' ? 'bg-brand-50 font-bold text-brand-700' : 'text-slate-600' }}">
                      <input type="radio" name="spaces[{{ $sKey }}][mode]" value="auto" {{ ($space['mode'] ?? '') === 'auto' ? 'checked' : '' }} class="sr-only" onchange="this.form.dispatchEvent(new Event('change'));">
                      Auto (Custom Code or Fallback)
                    </label>
                  </div>
                </div>
              @endif

              {{-- Assistant inherit sidebar toggle --}}
              @if($sKey === 'assistant')
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                  <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                    <input type="hidden" name="spaces[assistant][use_sidebar]" value="0">
                    <input type="checkbox" name="spaces[assistant][use_sidebar]" value="1"
                           {{ !empty($space['use_sidebar']) ? 'checked' : '' }}
                           class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                           onchange="document.getElementById('assist-custom-code').style.display = this.checked ? 'none' : 'block';">
                    Inherit Tanbat Sidebar Ad code for Assistant rails (recommended)
                  </label>
                  <p class="mt-1 text-[11px] text-slate-500">When checked, the Assistant page left and right rails display the same active sidebar ad configured above.</p>
                </div>
              @endif

              {{-- Space: sponsor_url --}}
              @if($sKey === 'sponsor_url')
                <div>
                  <label class="label">Sponsor Click-Through Target URL</label>
                  <div class="flex gap-2">
                    <input type="url" name="spaces[sponsor_url][url]"
                           value="{{ old('spaces.sponsor_url.url', $space['url'] ?? '') }}"
                           class="input font-mono text-xs flex-1"
                           placeholder="https://example.com/sponsor-landing">
                    @if(!empty($space['url']))
                      <a href="{{ $space['url'] }}" target="_blank" rel="noopener" class="btn-outline text-xs inline-flex items-center gap-1">
                        Test link ↗
                      </a>
                    @endif
                  </div>
                  <p class="mt-1 text-xs text-slate-500">Clicks on book covers and the newsbot card "Continue reading…" button redirect to this sponsor link.</p>
                </div>

              {{-- Space: feed --}}
              @elseif($sKey === 'feed')
                <div class="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label class="label">Ad Slot Container Key (Hash ID)</label>
                    <input type="text" name="spaces[feed][slot_key]"
                           value="{{ old('spaces.feed.slot_key', $space['slot_key'] ?? '') }}"
                           class="input font-mono text-xs"
                           placeholder="36ce0149ae6c36811ff6c54b088c483c">
                    <p class="mt-1 text-[11px] text-slate-500">The ad network container ID suffix (e.g. <code>#container-&lt;key&gt;</code>).</p>
                  </div>
                  <div>
                    <label class="label">Ad Slot Script URL</label>
                    <input type="url" name="spaces[feed][slot_src]"
                           value="{{ old('spaces.feed.slot_src', $space['slot_src'] ?? '') }}"
                           class="input font-mono text-xs"
                           placeholder="https://.../invoke.js">
                    <p class="mt-1 text-[11px] text-slate-500">Network script fetched to render the creative inside the feed card.</p>
                  </div>
                </div>

              {{-- Space: mid_article --}}
              @elseif($sKey === 'mid_article')
                <div class="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label class="label">Container ID</label>
                    <input type="text" name="spaces[mid_article][container_id]"
                           value="{{ old('spaces.mid_article.container_id', $space['container_id'] ?? '') }}"
                           class="input font-mono text-xs"
                           placeholder="container-36ce0149ae6c36811ff6c54b088c483c">
                  </div>
                  <div>
                    <label class="label">Network Script URL (invoke.js)</label>
                    <input type="url" name="spaces[mid_article][script_url]"
                           value="{{ old('spaces.mid_article.script_url', $space['script_url'] ?? '') }}"
                           class="input font-mono text-xs"
                           placeholder="https://.../invoke.js">
                  </div>
                </div>
                <div>
                  <label class="label">Custom HTML Override (Optional)</label>
                  <textarea name="spaces[mid_article][code]" rows="2" class="input font-mono text-xs"
                            placeholder="Leave empty to use the Container ID + Script URL above, or paste custom HTML/JS banner code here...">{{ old('spaces.mid_article.code', $space['code'] ?? '') }}</textarea>
                  <p class="mt-1 text-[11px] text-slate-500">If custom HTML is provided, it replaces the network script and is injected at 50% scroll on mobile screens.</p>
                </div>

              {{-- Spaces with standard HTML/JS code textareas --}}
              @else
                <div id="{{ $sKey === 'assistant' ? 'assist-custom-code' : 'code-wrap-' . $sKey }}"
                     style="{{ ($sKey === 'assistant' && !empty($space['use_sidebar'])) ? 'display:none;' : '' }}">
                  <label class="label">Advertisement HTML / JavaScript Code</label>
                  <textarea name="spaces[{{ $sKey }}][code]" rows="5"
                            class="input font-mono text-xs leading-relaxed"
                            placeholder="Paste ad network code (<script>, <iframe>, <ins class='adsbygoogle'>, banner HTML)...">{{ old('spaces.' . $sKey . '.code', $space['code'] ?? '') }}</textarea>
                  <p class="mt-1 text-[11px] text-slate-500">Raw HTML and &lt;script&gt; tags are rendered directly at this position.</p>
                </div>
              @endif

              {{-- Default code drawer --}}
              @if(!empty($space['default_code']))
                <details class="group rounded-lg border border-slate-100 bg-slate-50 text-xs">
                  <summary class="flex cursor-pointer items-center justify-between px-3 py-2 text-slate-600 hover:text-slate-900 font-medium select-none">
                    <span>View factory default code</span>
                    <span class="text-brand-600 text-[11px] group-open:rotate-180 transition-transform">▼</span>
                  </summary>
                  <div class="border-t border-slate-200/60 p-3 space-y-2">
                    <pre class="overflow-x-auto rounded bg-slate-900 p-2.5 text-[11px] text-slate-200 font-mono">{{ $space['default_code'] }}</pre>
                    <div class="flex justify-end">
                      <button type="button" class="text-xs font-semibold text-brand-600 hover:underline"
                              onclick="const ta = this.closest('.card').querySelector('textarea'); if(ta){ ta.value = {{ json_encode($space['default_code']) }}; }">
                        Copy default into editor
                      </button>
                    </div>
                  </div>
                </details>
              @endif

            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endforeach

  {{-- Sticky Action Bar --}}
  <div class="sticky bottom-4 z-20 mt-8 flex items-center justify-between rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-pop backdrop-blur">
    <div class="flex items-center gap-2 text-xs text-slate-500">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <span>Changes take effect immediately on next page load across Tanbat & OMRMS.</span>
    </div>
    <div class="flex items-center gap-3">
      <a href="{{ route('admin.ad-spaces.index') }}" class="btn-outline">Cancel</a>
      <button type="submit" class="btn-primary flex items-center gap-2">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Save All Ad Spaces
      </button>
    </div>
  </div>

</form>

@endsection
