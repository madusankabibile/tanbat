@extends('admin.layout')
@section('title', 'Telegram Integration')
@section('breadcrumb', 'Integrations · Telegram')
@section('heading', 'Telegram channel poster')

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
  Every new book is announced in your Telegram channel as a <strong>photo message</strong> —
  the cover image, a short caption, and a button that links back to the book's page on the site.
</p>

<div class="grid gap-6 lg:grid-cols-3">

  {{-- ───── Connection status ───── --}}
  <div class="card p-6 lg:col-span-2">
    <div class="flex items-center gap-3">
      <span class="grid h-12 w-12 place-items-center rounded-2xl
                   {{ $bot && $chat ? 'bg-emerald-500' : ($configured ? 'bg-amber-500' : 'bg-slate-300') }} text-white">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
          <path d="M12 0a12 12 0 1 0 0 24 12 12 0 0 0 0-24Zm5.6 8.2-1.9 8.9c-.14.63-.52.78-1.05.49l-2.9-2.14-1.4 1.35c-.16.15-.29.29-.58.29l.2-2.96 5.4-4.88c.24-.2-.05-.32-.36-.12l-6.68 4.2-2.87-.9c-.63-.2-.64-.63.13-.93l11.2-4.32c.52-.19.98.12.81.93Z"/>
        </svg>
      </span>
      <div class="min-w-0 flex-1">
        <div class="text-sm font-semibold text-slate-500">Connection</div>
        @if ($bot && $chat)
          <div class="text-lg font-extrabold text-slate-900">
            Connected as <span class="text-emerald-600">&#64;{{ $bot['username'] ?? 'unknown' }}</span>
          </div>
          <div class="mt-0.5 text-xs text-slate-500">
            Posting to
            @if ($channelUrl)
              <a href="{{ $channelUrl }}" target="_blank" rel="noopener" class="font-semibold text-slate-700 hover:underline">{{ $chat['title'] ?? $chatId }}</a>
            @else
              <span class="font-semibold text-slate-700">{{ $chat['title'] ?? $chatId }}</span>
            @endif
            <span class="text-slate-400">·</span>
            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px]">{{ $chat['type'] ?? 'channel' }} {{ $chat['id'] ?? '' }}</code>
          </div>
        @elseif ($configured)
          <div class="text-lg font-extrabold text-slate-900">Credentials saved, but the check failed</div>
          <div class="mt-0.5 text-xs text-rose-600">{{ $probeError }}</div>
        @else
          <div class="text-lg font-extrabold text-slate-900">Not connected</div>
          <div class="mt-0.5 text-xs text-slate-500">
            Paste the bot token from <strong>&#64;BotFather</strong> below, then add the bot to your channel as an admin.
          </div>
        @endif
      </div>
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-3">
      {{-- Automatic posting toggle --}}
      <form method="POST" action="{{ route('admin.telegram.toggle') }}">
        @csrf
        <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
        <button type="submit" @disabled(!$configured)
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold text-white shadow-soft
                       {{ $enabled ? 'bg-slate-600 hover:bg-slate-700' : 'bg-gradient-to-br from-emerald-500 to-emerald-600 hover:-translate-y-0.5' }}
                       disabled:cursor-not-allowed disabled:opacity-40"
                title="{{ $configured ? '' : 'Save a bot token and channel first' }}">
          {{ $enabled ? 'Turn automatic posting off' : 'Turn automatic posting on' }}
        </button>
      </form>

      <form method="POST" action="{{ route('admin.telegram.test') }}"
            onsubmit="return confirm('Send a test message to {{ $chatId }}? Everyone in the channel will see it.');">
        @csrf
        <button type="submit" @disabled(!$configured)
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
          Send test message
        </button>
      </form>

      @if ($configured)
        <form method="POST" action="{{ route('admin.telegram.disconnect') }}"
              onsubmit="return confirm('Clear the stored bot token and channel?');">
          @csrf
          @method('DELETE')
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">
            Disconnect
          </button>
        </form>
      @endif

      <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold
                   {{ $enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
        {{ $enabled ? 'Automatic posting ON' : 'Automatic posting OFF' }}
      </span>
    </div>
  </div>

  {{-- ───── Queue stats ───── --}}
  <div class="card p-6">
    <div class="text-sm font-bold uppercase tracking-wide text-slate-500">Queue</div>
    <dl class="mt-4 space-y-3 text-sm">
      <div class="flex items-center justify-between">
        <dt class="text-slate-600">Posted to channel</dt>
        <dd class="font-extrabold text-emerald-600">{{ $stats['posted'] }}</dd>
      </div>
      <div class="flex items-center justify-between">
        <dt class="text-slate-600">Waiting to post</dt>
        <dd class="font-extrabold text-slate-900">{{ $stats['pending'] }}</dd>
      </div>
      <div class="flex items-center justify-between">
        <dt class="text-slate-600">Failed ({{ $maxAttempts }} tries)</dt>
        <dd class="font-extrabold text-rose-600">{{ $stats['failed'] }}</dd>
      </div>
      <div class="flex items-center justify-between">
        <dt class="text-slate-600" title="A photo message needs a cover image">Skipped — no cover</dt>
        <dd class="font-extrabold text-slate-400">{{ $stats['nocover'] }}</dd>
      </div>
    </dl>
    <a href="{{ route('admin.books.index') }}" class="mt-4 inline-block text-xs font-semibold text-brand-600 hover:underline">
      Manage books →
    </a>
  </div>
</div>

{{-- ───── Settings ───── --}}
<form action="{{ route('admin.telegram.update') }}" method="POST" class="mt-6">
  @csrf
  @method('PUT')

  <div class="card p-6">
    <div class="text-sm font-bold uppercase tracking-wide text-slate-500">Bot &amp; channel</div>

    <div class="mt-5 grid gap-5 sm:grid-cols-2">
      <div>
        <label for="bot_token" class="block text-sm font-semibold text-slate-700">Bot token</label>
        <input id="bot_token" type="text" name="bot_token" autocomplete="off"
               class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
               placeholder="{{ $maskedToken ?: '123456789:AA…' }}">
        <div class="mt-1 text-[11px] text-slate-400">
          @if ($maskedToken)
            Currently <code class="rounded bg-slate-100 px-1.5 py-0.5">{{ $maskedToken }}</code> — leave blank to keep it.
          @else
            Get this from &#64;BotFather with <code class="rounded bg-slate-100 px-1.5 py-0.5">/newbot</code> or <code class="rounded bg-slate-100 px-1.5 py-0.5">/token</code>.
          @endif
        </div>
      </div>

      <div>
        <label for="chat_id" class="block text-sm font-semibold text-slate-700">Channel</label>
        <input id="chat_id" type="text" name="chat_id" value="{{ old('chat_id', $chatId) }}"
               class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
               placeholder="&#64;SinhalaFreeBooks">
        <div class="mt-1 text-[11px] text-slate-400">
          Public channel username (<code class="rounded bg-slate-100 px-1.5 py-0.5">&#64;name</code>) or a numeric
          <code class="rounded bg-slate-100 px-1.5 py-0.5">-100…</code> id for a private one.
        </div>
      </div>

      <div>
        <label for="button_text" class="block text-sm font-semibold text-slate-700">Button label</label>
        <input id="button_text" type="text" name="button_text" value="{{ old('button_text', $buttonText) }}" maxlength="64"
               class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
        <div class="mt-1 text-[11px] text-slate-400">Shown on the button under the photo. It links to the book's page.</div>
      </div>

      <div>
        <label for="post_spacing_minutes" class="block text-sm font-semibold text-slate-700">Minutes between posts</label>
        <input id="post_spacing_minutes" type="number" name="post_spacing_minutes" min="1" max="1440"
               value="{{ old('post_spacing_minutes', $spacing) }}"
               class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
        <div class="mt-1 text-[11px] text-slate-400">One book per interval, so a batch of imports drains steadily.</div>
      </div>
    </div>

    <div class="mt-5">
      <label for="caption_template" class="block text-sm font-semibold text-slate-700">Caption template</label>
      <textarea id="caption_template" name="caption_template" rows="8"
                class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-2.5 font-mono text-xs text-slate-800 focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('caption_template', $caption) }}</textarea>
      <div class="mt-1 text-[11px] text-slate-400">
        Placeholders:
        <code class="rounded bg-slate-100 px-1.5 py-0.5">&#123;title&#125;</code>
        <code class="rounded bg-slate-100 px-1.5 py-0.5">&#123;author&#125;</code>
        <code class="rounded bg-slate-100 px-1.5 py-0.5">&#123;language&#125;</code>
        <code class="rounded bg-slate-100 px-1.5 py-0.5">&#123;size&#125;</code>
        <code class="rounded bg-slate-100 px-1.5 py-0.5">&#123;extension&#125;</code>
        <code class="rounded bg-slate-100 px-1.5 py-0.5">&#123;link&#125;</code>.
        Telegram HTML is allowed (<code class="rounded bg-slate-100 px-1.5 py-0.5">&lt;b&gt;</code>,
        <code class="rounded bg-slate-100 px-1.5 py-0.5">&lt;i&gt;</code>,
        <code class="rounded bg-slate-100 px-1.5 py-0.5">&lt;a&gt;</code>). Max 1024 characters — longer captions are trimmed.
      </div>
    </div>
  </div>

  <div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 px-6 py-2.5 text-sm font-bold text-white shadow-soft hover:-translate-y-0.5 hover:shadow-pop">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      Save &amp; verify
    </button>
  </div>
</form>

{{-- ───── How it works ───── --}}
<div class="card mt-6 p-6">
  <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">How it works</h3>
  <ol class="mt-3 space-y-2 text-sm text-slate-700">
    <li><strong>1. Set up the bot.</strong> Create it with &#64;BotFather, then add it to the channel as an administrator with <em>Post Messages</em> permission. Without that permission Telegram rejects every post.</li>
    <li><strong>2. Books queue automatically.</strong> Any book with a cover image that hasn't been announced yet is queued — whether it came from the RSS importer, the Assistant wizard, or the manual "Add book" form.</li>
    <li><strong>3. One book per interval.</strong> The site posts a single book each spacing window so the channel stays readable.</li>
    <li><strong>4. Each message is a photo.</strong> Cover image + caption + a button linking to <code class="rounded bg-slate-100 px-1.5 py-0.5">{{ $siteUrl }}/books/…</code>, where the reader gets the download.</li>
    <li><strong>5. Failures retry.</strong> A book is retried up to {{ $maxAttempts }} times; the last error is shown on the Books page.</li>
  </ol>
</div>

@endsection
