@extends('admin.layout')
@section('title', 'Book Search Engine')
@section('breadcrumb', 'Integrations · Book Search Engine')
@section('heading', 'Book Search Engine')

@section('content')

@if ($errors->any())
  <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
    {{ $errors->first() }}
  </div>
@endif

<p class="mb-5 max-w-2xl text-sm text-slate-600">
  Choose which source powers book search and publishing in the Tanbat Assistant.
  Both sites change their public domain from time to time — update the domain
  here whenever a source stops responding, no code change needed.
</p>

<form action="{{ route('admin.book-search.update') }}" method="POST">
  @csrf
  @method('PUT')

  {{-- ───── Engine selection ───── --}}
  <div class="card p-6">
    <div class="text-sm font-bold uppercase tracking-wide text-slate-500">Active engine</div>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
      @foreach ($engines as $slug => $e)
        <label class="relative flex cursor-pointer flex-col gap-2 rounded-2xl border-2 p-5 transition
                      {{ $active === $slug ? 'border-brand-500 bg-brand-50/60 shadow-soft' : 'border-slate-200 bg-white hover:border-slate-300' }}"
               data-engine-card="{{ $slug }}">
          <span class="flex items-center justify-between">
            <span class="text-base font-extrabold text-slate-900">{{ $e['label'] }}</span>
            <input type="radio" name="engine" value="{{ $slug }}"
                   class="h-5 w-5 accent-brand-600"
                   {{ $active === $slug ? 'checked' : '' }}>
          </span>
          <span class="text-xs text-slate-500">
            @if ($slug === 'annas')
              Default source. Searches by md5 and publishes the full library record.
            @else
              Z-Library scraper (solves the browser proof-of-work challenge server-side).
            @endif
          </span>
          <span class="mt-1 inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold
                       {{ $active === $slug ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
            {{ $active === $slug ? 'Currently active' : 'Inactive' }}
          </span>
        </label>
      @endforeach
    </div>
  </div>

  {{-- ───── Domains ───── --}}
  <div class="card mt-6 p-6">
    <div class="text-sm font-bold uppercase tracking-wide text-slate-500">Domains</div>
    <div class="mt-1 text-xs text-slate-500">
      Include the scheme (e.g. <code class="rounded bg-slate-100 px-1.5 py-0.5">https://annas-archive.gl</code>).
      The trailing slash is added automatically.
    </div>

    <div class="mt-5 grid gap-5 sm:grid-cols-2">
      <div>
        <label for="annas_domain" class="block text-sm font-semibold text-slate-700">{{ $engines['annas']['label'] }} domain</label>
        <input id="annas_domain" type="text" name="annas_domain"
               value="{{ old('annas_domain', $engines['annas']['domain']) }}"
               class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
               placeholder="{{ $engines['annas']['default_domain'] }}">
        <div class="mt-1 text-[11px] text-slate-400">Default: {{ $engines['annas']['default_domain'] }}</div>
      </div>
      <div>
        <label for="zlib_domain" class="block text-sm font-semibold text-slate-700">{{ $engines['zlib']['label'] }} domain</label>
        <input id="zlib_domain" type="text" name="zlib_domain"
               value="{{ old('zlib_domain', $engines['zlib']['domain']) }}"
               class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
               placeholder="{{ $engines['zlib']['default_domain'] }}">
        <div class="mt-1 text-[11px] text-slate-400">Default: {{ $engines['zlib']['default_domain'] }}</div>
      </div>
    </div>
  </div>

  <div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 px-6 py-2.5 text-sm font-bold text-white shadow-soft hover:-translate-y-0.5 hover:shadow-pop">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      Save settings
    </button>
  </div>
</form>

{{-- ───── How it works ───── --}}
<div class="card mt-6 p-6">
  <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">How it works</h3>
  <ol class="mt-3 space-y-2 text-sm text-slate-700">
    <li><strong>Search.</strong> The Assistant wizard queries the active engine at the domain set above.</li>
    <li><strong>Publish.</strong> When a user confirms a result, the book is fetched from the same engine and turned into a post. Books are de-duplicated by their source identifier, so the same title from each engine is stored once.</li>
    <li><strong>Switching.</strong> Changing the engine takes effect immediately for new searches — previously published books are unaffected.</li>
    <li><strong>Domain rotation.</strong> If a source goes dark, paste its new domain here and save; no deploy required.</li>
  </ol>
</div>

@endsection
