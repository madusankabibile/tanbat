<?php

namespace App\Services;

use App\Models\BookDetail;
use App\Models\OAuthToken;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Posts new Tanbat books to Pinterest as Pins.
 *
 * Pinterest's v5 API is simpler than Reddit's: a Pin can reference the cover
 * by `image_url` directly (no S3 upload lease), and carries a native
 * destination `link`. So a single POST /v5/pins call sends the image, title,
 * description and the link back to the book post.
 *
 * Flow:
 *   1. Refresh-token grant → access_token (cached on the OAuthToken row).
 *   2. POST /v5/pins with board_id + media_source(image_url) + title +
 *      description + link.
 *
 * Credentials live in config/pinterest.php; the OAuth connection + selected
 * board are stored on the oauth_tokens row (provider = "pinterest"). The
 * heartbeat checks the `enabled` flag before instantiating this service.
 */
class PinterestPoster
{
    private array $cfg;
    private string $apiBase;
    private string $tokenUrl;

    public function __construct(array $config = null)
    {
        $this->cfg = $config ?? (array) config('pinterest');
        $host = rtrim((string) ($this->cfg['api_host'] ?? 'https://api.pinterest.com'), '/');
        $this->apiBase  = $host . '/v5';
        $this->tokenUrl = $host . '/v5/oauth/token';
    }

    /** Is the integration configured + connected well enough to post? */
    public function isReady(): bool
    {
        if (empty($this->cfg['enabled'])) return false;
        if (empty($this->cfg['client_id']) || empty($this->cfg['client_secret'])) return false;

        $row = OAuthToken::where('provider', 'pinterest')->first();
        return $row
            && !empty($row->refresh_token)
            && !empty($row->meta['board_id'] ?? null);
    }

    public function hasRefreshToken(): bool
    {
        $row = OAuthToken::where('provider', 'pinterest')->first();
        return $row && !empty($row->refresh_token);
    }

    /** Pinterest username we'll be pinning as, or null. */
    public function connectedAccount(): ?string
    {
        return OAuthToken::where('provider', 'pinterest')->first()?->account_name;
    }

    /** The board id pins are sent to, or null if none chosen yet. */
    public function boardId(): ?string
    {
        return OAuthToken::where('provider', 'pinterest')->first()?->meta['board_id'] ?? null;
    }

    /**
     * Cross-post a book. Returns the Pinterest pin id on success.
     * Throws RuntimeException on any unrecoverable failure — the caller bumps
     * retry counters.
     */
    public function postBook(BookDetail $book): string
    {
        if (!$this->isReady()) {
            throw new RuntimeException('Pinterest integration is not configured/connected, or no board is selected.');
        }
        if (empty($book->cover_url)) {
            throw new RuntimeException('Book has no cover image for the pin.');
        }

        $row     = OAuthToken::where('provider', 'pinterest')->first();
        $boardId = (string) ($row->meta['board_id'] ?? '');
        if ($boardId === '') {
            throw new RuntimeException('No Pinterest board selected.');
        }

        $username = $book->post?->user?->username ?? 'a member';

        $titleMax = (int) ($this->cfg['title_max'] ?? 100);
        $descMax  = (int) ($this->cfg['description_max'] ?? 800);

        $title = $this->renderTemplate($this->cfg['title_template'] ?? '{title}', [
            '{title}'    => $book->title,
            '{username}' => $username,
        ]);
        $title = $this->clip($title !== '' ? $title : $book->title, $titleMax);

        $description = $this->renderTemplate($this->cfg['description_template'] ?? '{description}', [
            '{title}'       => $book->title,
            '{username}'    => $username,
            '{description}' => $book->description ?: $book->title,
        ]);
        $description = $this->clip($description, $descMax);

        $link  = $this->bookLink($book);
        $token = $this->getAccessToken();

        $payload = [
            'board_id'     => $boardId,
            'title'        => $title,
            'description'  => $description,
            'link'         => $link,
            'media_source' => [
                'source_type' => 'image_url',
                'url'         => $book->cover_url,
            ],
        ];
        if ($altText = $this->clip($book->title, 500)) {
            $payload['alt_text'] = $altText;
        }

        $res = Http::withToken($token)
            ->acceptJson()
            ->timeout(45)
            ->post($this->apiBase . '/pins', $payload);

        if (!$res->successful()) {
            throw new RuntimeException('Pin create failed: ' . $res->status() . ' ' . $res->body());
        }

        $id = (string) ($res->json('id') ?? '');
        if ($id === '') {
            throw new RuntimeException('Pin created but no id returned: ' . $res->body());
        }
        return $id;
    }

    /** Public link to the book post on Tanbat. */
    private function bookLink(BookDetail $book): string
    {
        // route('books.show') derives from APP_URL — on production that's
        // https://tanbat.com, giving the correct shareable pin destination.
        if (!empty($book->slug)) {
            return route('books.show', $book->slug);
        }
        return rtrim(config('app.url'), '/') . '/books';
    }

    private function renderTemplate(string $tpl, array $values): string
    {
        return trim(strtr($tpl, $values));
    }

    private function clip(string $s, int $max): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) return $s;
        return rtrim(mb_substr($s, 0, $max - 1)) . '…';
    }

    /**
     * Mint an access token via the stored refresh token, caching it on the
     * OAuthToken row so we only hit Pinterest once per ~expiry window across
     * many heartbeat ticks.
     */
    private function getAccessToken(): string
    {
        $row = OAuthToken::where('provider', 'pinterest')->first();
        if (!$row || empty($row->refresh_token)) {
            throw new RuntimeException('Pinterest is not connected (no refresh token).');
        }
        if (!empty($row->access_token) && !$row->isExpired()) {
            return $row->access_token;
        }
        return $this->refreshAccessToken($row);
    }

    private function refreshAccessToken(OAuthToken $row): string
    {
        $res = Http::asForm()
            ->withBasicAuth($this->cfg['client_id'], $this->cfg['client_secret'])
            ->acceptJson()
            ->timeout(20)
            ->post($this->tokenUrl, [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $row->refresh_token,
            ]);

        if (!$res->successful() || empty($res->json('access_token'))) {
            throw new RuntimeException('Pinterest token refresh failed: ' . $res->status() . ' ' . $res->body());
        }

        $access    = (string) $res->json('access_token');
        $expiresIn = (int) ($res->json('expires_in') ?? 3600);

        // Pinterest MAY rotate the refresh token; persist a new one if sent.
        $newRefresh = (string) ($res->json('refresh_token') ?? '');

        $row->update([
            'access_token'  => $access,
            'refresh_token' => $newRefresh !== '' ? $newRefresh : $row->refresh_token,
            'expires_at'    => now()->addSeconds($expiresIn),
            'scope'         => $res->json('scope') ?? $row->scope,
        ]);

        return $access;
    }
}
