@extends('admin.layout')
@section('title', 'Reddit Integration')
@section('breadcrumb', 'Integrations · Reddit')
@section('heading', 'Reddit cross-poster')

@section('content')

@if (session('success'))
  <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    {{ session('success') }}
  </div>
@endif
@if (session('error'))
  <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
    {{ session('error') }}
  </div>
@endif

<div class="grid gap-4 lg:grid-cols-3">

  {{-- ───── Connection status ───── --}}
  <div class="card p-6 lg:col-span-2">
    <div class="flex items-center gap-3">
      <span class="grid h-12 w-12 place-items-center rounded-2xl
                   {{ $token ? 'bg-emerald-500' : 'bg-slate-300' }} text-white">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
          <path d="M12 0A12 12 0 1 0 24 12 12 12 0 0 0 12 0Zm6.3 13.7a1 1 0 0 1 0 1.4 6.7 6.7 0 0 1-9.4 0 1 1 0 0 1 1.4-1.4 4.7 4.7 0 0 0 6.6 0 1 1 0 0 1 1.4 0Zm-9.4-3a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Zm5 0a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Z"/>
        </svg>
      </span>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-slate-500">Connection</div>
        @if ($token)
          <div class="text-lg font-extrabold text-slate-900">
            Connected as <span class="text-emerald-600">@{{ $token->account_name ?: 'unknown' }}</span>
          </div>
          <div class="mt-0.5 text-xs text-slate-500">
            Scopes: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px]">{{ $token->scope ?: '—' }}</code>
            · access token expires {{ optional($token->expires_at)->diffForHumans() ?? 'soon' }}
          </div>
        @else
          <div class="text-lg font-extrabold text-slate-900">Not connected</div>
          <div class="mt-0.5 text-xs text-slate-500">
            Click <strong>Connect Reddit</strong> below to authorize Tanbat to post on your behalf.
          </div>
        @endif
      </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-3">
      @if ($token)
        <form action="{{ route('admin.reddit.disconnect') }}" method="POST">
          @csrf
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            Disconnect
          </button>
        </form>
        <form action="{{ route('admin.reddit.connect') }}" method="POST">
          @csrf
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Re-authorize
          </button>
        </form>
      @else
        <form action="{{ route('admin.reddit.connect') }}" method="POST">
          @csrf
          <button type="submit" @disabled(!$ready)
                  class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-orange-500 to-rose-500 px-5 py-2.5 text-sm font-bold text-white shadow-soft hover:-translate-y-0.5 hover:shadow-pop disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 0A12 12 0 1 0 24 12 12 12 0 0 0 12 0Zm6.3 13.7a1 1 0 0 1 0 1.4 6.7 6.7 0 0 1-9.4 0 1 1 0 0 1 1.4-1.4 4.7 4.7 0 0 0 6.6 0 1 1 0 0 1 1.4 0Zm-9.4-3a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Zm5 0a1.5 1.5 0 1 1 1.5 1.5 1.5 1.5 0 0 1-1.5-1.5Z"/></svg>
            Connect Reddit
          </button>
        </form>
      @endif
    </div>

    @unless ($ready)
      <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
        <strong>Missing config.</strong> Set <code>REDDIT_CLIENT_ID</code> and
        <code>REDDIT_CLIENT_SECRET</code> in <code>.env</code>, then run
        <code>php artisan config:clear</code> and reload this page.
      </div>
    @endunless
  </div>

  {{-- ───── Reddit app settings reminder ───── --}}
  <div class="card p-6">
    <div class="text-sm font-semibold text-slate-500">Reddit app settings</div>
    <div class="mt-1 text-xs text-slate-500">
      Make sure these match the app at
      <a href="https://www.reddit.com/prefs/apps" target="_blank" rel="noopener" class="text-brand-600 hover:underline">reddit.com/prefs/apps</a>.
    </div>

    <dl class="mt-3 space-y-3 text-xs">
      <div>
        <dt class="font-semibold text-slate-500">App type</dt>
        <dd class="text-slate-800">web app (uses authorization code flow — same as Zapier)</dd>
      </div>
      <div>
        <dt class="font-semibold text-slate-500">Redirect URI <span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">must match exactly</span></dt>
        <dd class="mt-1 break-all rounded-lg bg-slate-100 px-3 py-2 font-mono text-[11px] text-slate-800">{{ $redirectUri }}</dd>
      </div>
      <div>
        <dt class="font-semibold text-slate-500">Subreddit</dt>
        <dd class="text-slate-800">r/{{ $cfg['subreddit'] ?? 'pdfbooks' }}</dd>
      </div>
      <div>
        <dt class="font-semibold text-slate-500">Master switch</dt>
        <dd class="text-slate-800">
          REDDIT_ENABLED =
          <code class="rounded {{ !empty($cfg['enabled']) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }} px-1.5 py-0.5">
            {{ !empty($cfg['enabled']) ? 'true' : 'false' }}
          </code>
        </dd>
      </div>
    </dl>
  </div>
</div>

{{-- ───── How it works ───── --}}
<div class="card mt-6 p-6">
  <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">How it works</h3>
  <ol class="mt-3 space-y-2 text-sm text-slate-700">
    <li><strong>1. Connect once.</strong> Click "Connect Reddit" — you'll be sent to Reddit's authorize page. Click Allow and you're done.</li>
    <li><strong>2. Tanbat stores a refresh token.</strong> No password is kept; just a token Reddit can revoke from your account settings.</li>
    <li><strong>3. Each new book post.</strong> The heartbeat picks up unposted books, uploads the cover as an image post, and adds the description as a top-level comment. Pacing: at most one post every {{ $cfg['post_spacing_minutes'] ?? 20 }} minutes.</li>
    <li><strong>4. Disable any time.</strong> Either set <code>REDDIT_ENABLED=false</code> in <code>.env</code>, or click Disconnect above.</li>
  </ol>
</div>

@endsection
