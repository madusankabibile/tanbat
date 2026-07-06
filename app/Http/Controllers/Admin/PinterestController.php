<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OAuthToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin-only Pinterest OAuth connection (authorization-code flow) + board
 * selection. Mirrors RedditController.
 *
 * Flow:
 *   GET  /admin/pinterest               → status + board picker page
 *   POST /admin/pinterest/connect       → generate state, redirect to Pinterest
 *   GET  /admin/pinterest/callback      → exchange code → access + refresh token
 *   POST /admin/pinterest/board         → save which board pins go to
 *   POST /admin/pinterest/disconnect    → wipe stored tokens
 *
 * The redirect URI is config('pinterest.redirect_uri') if set, else derived
 * from APP_URL via the route helper. Whatever it resolves to must match the
 * redirect URI registered in the Pinterest app dashboard exactly, e.g.
 * https://tanbat.com/admin/pinterest/callback
 */
class PinterestController extends Controller
{
    private const AUTH_URL  = 'https://www.pinterest.com/oauth/';

    /** v5 token endpoint, honouring the configurable api_host (live/sandbox). */
    private function tokenUrl(): string
    {
        return rtrim((string) config('pinterest.api_host', 'https://api.pinterest.com'), '/') . '/v5/oauth/token';
    }

    /** v5 API base, honouring the configurable api_host (live/sandbox). */
    private function apiBase(): string
    {
        return rtrim((string) config('pinterest.api_host', 'https://api.pinterest.com'), '/') . '/v5';
    }

    public function index()
    {
        $cfg   = (array) config('pinterest');
        $token = OAuthToken::where('provider', 'pinterest')->first();

        // Live board list (best-effort) so the admin can pick a destination.
        $boards = [];
        $boardError = null;
        if ($token && !empty($token->refresh_token)) {
            try {
                $boards = $this->fetchBoards($cfg, $token);
            } catch (\Throwable $e) {
                $boardError = $e->getMessage();
            }
        }

        return view('admin.pinterest', [
            'cfg'         => $cfg,
            'token'       => $token,
            'redirectUri' => $this->redirectUri(),
            'ready'       => !empty($cfg['client_id']) && !empty($cfg['client_secret']),
            'boards'      => $boards,
            'boardError'  => $boardError,
            'boardId'     => $token->meta['board_id'] ?? null,
        ]);
    }

    /** Start the dance: redirect to Pinterest's authorize page. */
    public function connect(Request $request)
    {
        $cfg = (array) config('pinterest');
        if (empty($cfg['client_id'])) {
            return back()->with('error', 'PINTEREST_CLIENT_ID is not configured.');
        }

        $state = Str::random(40);
        $request->session()->put('pinterest_oauth_state', $state);

        $params = http_build_query([
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => $cfg['scopes'] ?? 'boards:read,pins:read,pins:write,user_accounts:read',
            'state'         => $state,
        ]);

        return redirect()->away(self::AUTH_URL . '?' . $params);
    }

    /** Pinterest redirects here after the user clicks Allow / Decline. */
    public function callback(Request $request)
    {
        $cfg = (array) config('pinterest');

        if ($request->filled('error')) {
            return redirect()->route('admin.pinterest.index')
                ->with('error', 'Pinterest returned: ' . $request->query('error'));
        }

        $expected = $request->session()->pull('pinterest_oauth_state');
        if (!$expected || !hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('admin.pinterest.index')
                ->with('error', 'State mismatch — please start the flow again.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('admin.pinterest.index')
                ->with('error', 'Pinterest did not return an authorization code.');
        }

        // Exchange code → tokens. Pinterest expects Basic-auth client creds +
        // a form body with the redirect_uri that was used to start the flow.
        $res = Http::asForm()
            ->withBasicAuth($cfg['client_id'], $cfg['client_secret'])
            ->acceptJson()
            ->timeout(20)
            ->post($this->tokenUrl(), [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $this->redirectUri(),
            ]);

        if (!$res->successful() || empty($res->json('access_token'))) {
            Log::warning('Pinterest code exchange failed', [
                'status' => $res->status(),
                'body'   => $res->body(),
            ]);
            return redirect()->route('admin.pinterest.index')
                ->with('error', 'Token exchange failed: ' . $res->status() . ' ' . $res->body());
        }

        $access  = (string) $res->json('access_token');
        $refresh = (string) ($res->json('refresh_token') ?? '');
        $scope   = (string) ($res->json('scope') ?? ($cfg['scopes'] ?? ''));
        $expires = (int) ($res->json('expires_in') ?? 3600);

        if ($refresh === '') {
            return redirect()->route('admin.pinterest.index')
                ->with('error', 'Pinterest did not return a refresh token. Re-run the connect flow.');
        }

        // Which account did we just authorize as?
        $accountName = null;
        try {
            $me = Http::withToken($access)->acceptJson()->timeout(15)
                ->get($this->apiBase() . '/user_account');
            $accountName = $me->json('username') ?? null;
        } catch (\Throwable $e) {
            // Non-fatal — the token is what matters.
        }

        $existing = OAuthToken::where('provider', 'pinterest')->first();
        OAuthToken::updateOrCreate(
            ['provider' => 'pinterest'],
            [
                'access_token'  => $access,
                'refresh_token' => $refresh,
                'scope'         => $scope,
                'account_name'  => $accountName,
                // Preserve a previously chosen board across re-authorization.
                'meta'          => $existing->meta ?? null,
                'expires_at'    => now()->addSeconds($expires),
            ]
        );

        return redirect()->route('admin.pinterest.index')
            ->with('success', 'Connected Pinterest as @' . ($accountName ?: 'unknown') . '. Now choose a board to pin to.');
    }

    /** Persist the board new pins should be created on. */
    public function board(Request $request)
    {
        $data = $request->validate([
            'board_id'   => ['required', 'string', 'max:64'],
            'board_name' => ['nullable', 'string', 'max:191'],
        ]);

        $token = OAuthToken::where('provider', 'pinterest')->first();
        if (!$token) {
            return redirect()->route('admin.pinterest.index')
                ->with('error', 'Connect Pinterest before choosing a board.');
        }

        $meta = $token->meta ?? [];
        $meta['board_id']   = $data['board_id'];
        $meta['board_name'] = $data['board_name'] ?? null;
        $token->update(['meta' => $meta]);

        return redirect()->route('admin.pinterest.index')
            ->with('success', 'Pins will be posted to "' . ($data['board_name'] ?: $data['board_id']) . '".');
    }

    /**
     * Save a manually generated token (the "Generate access token →
     * production limited" path in the Pinterest app dashboard). This lets you
     * post to the live API before full production approval lands, without
     * running the OAuth redirect flow.
     *
     * Pinterest hands you an access token and (usually) a refresh token. We
     * verify the access token works by hitting /user_account, then persist it.
     */
    public function token(Request $request)
    {
        $data = $request->validate([
            'access_token'  => ['required', 'string', 'max:2000'],
            'refresh_token' => ['nullable', 'string', 'max:2000'],
        ]);

        $access  = trim($data['access_token']);
        $refresh = trim((string) ($data['refresh_token'] ?? ''));

        // Verify the token + grab the account name.
        $me = Http::withToken($access)->acceptJson()->timeout(15)
            ->get($this->apiBase() . '/user_account');

        if (!$me->successful()) {
            return back()->with('error', 'That token was rejected by Pinterest: '
                . $me->status() . ' ' . $me->body());
        }
        $accountName = $me->json('username') ?? null;

        $existing = OAuthToken::where('provider', 'pinterest')->first();
        OAuthToken::updateOrCreate(
            ['provider' => 'pinterest'],
            [
                'access_token'  => $access,
                // Keep any previously stored refresh token if a new one isn't given.
                'refresh_token' => $refresh !== '' ? $refresh : ($existing->refresh_token ?? null),
                'scope'         => $existing->scope ?? (config('pinterest.scopes') ?? ''),
                'account_name'  => $accountName,
                'meta'          => $existing->meta ?? null,
                // Manual tokens are long-lived; refresh kicks in once expired
                // (if a refresh token is present). 25 days is a safe window.
                'expires_at'    => now()->addDays(25),
            ]
        );

        $note = $refresh === '' ? ' (no refresh token saved — you\'ll need to regenerate the token when it expires)' : '';
        return back()->with('success', 'Saved manual token for @' . ($accountName ?: 'unknown') . '.' . $note);
    }

    public function disconnect(Request $request)
    {
        OAuthToken::where('provider', 'pinterest')->delete();
        return redirect()->route('admin.pinterest.index')
            ->with('success', 'Pinterest account disconnected.');
    }

    /**
     * Resolve the redirect URI. Prefer the explicit config override (set this
     * to your public https URL when APP_URL differs); otherwise derive from
     * the named route (APP_URL based).
     */
    private function redirectUri(): string
    {
        $override = trim((string) config('pinterest.redirect_uri', ''));
        return $override !== '' ? $override : route('admin.pinterest.callback');
    }

    /** Fetch the connected account's boards (id + name) for the picker. */
    private function fetchBoards(array $cfg, OAuthToken $token): array
    {
        // Make sure we hold a usable access token. Reuse the poster's refresh
        // logic by minting through it lazily here.
        $access = $token->access_token;
        if (empty($access) || $token->isExpired()) {
            $res = Http::asForm()
                ->withBasicAuth($cfg['client_id'], $cfg['client_secret'])
                ->acceptJson()->timeout(20)
                ->post($this->tokenUrl(), [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $token->refresh_token,
                ]);
            if (!$res->successful() || empty($res->json('access_token'))) {
                throw new \RuntimeException('Could not refresh token to list boards: ' . $res->status());
            }
            $access = (string) $res->json('access_token');
            $token->update([
                'access_token' => $access,
                'expires_at'   => now()->addSeconds((int) ($res->json('expires_in') ?? 3600)),
            ]);
        }

        $res = Http::withToken($access)->acceptJson()->timeout(20)
            ->get($this->apiBase() . '/boards', ['page_size' => 100]);

        if (!$res->successful()) {
            throw new \RuntimeException('Boards request failed: ' . $res->status() . ' ' . $res->body());
        }

        return collect($res->json('items') ?? [])
            ->map(fn ($b) => ['id' => (string) ($b['id'] ?? ''), 'name' => (string) ($b['name'] ?? '(untitled)')])
            ->filter(fn ($b) => $b['id'] !== '')
            ->values()
            ->all();
    }
}
