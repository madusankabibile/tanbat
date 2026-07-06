<?php

namespace App\Services;

use App\Models\BookDetail;
use App\Models\OAuthToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Posts new Tanbat books to a configured subreddit as image posts.
 *
 * Flow:
 *   1. OAuth password grant → access_token (cached).
 *   2. Download the book's cover image to a temp file.
 *   3. Get an upload lease from /api/media/asset.json.
 *   4. POST the binary to the lease's S3 URL.
 *   5. Submit kind=image to /api/submit with the uploaded asset URL.
 *   6. Post the description + a direct link to the book's tanbat.com page
 *      (download open to guests) as a top-level comment on the new submission.
 *
 * All credentials live in config/reddit.php — the heartbeat checks the
 * `enabled` flag before instantiating this service.
 */
class RedditPoster
{
    private const OAUTH_TOKEN_URL = 'https://www.reddit.com/api/v1/access_token';
    private const API_BASE        = 'https://oauth.reddit.com';
    private const TOKEN_CACHE_KEY = 'reddit:access_token';
    // Reddit tokens last 1h; refresh slightly early so we never hand out a
    // token that's about to expire mid-call.
    private const TOKEN_TTL       = 3500;

    private array $cfg;

    public function __construct(array $config = null)
    {
        $this->cfg = $config ?? (array) config('reddit');
    }

    /** Is the integration configured well enough to attempt a post? */
    public function isReady(): bool
    {
        if (empty($this->cfg['enabled'])) return false;
        foreach (['client_id', 'client_secret', 'user_agent', 'subreddit'] as $k) {
            if (empty($this->cfg[$k])) return false;
        }
        // We require an OAuth connection (refresh token persisted via the
        // /admin/reddit "Connect Reddit" flow). The legacy password-grant
        // path inside getAccessToken() is kept for script-type apps but is
        // intentionally not considered "ready" — it can't reach Reddit
        // without an admin having opted in to it.
        return $this->hasRefreshToken();
    }

    public function hasRefreshToken(): bool
    {
        $row = OAuthToken::where('provider', 'reddit')->first();
        return $row && !empty($row->refresh_token);
    }

    /** Account name (Reddit username) we'll be posting as, or null. */
    public function connectedAccount(): ?string
    {
        $row = OAuthToken::where('provider', 'reddit')->first();
        return $row?->account_name;
    }

    /**
     * Cross-post a book. Returns the Reddit fullname ("t3_xxx") on success.
     * Throws RuntimeException on any unrecoverable failure — the caller is
     * expected to catch it and bump retry counters.
     */
    public function postBook(BookDetail $book): string
    {
        if (!$this->isReady()) {
            throw new RuntimeException('Reddit integration is not configured (config/reddit.php).');
        }

        if (empty($book->cover_url)) {
            throw new RuntimeException('Book has no cover image to upload.');
        }

        $username = $book->post?->user?->username ?? 'a member';
        $title = $this->renderTemplate($this->cfg['title_template'], [
            '{title}'    => $book->title,
            '{username}' => $username,
        ]);
        // Reddit caps post titles at 300 chars.
        if (mb_strlen($title) > 300) $title = mb_substr($title, 0, 297) . '…';

        // Public book page — the download button there is open to guests, so
        // this doubles as the "direct download" link in the Reddit comment.
        $downloadLink = url('/books/' . $book->slug);

        $comment = $this->renderTemplate($this->cfg['comment_template'], [
            '{title}'       => $book->title,
            '{username}'    => $username,
            '{description}' => $book->description ?: '(no description provided)',
            '{download}'    => $downloadLink,
        ]);

        $token = $this->getAccessToken();

        // 1. Download the cover.
        [$imagePath, $mimeType] = $this->downloadCover($book->cover_url);

        try {
            // 2. Get upload lease.
            $lease = $this->requestUploadLease($token, $imagePath, $mimeType);

            // 3. Upload to S3.
            $assetUrl = $this->uploadToS3($lease, $imagePath, $mimeType);

            // 4. Submit the image post.
            $postFullname = $this->submitImagePost($token, $title, $assetUrl);

            // 5. Add description as a comment.
            try {
                $this->addComment($token, $postFullname, $comment);
            } catch (\Throwable $e) {
                // The post itself succeeded — log but don't unwind.
                Log::warning('Reddit comment failed for ' . $postFullname . ': ' . $e->getMessage());
            }

            return $postFullname;
        } finally {
            @unlink($imagePath);
        }
    }

    private function renderTemplate(string $tpl, array $values): string
    {
        return strtr($tpl, $values);
    }

    /**
     * Mint an access token. Prefers the refresh-token flow (web app, what
     * Zapier uses); falls back to password grant (script app) if no refresh
     * token is stored.
     *
     * Access tokens are cached on the OAuthToken row, so we only call Reddit
     * once per ~58-minute window even across many heartbeat ticks.
     */
    private function getAccessToken(): string
    {
        // Refresh-token flow
        $row = OAuthToken::where('provider', 'reddit')->first();
        if ($row && !empty($row->refresh_token)) {
            if (!empty($row->access_token) && !$row->isExpired()) {
                return $row->access_token;
            }
            return $this->refreshAccessToken($row);
        }

        // Legacy password grant — only works for script-type apps.
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') return $cached;

        $res = Http::withBasicAuth($this->cfg['client_id'], $this->cfg['client_secret'])
            ->withHeaders(['User-Agent' => $this->cfg['user_agent']])
            ->asForm()
            ->timeout(20)
            ->post(self::OAUTH_TOKEN_URL, [
                'grant_type' => 'password',
                'username'   => $this->cfg['username'],
                'password'   => $this->cfg['password'],
            ]);

        if (!$res->successful() || empty($res->json('access_token'))) {
            throw new RuntimeException('Reddit OAuth failed: ' . $res->status() . ' ' . $res->body());
        }

        $token = (string) $res->json('access_token');
        Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL);
        return $token;
    }

    /**
     * Exchange the stored refresh_token for a fresh access_token and persist
     * the new pair. Reddit's refresh tokens are long-lived (effectively
     * permanent unless the user revokes), so we never refresh the refresh.
     */
    private function refreshAccessToken(OAuthToken $row): string
    {
        $res = Http::withBasicAuth($this->cfg['client_id'], $this->cfg['client_secret'])
            ->withHeaders(['User-Agent' => $this->cfg['user_agent']])
            ->asForm()
            ->timeout(20)
            ->post(self::OAUTH_TOKEN_URL, [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $row->refresh_token,
            ]);

        if (!$res->successful() || empty($res->json('access_token'))) {
            throw new RuntimeException('Reddit token refresh failed: ' . $res->status() . ' ' . $res->body());
        }

        $access = (string) $res->json('access_token');
        $expiresIn = (int) ($res->json('expires_in') ?? 3600);
        $row->update([
            'access_token' => $access,
            'expires_at'   => now()->addSeconds($expiresIn),
            'scope'        => $res->json('scope') ?? $row->scope,
        ]);

        return $access;
    }

    /** Download the cover to a temp file. Returns [path, mime]. */
    private function downloadCover(string $url): array
    {
        $maxBytes = (int) ($this->cfg['max_image_bytes'] ?? 10 * 1024 * 1024);

        $res = Http::withHeaders(['User-Agent' => $this->cfg['user_agent']])
            ->timeout(45)
            ->withOptions(['stream' => false])
            ->get($url);

        if (!$res->successful()) {
            throw new RuntimeException('Cover download failed: HTTP ' . $res->status());
        }
        $body = $res->body();
        if (strlen($body) === 0)            throw new RuntimeException('Cover download returned empty body.');
        if (strlen($body) > $maxBytes)      throw new RuntimeException('Cover image exceeds size cap.');

        $mime = $res->header('Content-Type') ?: 'image/jpeg';
        $mime = trim(explode(';', $mime)[0]); // strip charset
        $ext  = $this->mimeToExt($mime);
        if (!$ext) throw new RuntimeException('Unsupported cover mimetype: ' . $mime);

        $path = tempnam(sys_get_temp_dir(), 'tanbat_book_') . '.' . $ext;
        file_put_contents($path, $body);
        return [$path, $mime];
    }

    private function mimeToExt(string $mime): ?string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => null,
        };
    }

    /**
     * Ask Reddit for an S3 upload lease.
     *
     * Returns the raw `args` payload from Reddit: action URL + the list of
     * signed fields that MUST be sent as multipart with the file.
     */
    private function requestUploadLease(string $token, string $imagePath, string $mimeType): array
    {
        $res = Http::withToken($token)
            ->withHeaders(['User-Agent' => $this->cfg['user_agent']])
            ->asForm()
            ->timeout(20)
            ->post(self::API_BASE . '/api/media/asset.json', [
                'filepath' => basename($imagePath),
                'mimetype' => $mimeType,
            ]);

        if (!$res->successful()) {
            throw new RuntimeException('asset.json failed: ' . $res->status() . ' ' . $res->body());
        }
        $json = $res->json();
        if (empty($json['args']['action']) || empty($json['args']['fields'])) {
            throw new RuntimeException('asset.json missing args/fields: ' . $res->body());
        }
        return $json;
    }

    /**
     * Upload the file to S3 using the lease's signed multipart fields, then
     * return the public URL we'll later hand to /api/submit.
     */
    private function uploadToS3(array $lease, string $imagePath, string $mimeType): string
    {
        $action = $lease['args']['action'];
        // Reddit returns protocol-relative URLs ("//reddit-uploaded-media…").
        if (str_starts_with($action, '//')) $action = 'https:' . $action;

        $multipart = [];
        foreach ($lease['args']['fields'] as $field) {
            $multipart[] = [
                'name'     => $field['name'],
                'contents' => $field['value'],
            ];
        }
        $multipart[] = [
            'name'     => 'file',
            'contents' => fopen($imagePath, 'rb'),
            'filename' => basename($imagePath),
            'headers'  => ['Content-Type' => $mimeType],
        ];

        // Laravel's HTTP client wraps Guzzle but doesn't expose multipart with
        // arbitrary signed fields cleanly, so we drop to Guzzle directly here.
        $client = new \GuzzleHttp\Client();
        try {
            $resp = $client->request('POST', $action, [
                'headers'   => ['User-Agent' => $this->cfg['user_agent']],
                'multipart' => $multipart,
                'timeout'   => 90,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('S3 upload failed: ' . $e->getMessage());
        }

        // 2xx is success; the key field tells us the final object path.
        $status = $resp->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('S3 upload non-2xx: ' . $status . ' ' . (string) $resp->getBody());
        }

        $key = collect($lease['args']['fields'])->firstWhere('name', 'key')['value'] ?? null;
        if (!$key) throw new RuntimeException('Upload lease missing key field.');

        return rtrim($action, '/') . '/' . ltrim($key, '/');
    }

    /** kind=image submission. Returns the Reddit fullname (t3_xxx). */
    private function submitImagePost(string $token, string $title, string $imageUrl): string
    {
        $res = Http::withToken($token)
            ->withHeaders(['User-Agent' => $this->cfg['user_agent']])
            ->asForm()
            ->timeout(30)
            ->post(self::API_BASE . '/api/submit', [
                'api_type' => 'json',
                'kind'     => 'image',
                'sr'       => $this->cfg['subreddit'],
                'title'    => $title,
                'url'      => $imageUrl,
                'sendreplies' => false,
                'resubmit'    => true,
            ]);

        if (!$res->successful()) {
            throw new RuntimeException('Submit failed: ' . $res->status() . ' ' . $res->body());
        }
        $json = $res->json();
        $errors = $json['json']['errors'] ?? [];
        if (!empty($errors)) {
            throw new RuntimeException('Submit returned errors: ' . json_encode($errors));
        }

        // Reddit's "kind=image" response is async: it returns a websocket_url
        // we *could* listen on for the final post id, but PHP websocket support
        // is shaky. Easier: poll /r/{sub}/new looking for our brand-new post.
        // Use the title as the dedup key — it's unique per Tanbat book.
        $data = $json['json']['data'] ?? [];

        // 1. Some sub configs DO put the fullname here. Honour it if present.
        if (!empty($data['name']) && str_starts_with($data['name'], 't3_')) {
            return $data['name'];
        }
        if (!empty($data['url']) && preg_match('~/comments/([a-z0-9]+)/~i', $data['url'], $m)) {
            return 't3_' . $m[1];
        }

        // 2. Fallback: scan /r/{sub}/new for the bot's most recent submission
        //    whose title matches. Reddit usually surfaces it within a few
        //    seconds — poll up to ~12s before giving up.
        return $this->discoverPostFullname($token, $title);
    }

    private function discoverPostFullname(string $token, string $title): string
    {
        $needle = mb_strtolower(trim($title));
        for ($i = 0; $i < 6; $i++) {
            sleep(2);
            $res = Http::withToken($token)
                ->withHeaders(['User-Agent' => $this->cfg['user_agent']])
                ->timeout(15)
                ->get(self::API_BASE . '/r/' . $this->cfg['subreddit'] . '/new', [
                    'limit' => 15,
                ]);
            if (!$res->successful()) continue;

            $items = $res->json('data.children') ?? [];
            foreach ($items as $it) {
                $d = $it['data'] ?? [];
                if (($d['author'] ?? '') !== $this->cfg['username']) continue;
                if (mb_strtolower(trim($d['title'] ?? '')) !== $needle) continue;
                return 't3_' . ($d['id'] ?? '');
            }
        }
        throw new RuntimeException('Post submitted but its fullname could not be discovered in /r/' . $this->cfg['subreddit'] . '/new after 12s.');
    }

    private function addComment(string $token, string $parentFullname, string $body): void
    {
        $res = Http::withToken($token)
            ->withHeaders(['User-Agent' => $this->cfg['user_agent']])
            ->asForm()
            ->timeout(20)
            ->post(self::API_BASE . '/api/comment', [
                'api_type' => 'json',
                'thing_id' => $parentFullname,
                'text'     => $body,
            ]);

        if (!$res->successful()) {
            throw new RuntimeException('Comment failed: ' . $res->status() . ' ' . $res->body());
        }
        $errors = $res->json('json.errors') ?? [];
        if (!empty($errors)) {
            throw new RuntimeException('Comment errors: ' . json_encode($errors));
        }
    }
}
