<?php

namespace Tests\Feature;

use App\Support\TvStreamToken;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

/**
 * Token rules for the TV stream proxy (App\Support\TvStreamToken).
 *
 * Filed under Feature, not Unit, because these need the framework booted -
 * TvStreamToken reads config() and encrypts through Crypt with the app key.
 *
 * They build their own Request rather than going through the HTTP harness on
 * purpose: the binding they verify is per-request, and the test harness runs on
 * the array session driver, which mints a new session id on every request. A
 * request-level test therefore could not tell a working binding from a broken
 * one. Here the session id, IP and user-agent are ours to control.
 */
class TvTokenTest extends TestCase
{
    private const URL = 'https://cdn.example-origin.test/live/abc/index.m3u8';

    /** A request with a fixed session id, IP and user-agent. */
    private function request(string $sessionId = 'session-one', string $ip = '203.0.113.7'): Request
    {
        $request = Request::create('http://localhost/tv/s/x/index.m3u8', 'GET', [], [], [], [
            'REMOTE_ADDR'     => $ip,
            'HTTP_USER_AGENT' => 'TestBrowser/1.0',
        ]);

        $request->setLaravelSession(
            new Store('tv_test_session', new ArraySessionHandler(120), $sessionId)
        );

        return $request;
    }

    public function test_a_minted_token_round_trips_back_to_its_url(): void
    {
        config(['tv.bind_session' => true]);
        $request = $this->request();

        $payload = TvStreamToken::open($request, TvStreamToken::mint($request, self::URL, 42, 'manifest'));

        $this->assertNotNull($payload);
        $this->assertSame(self::URL, $payload['u']);
        $this->assertSame(42, $payload['c']);
        $this->assertSame('manifest', $payload['k']);
    }

    public function test_a_token_is_url_safe(): void
    {
        $token = TvStreamToken::mint($this->request(), self::URL, 1);

        // It has to survive as a single path segment untouched.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $token);
        $this->assertSame($token, rawurlencode($token));
    }

    public function test_a_token_minted_for_one_session_is_rejected_in_another(): void
    {
        config(['tv.bind_session' => true]);

        $token = TvStreamToken::mint($this->request('session-one'), self::URL, 1);

        // The URL copied out of devtools into a different browser.
        $this->assertNull(TvStreamToken::open($this->request('session-two'), $token));
    }

    public function test_a_token_is_rejected_from_a_different_ip(): void
    {
        config(['tv.bind_session' => true]);

        $token = TvStreamToken::mint($this->request('session-one', '203.0.113.7'), self::URL, 1);

        $this->assertNull(TvStreamToken::open($this->request('session-one', '198.51.100.4'), $token));
    }

    public function test_binding_can_be_switched_off(): void
    {
        config(['tv.bind_session' => false]);

        $token = TvStreamToken::mint($this->request('session-one'), self::URL, 1);

        $this->assertNotNull(TvStreamToken::open($this->request('session-two'), $token));
    }

    public function test_an_expired_token_is_rejected(): void
    {
        config(['tv.bind_session' => true, 'tv.token_ttl' => 10]);
        $request = $this->request();

        $token = TvStreamToken::mint($request, self::URL, 1, 'segment');
        $this->travel(30)->seconds();

        $this->assertNull(TvStreamToken::open($request, $token));
    }

    public function test_a_tampered_token_is_rejected(): void
    {
        $request = $this->request();
        $token   = TvStreamToken::mint($request, self::URL, 1);

        // Flip the tail of the ciphertext - Crypt's HMAC must catch it, so a
        // viewer can never swap in a URL of their own choosing (no SSRF).
        $tampered = substr($token, 0, -4) . (str_ends_with($token, 'AAAA') ? 'BBBB' : 'AAAA');

        $this->assertNull(TvStreamToken::open($request, $tampered));
    }

    public function test_segment_tokens_expire_sooner_than_manifest_tokens(): void
    {
        config(['tv.token_ttl' => 90, 'tv.manifest_ttl' => 14400]);
        $request = $this->request();

        $segment  = TvStreamToken::open($request, TvStreamToken::mint($request, self::URL, 1, 'segment'));
        $manifest = TvStreamToken::open($request, TvStreamToken::mint($request, self::URL, 1, 'manifest'));

        $this->assertLessThan($manifest['e'], $segment['e']);
    }
}
