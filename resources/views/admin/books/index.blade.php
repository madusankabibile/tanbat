@extends('admin.layout')
@section('title', 'Books')
@section('breadcrumb', 'Manage')
@section('heading', 'Books')

@section('content')

<div class="mb-4 flex justify-end">
  <a href="{{ route('admin.books.create') }}" class="btn-primary inline-flex items-center gap-1.5">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add book
  </a>
</div>

@if (session('error'))
  <div class="mb-5 flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    {{ session('error') }}
  </div>
@endif

{{-- ───── Reddit connection banner ───── --}}
<div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/70 bg-white px-5 py-4 shadow-card">
  <div class="flex items-center gap-3">
    <span class="grid h-9 w-9 place-items-center rounded-xl bg-orange-50 text-orange-600">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0A12 12 0 1 0 24 12 12 12 0 0 0 12 0Zm6.3 13.7a1 1 0 0 1 0 1.4 6.7 6.7 0 0 1-9.4 0 1 1 0 0 1 1.4-1.4 4.7 4.7 0 0 0 6.6 0 1 1 0 0 1 1.4 0Zm-9.4-3a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Zm5 0a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Z"/></svg>
    </span>
    <div>
      @if($redditReady)
        <div class="text-sm font-bold text-slate-900">Reddit connected
          <span class="badge badge-on ml-1">Live</span>
        </div>
        <div class="text-xs text-slate-500">
          Posting as <span class="font-semibold">@ {{ $redditAccount ?: 'unknown' }}</span>
          @if($subreddit) to <span class="font-semibold">r/{{ $subreddit }}</span> @endif
        </div>
      @else
        <div class="text-sm font-bold text-slate-900">Reddit not connected
          <span class="badge badge-off ml-1">Off</span>
        </div>
        <div class="text-xs text-slate-500">Connect an account to enable cross-posting.</div>
      @endif
    </div>
  </div>
  <a href="{{ route('admin.reddit.index') }}" class="btn-xs">Manage connection</a>
</div>

{{-- ───── Filters ───── --}}
<form method="GET" class="mb-4 card flex flex-wrap items-end gap-3 p-4">
  <div class="flex-1 min-w-[220px]">
    <label class="label">Search</label>
    <input type="text" name="q" value="{{ $q }}" placeholder="Title, author, publisher or md5" class="input">
  </div>
  <div>
    <label class="label">Reddit status</label>
    <select name="reddit" class="input">
      <option value="">All</option>
      <option value="posted"  @selected($reddit === 'posted')>Posted</option>
      <option value="pending" @selected($reddit === 'pending')>Pending</option>
      <option value="failed"  @selected($reddit === 'failed')>Failed</option>
    </select>
  </div>
  <div class="flex gap-2">
    <button class="btn-primary">Filter</button>
    <a href="{{ route('admin.books.index') }}" class="btn-outline">Reset</a>
  </div>
</form>

{{-- ───── Table ───── --}}
<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>Book</th>
        <th>Author</th>
        <th>Requested by</th>
        <th>Format</th>
        <th>Reddit</th>
        <th>Added</th>
        <th class="text-right">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($books as $b)
        <tr>
          <td class="max-w-xs">
            <div class="flex items-center gap-3">
              @if($b->cover_url)
                <img src="{{ $b->cover_url }}" alt="" class="h-12 w-9 flex-none rounded object-cover shadow-sm" loading="lazy">
              @else
                <span class="grid h-12 w-9 flex-none place-items-center rounded bg-slate-100 text-slate-400">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </span>
              @endif
              <div class="min-w-0">
                @if($b->slug)
                  <a href="{{ route('books.show', $b->slug) }}" target="_blank" class="block truncate font-semibold text-slate-900 hover:text-brand-600">{{ $b->title }}</a>
                @else
                  <span class="block truncate font-semibold text-slate-900">{{ $b->title }}</span>
                @endif
                <div class="text-[11px] text-slate-500 truncate">{{ $b->publisher }}{{ $b->year ? ' · '.$b->year : '' }}</div>
              </div>
            </div>
          </td>
          <td class="text-slate-600">{{ $b->author ?: '—' }}</td>
          <td>
            @if($b->post?->user)
              <div class="text-sm font-semibold text-slate-800">{{ $b->post->user->name }}</div>
              <div class="text-[11px] text-slate-500">{{ '@'.$b->post->user->username }}</div>
            @else
              <span class="text-slate-400">—</span>
            @endif
          </td>
          <td>
            <span class="badge badge-type">{{ strtoupper($b->extension ?: '?') }}</span>
            @if($b->size)<div class="mt-0.5 text-[11px] text-slate-500">{{ $b->size }}</div>@endif
          </td>
          <td>
            @if($b->reddit_post_id)
              <a href="https://www.reddit.com/comments/{{ \Illuminate\Support\Str::after($b->reddit_post_id, 't3_') }}" target="_blank" class="badge badge-on hover:underline">Posted</a>
              <div class="mt-0.5 text-[11px] text-slate-500">{{ $b->reddit_posted_at?->diffForHumans() }}</div>
            @elseif($b->reddit_attempts >= $maxAttempts)
              <span class="badge badge-expired" title="{{ $b->reddit_last_error }}">Failed ({{ $b->reddit_attempts }})</span>
            @elseif($b->reddit_attempts > 0)
              <span class="badge badge-off" title="{{ $b->reddit_last_error }}">Retrying ({{ $b->reddit_attempts }}/{{ $maxAttempts }})</span>
            @else
              <span class="badge badge-off">Pending</span>
            @endif
          </td>
          <td class="text-xs text-slate-500">{{ $b->created_at?->format('M j, Y') }}</td>
          <td class="text-right">
            @php
              $noCover    = empty($b->cover_url);
              $redditOff  = !$redditReady || $noCover;
              $pinOff     = !$pinterestReady || $noCover;
              $redditTip  = !$redditReady ? 'Connect Reddit first' : ($noCover ? 'No cover image to post' : '');
              $pinTip     = !$pinterestReady ? 'Connect Pinterest first' : ($noCover ? 'No cover image to pin' : '');
            @endphp
            <div class="row-menu">
              <button type="button" class="row-menu__btn" aria-haspopup="true" aria-expanded="false" title="Actions">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
              </button>
              <div class="row-menu__panel">
                <form method="POST" action="{{ route('admin.books.repost', $b) }}"
                      onsubmit="return confirm('{{ $b->reddit_post_id ? 'Post this book to Reddit again?' : 'Post this book to Reddit now?' }}');">
                  @csrf
                  <button type="submit" class="row-menu__item" @disabled($redditOff) title="{{ $redditTip }}">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0A12 12 0 1 0 24 12 12 12 0 0 0 12 0Zm6.3 13.7a1 1 0 0 1 0 1.4 6.7 6.7 0 0 1-9.4 0 1 1 0 0 1 1.4-1.4 4.7 4.7 0 0 0 6.6 0 1 1 0 0 1 1.4 0Zm-9.4-3a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Zm5 0a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Z"/></svg>
                    {{ $b->reddit_post_id ? 'Repost to Reddit' : 'Post to Reddit' }}
                  </button>
                </form>
                <form method="POST" action="{{ route('admin.books.repost-pinterest', $b) }}"
                      onsubmit="return confirm('{{ $b->pinterest_pin_id ? 'Pin this book to Pinterest again?' : 'Pin this book to Pinterest now?' }}');">
                  @csrf
                  <button type="submit" class="row-menu__item" @disabled($pinOff) title="{{ $pinTip }}">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0a12 12 0 0 0-4.37 23.17c-.1-.94-.2-2.4.04-3.44.22-.93 1.4-5.96 1.4-5.96s-.36-.72-.36-1.78c0-1.67.97-2.92 2.17-2.92 1.02 0 1.51.77 1.51 1.69 0 1.03-.65 2.56-.99 3.98-.28 1.19.6 2.16 1.77 2.16 2.12 0 3.76-2.24 3.76-5.48 0-2.86-2.06-4.86-5-4.86-3.41 0-5.41 2.56-5.41 5.2 0 1.03.4 2.13.89 2.73.1.12.11.22.08.34l-.33 1.35c-.05.22-.17.27-.4.16-1.49-.69-2.42-2.87-2.42-4.62 0-3.76 2.73-7.21 7.88-7.21 4.13 0 7.35 2.95 7.35 6.88 0 4.11-2.59 7.42-6.18 7.42-1.21 0-2.34-.63-2.73-1.37l-.74 2.83c-.27 1.03-1 2.32-1.48 3.11A12 12 0 1 0 12 0Z"/></svg>
                    {{ $b->pinterest_pin_id ? 'Repin to Pinterest' : 'Pin to Pinterest' }}
                  </button>
                </form>
                <div class="row-menu__divider"></div>
                <form method="POST" action="{{ route('admin.books.destroy', $b) }}" onsubmit="return confirm('Delete this book and its post? This cannot be undone.');">
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
        <tr><td colspan="7" class="py-8 text-center text-sm text-slate-500">No books match these filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager mt-5 flex items-center gap-1.5">
  {{ $books->links() }}
</div>

@endsection
