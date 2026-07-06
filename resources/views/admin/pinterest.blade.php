@extends('admin.layout')
@section('title', 'Pinterest Integration')
@section('breadcrumb', 'Integrations · Pinterest')
@section('heading', 'Pinterest cross-poster')

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
                   {{ $token ? 'bg-red-600' : 'bg-slate-300' }} text-white">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 0a12 12 0 0 0-4.37 23.17c-.1-.94-.2-2.4.04-3.44.22-.93 1.4-5.96 1.4-5.96s-.36-.72-.36-1.78c0-1.67.97-2.92 2.17-2.92 1.02 0 1.51.77 1.51 1.69 0 1.03-.65 2.56-.99 3.98-.28 1.19.6 2.16 1.77 2.16 2.12 0 3.76-2.24 3.76-5.48 0-2.86-2.06-4.86-5-4.86-3.41 0-5.41 2.56-5.41 5.2 0 1.03.4 2.13.89 2.73.1.12.11.22.08.34l-.33 1.35c-.05.22-.17.27-.4.16-1.49-.69-2.42-2.87-2.42-4.62 0-3.76 2.73-7.21 7.88-7.21 4.13 0 7.35 2.95 7.35 6.88 0 4.11-2.59 7.42-6.18 7.42-1.21 0-2.34-.63-2.73-1.37l-.74 2.83c-.27 1.03-1 2.32-1.48 3.11A12 12 0 1 0 12 0Z"/></svg>
      </span>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-slate-500">Connection</div>
        @if ($token)
          <div class="text-lg font-extrabold text-slate-900">
            Connected as <span class="text-red-600">{{ '@' . ($token->account_name ?: 'unknown') }}</span>
          </div>
          <div class="mt-0.5 text-xs text-slate-500">
            Scopes: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px]">{{ $token->scope ?: '—' }}</code>
            · access token expires {{ optional($token->expires_at)->diffForHumans() ?? 'soon' }}
          </div>
          <div class="mt-0.5 text-xs text-slate-500">
            Pinning to board:
            <strong class="text-slate-700">{{ $token->meta['board_name'] ?? ($boardId ?: 'none selected yet') }}</strong>
          </div>
        @else
          <div class="text-lg font-extrabold text-slate-900">Not connected</div>
          <div class="mt-0.5 text-xs text-slate-500">
            Click <strong>Connect Pinterest</strong> below to authorize Tanbat to pin on your behalf.
          </div>
        @endif
      </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-3">
      @if ($token)
        <form action="{{ route('admin.pinterest.disconnect') }}" method="POST">
          @csrf
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            Disconnect
          </button>
        </form>
        <form action="{{ route('admin.pinterest.connect') }}" method="POST">
          @csrf
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Re-authorize
          </button>
        </form>
      @else
        <form action="{{ route('admin.pinterest.connect') }}" method="POST">
          @csrf
          <button type="submit" @disabled(!$ready)
                  class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-soft hover:-translate-y-0.5 hover:shadow-pop hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 0a12 12 0 0 0-4.37 23.17c-.1-.94-.2-2.4.04-3.44.22-.93 1.4-5.96 1.4-5.96s-.36-.72-.36-1.78c0-1.67.97-2.92 2.17-2.92 1.02 0 1.51.77 1.51 1.69 0 1.03-.65 2.56-.99 3.98-.28 1.19.6 2.16 1.77 2.16 2.12 0 3.76-2.24 3.76-5.48 0-2.86-2.06-4.86-5-4.86-3.41 0-5.41 2.56-5.41 5.2 0 1.03.4 2.13.89 2.73.1.12.11.22.08.34l-.33 1.35c-.05.22-.17.27-.4.16-1.49-.69-2.42-2.87-2.42-4.62 0-3.76 2.73-7.21 7.88-7.21 4.13 0 7.35 2.95 7.35 6.88 0 4.11-2.59 7.42-6.18 7.42-1.21 0-2.34-.63-2.73-1.37l-.74 2.83c-.27 1.03-1 2.32-1.48 3.11A12 12 0 1 0 12 0Z"/></svg>
            Connect Pinterest
          </button>
        </form>
      @endif
    </div>

    @unless ($ready)
      <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
        <strong>Missing config.</strong> Set <code>PINTEREST_CLIENT_ID</code> and
        <code>PINTEREST_CLIENT_SECRET</code> in <code>.env</code>, then run
        <code>php artisan config:clear</code> and reload this page.
      </div>
    @endunless

    {{-- ───── Board picker (only once connected) ───── --}}
    @if ($token)
      <div class="mt-6 border-t border-slate-100 pt-5">
        <div class="text-sm font-bold text-slate-700">Destination board</div>
        <p class="mt-1 text-xs text-slate-500">New book pins are created on the board you choose here.</p>

        @if ($boardError)
          <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
            Couldn't load your boards: {{ $boardError }}. Try <strong>Re-authorize</strong>.
          </div>
        @elseif (empty($boards))
          <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
            No boards found on this account. Create a board on Pinterest, then reload this page.
          </div>
        @else
          <form action="{{ route('admin.pinterest.board') }}" method="POST" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <label class="block">
              <span class="mb-1 block text-xs font-semibold text-slate-500">Board</span>
              <select name="board_id" id="pinBoard" required
                      class="min-w-64 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-500 focus:ring-red-500"
                      onchange="document.getElementById('pinBoardName').value = this.options[this.selectedIndex].text">
                @foreach ($boards as $b)
                  <option value="{{ $b['id'] }}" @selected($boardId === $b['id'])>{{ $b['name'] }}</option>
                @endforeach
              </select>
            </label>
            <input type="hidden" name="board_name" id="pinBoardName"
                   value="{{ $token->meta['board_name'] ?? (collect($boards)->firstWhere('id', $boardId)['name'] ?? '') }}">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
              Save board
            </button>
          </form>
        @endif
      </div>
    @endif
  </div>

  {{-- ───── Pinterest app settings reminder ───── --}}
  <div class="card p-6">
    <div class="text-sm font-semibold text-slate-500">Pinterest app settings</div>
    <div class="mt-1 text-xs text-slate-500">
      Make sure these match the app at
      <a href="https://developers.pinterest.com/apps/" target="_blank" rel="noopener" class="text-brand-600 hover:underline">developers.pinterest.com/apps</a>.
    </div>

    <dl class="mt-3 space-y-3 text-xs">
      <div>
        <dt class="font-semibold text-slate-500">OAuth flow</dt>
        <dd class="text-slate-800">Authorization code (standard web app)</dd>
      </div>
      <div>
        <dt class="font-semibold text-slate-500">Redirect URI <span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">must match exactly</span></dt>
        <dd class="mt-1 break-all rounded-lg bg-slate-100 px-3 py-2 font-mono text-[11px] text-slate-800">{{ $redirectUri }}</dd>
        <dd class="mt-1 text-[11px] text-slate-500">Pinterest requires HTTPS here (localhost excepted).</dd>
      </div>
      <div>
        <dt class="font-semibold text-slate-500">Scopes</dt>
        <dd class="text-slate-800 break-all">{{ $cfg['scopes'] ?? '' }}</dd>
      </div>
      <div>
        <dt class="font-semibold text-slate-500">API host</dt>
        <dd class="text-slate-800 break-all">
          {{ $cfg['api_host'] ?? 'https://api.pinterest.com' }}
          @if(($cfg['api_host'] ?? '') === 'https://api-sandbox.pinterest.com')
            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">SANDBOX</span>
          @else
            <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">LIVE</span>
          @endif
        </dd>
      </div>
      <div>
        <dt class="font-semibold text-slate-500">Master switch</dt>
        <dd class="text-slate-800">
          PINTEREST_ENABLED =
          <code class="rounded {{ !empty($cfg['enabled']) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }} px-1.5 py-0.5">
            {{ !empty($cfg['enabled']) ? 'true' : 'false' }}
          </code>
        </dd>
      </div>
      <div>
        <dt class="font-semibold text-slate-500">Spacing</dt>
        <dd class="text-slate-800">one pin every {{ $cfg['post_spacing_minutes'] ?? 20 }} min</dd>
      </div>
    </dl>
  </div>
</div>

{{-- ───── Manual / production-limited token ───── --}}
<div class="card mt-6 p-6">
  <div class="flex items-start gap-3">
    <span class="grid h-9 w-9 flex-none place-items-center rounded-xl bg-amber-50 text-amber-600">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3"/></svg>
    </span>
    <div>
      <div class="text-sm font-bold text-slate-900">Paste a manual access token</div>
      <p class="mt-1 text-xs text-slate-500 max-w-2xl">
        While your app is in <strong>Trial</strong>, the OAuth flow can't create real Pins on the live API.
        Use the Pinterest dashboard's <strong>“Generate access token” → production limited</strong> feature,
        then paste the tokens here. This overrides the connection above and lets posting work on
        <code>{{ $cfg['api_host'] ?? 'https://api.pinterest.com' }}</code> right away.
      </p>
      <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
        <strong>Tick every scope below when generating the token</strong> — a token missing
        <code>boards:write</code> or <code>pins:write</code> will fail to create pins:
        <code class="ml-1 break-all">{{ $cfg['scopes'] ?? 'boards:read,boards:write,pins:read,pins:write,user_accounts:read' }}</code>
      </div>
    </div>
  </div>

  <form action="{{ route('admin.pinterest.token') }}" method="POST" class="mt-4 grid gap-3 sm:grid-cols-2">
    @csrf
    <label class="block sm:col-span-2">
      <span class="mb-1 block text-xs font-semibold text-slate-500">Access token <span class="text-rose-500">*</span></span>
      <input type="text" name="access_token" required autocomplete="off" spellcheck="false"
             placeholder="pina_..."
             class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono text-xs focus:border-red-500 focus:ring-red-500">
    </label>
    <label class="block sm:col-span-2">
      <span class="mb-1 block text-xs font-semibold text-slate-500">Refresh token <span class="text-slate-400">(optional, recommended)</span></span>
      <input type="text" name="refresh_token" autocomplete="off" spellcheck="false"
             placeholder="pinr_..."
             class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono text-xs focus:border-red-500 focus:ring-red-500">
      <span class="mt-1 block text-[11px] text-slate-400">Without a refresh token you'll have to regenerate when the access token expires.</span>
    </label>
    <div class="sm:col-span-2">
      <button type="submit"
              class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
        Save token
      </button>
    </div>
  </form>
</div>

{{-- ───── How it works ───── --}}
<div class="card mt-6 p-6">
  <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">How it works</h3>
  <ol class="mt-3 space-y-2 text-sm text-slate-700">
    <li><strong>1. Connect once.</strong> Click "Connect Pinterest" — authorize on Pinterest, and you're returned here.</li>
    <li><strong>2. Pick a board.</strong> Choose which board new book pins land on, then "Save board".</li>
    <li><strong>3. Each new book.</strong> The heartbeat picks up un-pinned books and creates a Pin with the cover image, the book title, the description, and a link back to the book post on tanbat.com. Pacing: at most one pin every {{ $cfg['post_spacing_minutes'] ?? 20 }} minutes.</li>
    <li><strong>4. Disable any time.</strong> Set <code>PINTEREST_ENABLED=false</code> in <code>.env</code>, or click Disconnect above.</li>
  </ol>
</div>

@endsection
