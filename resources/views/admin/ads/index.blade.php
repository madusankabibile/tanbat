@extends('admin.layout')
@section('title', 'Advertisements')
@section('breadcrumb', 'Manage')
@section('heading', 'Advertisements')

@section('content')

<div class="mb-4 flex items-center justify-between">
  <p class="text-sm text-slate-600">Manage the ads served across the site — placements, schedule, weight, and live click/impression stats.</p>
  <a href="{{ route('admin.ads.create') }}" class="btn-primary">+ New advertisement</a>
</div>

<form method="GET" class="mb-4 card flex flex-wrap items-end gap-3 p-4">
  <div class="flex-1 min-w-[220px]">
    <label class="label">Search</label>
    <input type="text" name="q" value="{{ $q }}" placeholder="Title or link URL" class="input">
  </div>
  <div>
    <label class="label">Placement</label>
    <select name="placement" class="input">
      <option value="">All</option>
      @foreach($placements as $pl)
        <option value="{{ $pl }}" @selected($placement === $pl)>{{ ucfirst($pl) }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="label">Status</label>
    <select name="status" class="input">
      <option value="">All</option>
      <option value="live"     @selected($status === 'live')>Live</option>
      <option value="active"   @selected($status === 'active')>Active</option>
      <option value="inactive" @selected($status === 'inactive')>Inactive</option>
      <option value="expired"  @selected($status === 'expired')>Expired</option>
    </select>
  </div>
  <div class="flex gap-2">
    <button class="btn-primary">Filter</button>
    <a href="{{ route('admin.ads.index') }}" class="btn-outline">Reset</a>
  </div>
</form>

<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>Ad</th>
        <th>Placement</th>
        <th>Status</th>
        <th class="text-right">Weight</th>
        <th class="text-right">Impr.</th>
        <th class="text-right">Clicks</th>
        <th class="text-right">CTR</th>
        <th>Schedule</th>
        <th class="text-right">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($ads as $ad)
        @php
          $isLive = $ad->is_active
            && (!$ad->starts_at || $ad->starts_at->lte(now()))
            && (!$ad->ends_at   || $ad->ends_at->gte(now()));
          $isExpired = $ad->ends_at && $ad->ends_at->lt(now());
        @endphp
        <tr>
          <td class="max-w-xs">
            <div class="flex items-center gap-3">
              @if($ad->image_url)
                <img src="{{ $ad->image_url }}" class="h-10 w-14 rounded-md object-cover" alt="">
              @else
                <span class="grid h-10 w-14 place-items-center rounded-md bg-brand-100 text-brand-600 text-xs font-bold">AD</span>
              @endif
              <div class="min-w-0">
                <a href="{{ route('admin.ads.edit', $ad) }}" class="block truncate font-semibold text-slate-900 hover:text-brand-600">{{ $ad->title }}</a>
                <a href="{{ $ad->link_url }}" target="_blank" rel="noopener" class="block truncate text-[11px] text-slate-500 hover:text-brand-600">{{ $ad->link_url }}</a>
              </div>
            </div>
          </td>
          <td data-label="Placement"><span class="badge badge-type">{{ $ad->placement }}</span></td>
          <td data-label="Status">
            @if($isExpired)
              <span class="badge badge-expired">expired</span>
            @elseif($isLive)
              <span class="badge badge-live">live</span>
            @elseif($ad->is_active)
              <span class="badge badge-on">scheduled</span>
            @else
              <span class="badge badge-off">paused</span>
            @endif
          </td>
          <td class="text-right font-semibold" data-label="Weight">{{ $ad->weight }}</td>
          <td class="text-right font-semibold" data-label="Impressions">{{ number_format($ad->impressions) }}</td>
          <td class="text-right font-semibold" data-label="Clicks">{{ number_format($ad->clicks) }}</td>
          <td class="text-right font-semibold" data-label="CTR">{{ $ad->ctr }}%</td>
          <td class="text-xs text-slate-500" data-label="Schedule">
            @if($ad->starts_at || $ad->ends_at)
              {{ $ad->starts_at?->format('M j') ?? '—' }} → {{ $ad->ends_at?->format('M j, Y') ?? '∞' }}
            @else
              always
            @endif
          </td>
          <td class="text-right cell-actions">
            <div class="inline-flex gap-1.5">
              <form method="POST" action="{{ route('admin.ads.toggle', $ad) }}">
                @csrf
                <button class="btn-xs">{{ $ad->is_active ? 'Pause' : 'Activate' }}</button>
              </form>
              <a href="{{ route('admin.ads.edit', $ad) }}" class="btn-xs">Edit</a>
              <form method="POST" action="{{ route('admin.ads.destroy', $ad) }}" onsubmit="return confirm('Delete this advertisement?');">
                @csrf @method('DELETE')
                <button class="btn-xs btn-xs-danger">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="py-8 text-center text-sm text-slate-500">No advertisements yet. <a href="{{ route('admin.ads.create') }}" class="font-semibold text-brand-600 hover:underline">Create one →</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager mt-5 flex items-center gap-1.5">
  {{ $ads->links() }}
</div>

@endsection
