@extends('layouts.app')
@section('title', 'Live TV — Tanbat')

@push('head')
@include('partials._seo', [
    'title'       => 'Live TV',
    'description' => 'Watch live TV channels free on Tanbat — news, entertainment, sport and music, streaming in your browser.',
    'url'         => route('tv.index'),
    'type'        => 'website',
])
@endpush

@section('content')
@auth
  @include('partials.navbar')
@else
  @include('partials.guest-bar')
@endauth

<div class="tvx-shell mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  <header class="tvx-head">
    <div>
      <h1 class="tvx-title">Live TV{{ $category ? ' · ' . $category->name : '' }}</h1>
      <p class="tvx-sub">{{ number_format($channels->total()) }} {{ Str::plural('channel', $channels->total()) }} streaming right now.</p>
    </div>
    <form method="GET" class="tvx-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="search" name="q" value="{{ $q }}" placeholder="Search channels" aria-label="Search channels">
      {{-- Keep the active category when searching, so the two filters compose. --}}
      @if($category)
        <input type="hidden" name="category" value="{{ $category->slug }}">
      @endif
    </form>
  </header>

  {{-- Category chips. Only categories that actually hold a live channel are
       listed, so no chip leads to an empty page. --}}
  @if($categories->isNotEmpty())
    <nav class="tvx-chips" aria-label="Filter by category">
      <a href="{{ route('tv.index', array_filter(['q' => $q])) }}"
         class="tvx-chip {{ $category ? '' : 'is-on' }}">All</a>
      @foreach($categories as $c)
        <a href="{{ route('tv.index', array_filter(['category' => $c->slug, 'q' => $q])) }}"
           class="tvx-chip {{ $category?->id === $c->id ? 'is-on' : '' }}">{{ $c->name }}</a>
      @endforeach
    </nav>
  @endif

  @if($channels->isEmpty())
    <div class="tvx-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
      <p>{{ $q !== '' ? 'No channels match “' . $q . '”.' : 'No channels are live yet — check back soon.' }}</p>
      @if($q !== '')
        <a href="{{ route('tv.index') }}">Clear search</a>
      @endif
    </div>
  @else
    <div class="tvx-grid">
      @foreach($channels as $c)
        <a href="{{ route('tv.show', $c->slug) }}" class="tvx-card">
          <span class="tvx-thumb">
            @if($c->logo_url)
              <img src="{{ $c->logo_url }}" alt="{{ $c->name }} logo" loading="lazy">
            @else
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
            @endif
            <span class="tvx-live"><i></i>Live</span>
          </span>
          <span class="tvx-body">
            <strong>{{ $c->name }}</strong>
            <span class="tvx-meta">
              @if($c->post?->category)
                <span class="tvx-cat">{{ $c->post->category->name }}</span>
              @endif
              <span class="tvx-views">{{ number_format($c->views) }} views</span>
            </span>
          </span>
        </a>
      @endforeach
    </div>

    <div class="tvx-pager">{{ $channels->links() }}</div>
  @endif
</div>

<style>
.tvx-head {
  display: flex; align-items: flex-end; justify-content: space-between; gap: 16px;
  flex-wrap: wrap; margin-bottom: 20px;
}
.tvx-title { margin: 0; font-size: 26px; font-weight: 800; color: #1E1B4B; }
.tvx-sub   { margin: 4px 0 0; font-size: 13.5px; color: #64748B; }
.tvx-search { position: relative; flex: 1; min-width: 220px; max-width: 320px; }
.tvx-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); height: 16px; width: 16px; color: #94A3B8; }
.tvx-search input {
  width: 100%; border: 1px solid #E5E7EB; border-radius: 9999px;
  padding: 10px 16px 10px 38px; font-size: 13.5px; background: #fff; color: #1E1B4B;
}
.tvx-search input:focus { outline: 2px solid #C7D2FE; outline-offset: -1px; border-color: #A5B4FC; }

.tvx-grid {
  display: grid; gap: 14px;
  grid-template-columns: repeat(auto-fill, minmax(158px, 1fr));
}
.tvx-card {
  display: flex; flex-direction: column; overflow: hidden;
  background: #fff; border: 1px solid #E5E7EB; border-radius: 14px;
  box-shadow: 0 1px 2px rgba(20, 20, 50, .04);
  transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.tvx-card:hover {
  transform: translateY(-2px); border-color: #C7D2FE;
  box-shadow: 0 10px 24px rgba(99, 102, 241, .14);
}
.tvx-thumb {
  position: relative; display: grid; place-items: center;
  aspect-ratio: 16 / 10; background: #0B1020; padding: 18px; color: #334155;
}
.tvx-thumb img { max-height: 100%; max-width: 100%; object-fit: contain; }
.tvx-thumb > svg { height: 34px; width: 34px; }
.tvx-live {
  position: absolute; top: 8px; left: 8px;
  display: inline-flex; align-items: center; gap: 5px;
  border-radius: 9999px; padding: 3px 8px; background: rgba(220, 38, 38, .92);
  font-size: 9.5px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; color: #fff;
}
.tvx-live i { height: 5px; width: 5px; border-radius: 9999px; background: #fff; }
.tvx-body { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px 12px; min-width: 0; }
.tvx-body strong {
  font-size: 13.5px; font-weight: 700; color: #1E1B4B;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.tvx-meta { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; min-width: 0; }
.tvx-cat {
  border-radius: 9999px; padding: 1px 7px; background: #EEF2FF; color: #4338CA;
  font-size: 10px; font-weight: 700; white-space: nowrap;
}
.tvx-views { font-size: 11.5px; color: #94A3B8; }

/* Category filter chips */
.tvx-chips {
  display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px;
}
.tvx-chip {
  border: 1px solid #E5E7EB; border-radius: 9999px; padding: 6px 14px;
  background: #fff; color: #475569; font-size: 12.5px; font-weight: 600;
  transition: background .15s ease, border-color .15s ease, color .15s ease;
}
.tvx-chip:hover { border-color: #C7D2FE; color: #4338CA; }
.tvx-chip.is-on { background: #4338CA; border-color: #4338CA; color: #fff; }

.tvx-empty {
  display: flex; flex-direction: column; align-items: center; gap: 10px;
  padding: 60px 20px; text-align: center; color: #94A3B8;
  background: #fff; border: 1px solid #E5E7EB; border-radius: 16px;
}
.tvx-empty svg { height: 40px; width: 40px; color: #CBD5E1; }
.tvx-empty p { margin: 0; font-size: 14px; font-weight: 600; color: #64748B; }
.tvx-empty a { font-size: 13px; font-weight: 700; color: #6366F1; }
.tvx-pager { margin-top: 24px; }
</style>

@endsection
