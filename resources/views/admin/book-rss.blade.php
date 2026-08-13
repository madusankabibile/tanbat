@extends('admin.layout')
@section('title', 'Book RSS Importer')
@section('breadcrumb', 'Integrations · Book RSS')
@section('heading', 'Book RSS importer')

@section('content')

@if (session('status'))
  <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    {{ session('status') }}
  </div>
@endif
@if (session('error'))
  <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
    {{ session('error') }}
  </div>
@endif
@if ($errors->any())
  <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
    {{ $errors->first() }}
  </div>
@endif

<p class="mb-5 max-w-2xl text-sm text-slate-600">
  Watches an ebook site's RSS feed and publishes each new entry as an
  <strong>anonymous book post</strong>. Only the listing details are imported — title, author,
  language, format, cover thumbnail and the links. Each post credits and links back to
  the original page.
</p>

<div class="grid gap-6 lg:grid-cols-3">

  {{-- ───── Status ───── --}}
  <div class="card p-6 lg:col-span-2">
    <div class="flex items-center gap-3">
      <span class="grid h-12 w-12 place-items-center rounded-2xl {{ $enabled ? 'bg-emerald-500' : 'bg-slate-300' }} text-white">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
          <path d="M6.18 15.64a2.18 2.18 0 1 1 0 4.36 2.18 2.18 0 0 1 0-4.36ZM4 4.44A15.56 15.56 0 0 1 19.56 20h-2.83A12.73 12.73 0 0 0 4 7.27V4.44Zm0 5.66A9.9 9.9 0 0 1 13.9 20h-2.83A7.07 7.07 0 0 0 4 12.93V10.1Z"/>
        </svg>
      </span>
      <div class="min-w-0 flex-1">
        <div class="text-sm font-semibold text-slate-500">Automatic import</div>
        <div class="text-lg font-extrabold text-slate-900">{{ $enabled ? 'Enabled' : 'Disabled' }}</div>
        <div class="mt-0.5 text-xs text-slate-500">
          @if ($enabled)
            Polls the feed about every {{ $pollMinutes }} minutes, up to {{ $maxPerRun }} new books per run.
          @else
            The feed isn't polled automatically. You can still import on demand below.
          @endif
        </div>
      </div>
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-3">
      <form method="POST" action="{{ route('admin.book-rss.toggle') }}">
        @csrf
        <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold text-white shadow-soft
                       {{ $enabled ? 'bg-slate-600 hover:bg-slate-700' : 'bg-gradient-to-br from-emerald-500 to-emerald-600 hover:-translate-y-0.5' }}">
          {{ $enabled ? 'Turn automatic import off' : 'Turn automatic import on' }}
        </button>
      </form>

      <form method="POST" action="{{ route('admin.book-rss.import') }}"
            onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Importing…'; return true;">
        @csrf
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">
          Import now
        </button>
      </form>
    </div>

    <div class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
      <div>
        <span class="font-semibold text-slate-700">Last run:</span>
        {{ $lastRunAt ? $lastRunAt->diffForHumans() . ' (' . $lastRunAt->format('Y-m-d H:i') . ')' : 'never' }}
      </div>
      @if ($lastResult)
        <div class="mt-1"><span class="font-semibold text-slate-700">Result:</span> {{ $lastResult }}</div>
      @endif
    </div>
  </div>

  {{-- ───── Totals ───── --}}
  <div class="card p-6">
    <div class="text-sm font-bold uppercase tracking-wide text-slate-500">Imported</div>
    <div class="mt-3 text-4xl font-extrabold text-slate-900">{{ $importedTotal }}</div>
    <div class="mt-1 text-xs text-slate-500">books published from this feed</div>
    <a href="{{ route('admin.telegram.index') }}" class="mt-4 inline-block text-xs font-semibold text-brand-600 hover:underline">
      Telegram channel settings →
    </a>
  </div>
</div>

{{-- ───── Feed URL ───── --}}
<form action="{{ route('admin.book-rss.update') }}" method="POST" class="mt-6">
  @csrf
  @method('PUT')

  <div class="card p-6">
    <div class="text-sm font-bold uppercase tracking-wide text-slate-500">Feed</div>
    <div class="mt-4">
      <label for="feed_url" class="block text-sm font-semibold text-slate-700">RSS feed URL</label>
      <input id="feed_url" type="url" name="feed_url" value="{{ old('feed_url', $feedUrl) }}" maxlength="1024"
             class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
             placeholder="https://example.com/feed/">
      <div class="mt-1 text-[11px] text-slate-400">
        A standard RSS 2.0 feed. Entries are de-duplicated on the feed's own item id, so
        re-importing never creates the same book twice.
      </div>
    </div>
  </div>

  <div class="mt-6">
    <button type="submit"
            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 px-6 py-2.5 text-sm font-bold text-white shadow-soft hover:-translate-y-0.5 hover:shadow-pop">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      Save feed URL
    </button>
  </div>
</form>

{{-- ───── Recent imports ───── --}}
<div class="card mt-6 overflow-hidden">
  <div class="border-b border-slate-100 px-6 py-4">
    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Recently imported</h3>
  </div>

  @if ($recent->isEmpty())
    <div class="px-6 py-10 text-center text-sm text-slate-500">
      Nothing imported yet. Hit <strong>Import now</strong> above to pull the current feed.
    </div>
  @else
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
            <th class="px-6 py-3">Book</th>
            <th class="px-6 py-3">Telegram</th>
            <th class="px-6 py-3">Imported</th>
            <th class="px-6 py-3">Source</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($recent as $b)
            <tr class="border-b border-slate-50 last:border-0">
              <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                  @if ($b->cover_url)
                    <img src="{{ $b->cover_url }}" alt="" class="h-12 w-9 flex-none rounded object-cover shadow-sm" loading="lazy">
                  @else
                    <span class="grid h-12 w-9 flex-none place-items-center rounded bg-slate-100 text-[10px] text-slate-400">—</span>
                  @endif
                  <div class="min-w-0">
                    <a href="{{ url('/books/' . $b->slug) }}" target="_blank" class="block truncate font-semibold text-slate-800 hover:underline">{{ $b->title }}</a>
                    <div class="truncate text-xs text-slate-500">{{ $b->author ?: 'Unknown author' }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-3">
                @if ($b->telegram_message_id)
                  <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Posted</span>
                @elseif (!$b->cover_url)
                  <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500" title="A photo message needs a cover">No cover</span>
                @elseif ($b->telegram_last_error)
                  <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-700" title="{{ $b->telegram_last_error }}">Retrying</span>
                @else
                  <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">Queued</span>
                @endif
              </td>
              <td class="px-6 py-3 text-xs text-slate-500">{{ $b->created_at?->diffForHumans() }}</td>
              <td class="px-6 py-3">
                <a href="{{ $b->source_url }}" target="_blank" rel="noopener nofollow"
                   class="text-xs font-semibold text-brand-600 hover:underline">Original ↗</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

{{-- ───── How it works ───── --}}
<div class="card mt-6 p-6">
  <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">How it works</h3>
  <ol class="mt-3 space-y-2 text-sm text-slate-700">
    <li><strong>1. Poll.</strong> The feed is fetched on a schedule (and whenever you press <em>Import now</em>).</li>
    <li><strong>2. Read the listing.</strong> Title, author, language, file format and size come from the entry's own summary; the first image becomes the cover.</li>
    <li><strong>3. Publish.</strong> Each new entry becomes a book post owned by the shared <strong>anonymous</strong> account, so it appears as an anonymous post site-wide.</li>
    <li><strong>4. Download link.</strong> If the entry carries an off-site file link, that becomes the book's download link; otherwise the link points at the original page.</li>
    <li><strong>5. Announce.</strong> New books queue up for the Telegram channel automatically — see <a href="{{ route('admin.telegram.index') }}" class="font-semibold text-brand-600 hover:underline">Integrations → Telegram</a>.</li>
  </ol>
</div>

@endsection
