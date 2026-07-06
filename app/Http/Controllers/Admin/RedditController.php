<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OAuthToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin-only Reddit OAuth connection (authorization-code flow).
 *
 * Flow:
 *   GET /admin/reddit                → status page
 *   POST /admin/reddit/connect       → generates state, redirects to Reddit
 *   GET /admin/reddit/callback       → returns from Reddit, exchanges code
 *                                       for an access + refresh token,
 *                                       persists in oauth_tokens
 *   POST /admin/reddit/disconnect    → wipes the stored tokens
 *
 * The redirect URI is computed from APP_URL + the route, so the value the
 * admin registers in the Reddit app's settings must exactly match
 * `{APP_URL}/admin/reddit/callback`.
 */
class RedditController extends Controller
{
    public function index()
    {
        $cfg   = (array) config('reddit');
        $token = OAuthToken::where('provider', 'reddit')->first();
        $redirectUri = route('admin.reddit.callback');

        return view('admin.reddit', [
            'cfg'         => $cfg,
            'token'       => $token,
            'redirectUri' => $redirectUri,
            'ready'       => !empty($cfg['client_id']) && !empty($cfg['client_secret']),
        ]);
    }

    /** Start the dance: redirect to Reddit's authorize page. */
    public function connect(Request $request)
    {
        $cfg = (array) config('reddit');
        if (empty($cfg['client_id'])) {
            return back()->with('error', 'REDDIT_CLIENT_ID is not configured.');
        }

        $state = Str::random(40);
        $request->session()->put('reddit_oauth_state', $state);

        $params = http_build_query([
            'client_id'     => $cfg['client_id'],
            'response_type' => 'code',
            'state'         => $state,
            'redirect_uri'  => route('admin.reddit.callback'),
            'duration'      => 'permanent',
            'scope'         => 'identity submit read',
        ]);

        return redirect()->away('https://www.reddit.com/api/v1/authorize?' . $params);
    }

    /** Reddit redirects here after the user clicks Allow / Decline. */
    public function callback(Request $request)
    {
        $cfg = (array) config('reddit');

        // Reddit denied / user cancelled.
        if ($request->filled('error')) {
            return redirect()->route('admin.reddit.index')
                ->with('error', 'Reddit returned: ' . $request->query('error'));
        }

        // Same-origin CSRF guard via the random state we stashed in the session.
        $expected = $request->session()->pull('reddit_oauth_state');
        if (!$expected || !hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('admin.reddit.index')
                ->with('error', 'State mismatch — please start the flow again.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('admin.reddit.index')
                ->with('error', 'Reddit did not return an authorization code.');
        }

        // Exchange code → tokens.
        $res = Http::withBasicAuth($cfg['client_id'], $cfg['client_secret'])
            ->withHeaders(['User-Agent' => $cfg['user_agent']])
            ->asForm()
            ->timeout(20)
            ->post('https://www.reddit.com/api/v1/access_token', [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => route('admin.reddit.callback'),
            ]);

        if (!$res->successful() || empty($res->json('access_token'))) {
            Log::warning('Reddit code exchange failed', [
                'status' => $res->status(),
                'body'   => $res->body(),
            ]);
            return redirect()->route('admin.reddit.index')
                ->with('error', 'Token exchange failed: ' . $res->status() . ' ' . $res->body());
        }

        $access  = (string) $res->json('access_token');
        $refresh = (string) ($res->json('refresh_token') ?? '');
        $scope   = (string) ($res->json('scope') ?? '');
        $expires = (int) ($res->json('expires_in') ?? 3600);

        if ($refresh === '') {
            // duration=permanent should always return a refresh token — but
            // surface the issue rather than silently storing a 1-hour-only
            // access token.
            return redirect()->route('admin.reddit.index')
                ->with('error', 'Reddit did not return a refresh token. Re-run the connect flow; the app must allow permanent access.');
        }

        // Confirm which account we just authorized as.
        $me = Http::withToken($access)
            ->withHeaders(['User-Agent' => $cfg['user_agent']])
            ->timeout(15)
            ->get('https://oauth.reddit.com/api/v1/me');
        $accountName = $me->json('name') ?? null;

        OAuthToken::updateOrCreate(
            ['provider' => 'reddit'],
            [
                'access_token'  => $access,
                'refresh_token' => $refresh,
                'scope'         => $scope,
                'account_name'  => $accountName,
                'expires_at'    => now()->addSeconds($expires),
            ]
        );

        return redirect()->route('admin.reddit.index')
            ->with('success', 'Connected Reddit as @' . ($accountName ?: 'unknown') . '.');
    }

    public function disconnect(Request $request)
    {
        OAuthToken::where('provider', 'reddit')->delete();
        return redirect()->route('admin.reddit.index')
            ->with('success', 'Reddit account disconnected.');
    }
}
