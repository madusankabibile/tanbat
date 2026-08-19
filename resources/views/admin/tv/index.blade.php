@extends('admin.layout')
@section('title', 'TV Channels')
@section('breadcrumb', 'Manage')
@section('heading', 'TV Channels')

@section('content')

<div class="mb-4 flex justify-end">
  <a href="{{ route('admin.tv.create') }}" class="btn-primary inline-flex items-center gap-1.5">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add TV post
  </a>
</div>

@if (session('error'))
  <div class="mb-5 flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    {{ session('error') }}
  </div>
@endif

{{-- ───── Ledger strip ───── --}}
<div class="strip mb-5">
  <div class="kpi">
    <div class="kpi-label">Channels</div>
    <div class="kpi-value">{{ number_format($totals['all']) }}</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Live</div>
    <div class="kpi-value">{{ number_format($totals['live']) }}</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Offline</div>
    <div class="kpi-value">{{ number_format($totals['offline']) }}</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Total views</div>
    <div class="kpi-value">{{ number_format($totals['views']) }}</div>
  </div>
</div>

{{-- ───── Filters ───── --}}
<form method="GET" class="mb-4 card flex flex-wrap items-end gap-3 p-4">
  <div class="flex-1 min-w-[220px]">
    <label class="label">Search</label>
    <input type="text" name="q" value="{{ $q }}" placeholder="Channel name or slug" class="input">
  </div>
  <div>
    <label class="label">Status</label>
    <select name="status" class="input">
      <option value="">All</option>
      <option value="live"    @selected($status === 'live')>Live</option>
      <option value="offline" @selected($status === 'offline')>Offline</option>
    </select>
  </div>
  <div class="flex gap-2">
    <button class="btn-primary">Filter</button>
    <a href="{{ route('admin.tv.index') }}" class="btn-outline">Reset</a>
  </div>
</form>

{{-- ───── Table ───── --}}
<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>Channel</th>
        <th>Public URL</th>
        <th>Status</th>
        <th class="text-right">Views</th>
        <th>Added</th>
        <th class="text-right">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($channels as $c)
        <tr>
          <td class="max-w-xs" data-label="Channel">
            <div class="flex items-center gap-3">
              @if($c->logo_url)
                <img src="{{ $c->logo_url }}" alt="" class="h-10 w-10 flex-none rounded-lg bg-slate-100 object-contain p-1 shadow-sm" loading="lazy">
              @else
                <span class="grid h-10 w-10 flex-none place-items-center rounded-lg bg-slate-100 text-slate-400">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><polyline points="17 2 12 7 7 2"/></svg>
                </span>
              @endif
              <div class="min-w-0">
                <a href="{{ route('tv.show', $c->slug) }}" target="_blank" class="block truncate font-semibold text-slate-900 hover:text-brand-600">{{ $c->name }}</a>
                <div class="truncate text-[11px] text-slate-500">{{ Str::limit(strip_tags((string) $c->description), 60) ?: 'No description' }}</div>
              </div>
            </div>
          </td>
          <td data-label="Public URL">
            <a href="{{ route('tv.show', $c->slug) }}" target="_blank" class="font-mono text-xs text-brand-600 hover:underline">/tv/{{ $c->slug }}</a>
          </td>
          <td data-label="Status">
            @if($c->is_active)
              <span class="badge badge-on">Live</span>
            @else
              <span class="badge badge-off">Offline</span>
            @endif
          </td>
          <td class="text-right" data-label="Views">{{ number_format($c->views) }}</td>
          <td data-label="Added">{{ $c->created_at?->format('M j, Y') }}</td>
          <td class="text-right cell-actions">
            <div class="row-menu">
              <button type="button" class="row-menu__btn" aria-haspopup="true" aria-expanded="false" title="Actions">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
              </button>
              <div class="row-menu__panel">
                <a href="{{ route('admin.tv.edit', $c) }}" class="row-menu__item">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                  Edit
                </a>
                <a href="{{ route('tv.show', $c->slug) }}" target="_blank" class="row-menu__item">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                  Open player
                </a>
                <form method="POST" action="{{ route('admin.tv.toggle', $c) }}">
                  @csrf
                  <button type="submit" class="row-menu__item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    {{ $c->is_active ? 'Take offline' : 'Set live' }}
                  </button>
                </form>
                <div class="row-menu__divider"></div>
                <form method="POST" action="{{ route('admin.tv.destroy', $c) }}" onsubmit="return confirm('Delete this TV channel and its post? This cannot be undone.');">
                  @csrf @method('DELETE')
                  <button type="submit" class="row-menu__item row-menu__item--danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Delete
                  </button>
                </form>
              </div>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="py-8 text-center text-sm text-slate-500">No TV channels match these filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager mt-5 flex items-center gap-1.5">
  {{ $channels->links() }}
</div>

@endsection
