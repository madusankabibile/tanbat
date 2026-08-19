<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\TvChannel;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The TV stream proxy's whole reason to exist is that the origin .m3u8 never
 * reaches the browser. These tests hold that line, and cover the manifest
 * rewriting and token rules that make it work.
 *
 * Upstream HTTP is faked, so nothing here touches the network. The database is
 * real (phpunit.xml leaves DB_* unset), so each test creates and tears down its
 * own channel rather than depending on seeded rows.
 */
class TvStreamTest extends TestCase
{
    private ?TvChannel $channel = null;

    private const ORIGIN = 'https://cdn.example-origin.test/live/abc/index.m3u8';

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL has a subdirectory; without this, $this->get('/tv/...')
        // resolves under it, matches no route, and the fallback serves home.
        URL::forceRootUrl('http://localhost');
    }

    protected function tearDown(): void
    {
        // Deleting the post cascades to tv_channels.
        $this->channel?->post?->delete();
        $this->channel?->delete();

        parent::tearDown();
    }

    private function makeChannel(): TvChannel
    {
        $userId = User::firstOrCreate(
            ['username' => 'anonymous'],
            [
                'name' => 'Anonymous', 'age' => 18, 'gender' => 'other', 'country' => 'US',
                'email' => 'anonymous@tanbat.local', 'password' => bcrypt(Str::random(40)), 'role' => 'user',
            ]
        )->id;

        $post = Post::create(['user_id' => $userId, 'type' => 'tv', 'title' => 'Proxy Test Channel']);

        return $this->channel = TvChannel::create([
            'post_id'     => $post->id,
            'name'        => 'Proxy Test Channel',
            'slug'        => 'proxy-test-' . Str::lower(Str::random(6)),
            'description' => 'Fixture channel for the stream-proxy tests.',
            'stream_url'  => self::ORIGIN,
            'is_active'   => true,
        ]);
    }

    /**
     * Ask for a playback session the way the player does, returning its src.
     *
     * Session binding is switched off first: the test harness runs on the array
     * session driver, which mints a fresh session id on every request, so a
     * token would never survive the hop from POST /session to GET /manifest
     * here. Binding itself is covered by Tests\Unit\TvStreamTokenTest, where the
     * Request is ours to control.
     */
    private function playbackSrc(TvChannel $channel): string
    {
        config(['tv.bind_session' => false]);

        return $this->post("/tv/{$channel->slug}/session")->assertOk()->json('src');
    }

    public function test_player_page_never_contains_the_origin_stream_url(): void
    {
        $channel = $this->makeChannel();

        $html = $this->get("/tv/{$channel->slug}")->assertOk()->getContent();

        $this->assertStringNotContainsString(self::ORIGIN, $html, 'The origin manifest URL leaked into the page.');
        $this->assertStringNotContainsString('cdn.example-origin.test', $html, 'The origin host leaked into the page.');
        $this->assertDoesNotMatchRegularExpression('/\.m3u8/', $html, 'An .m3u8 URL leaked into the page.');
    }

    public function test_session_returns_a_proxy_url_on_our_own_host(): void
    {
        $channel = $this->makeChannel();

        $src = $this->playbackSrc($channel);

        $this->assertStringStartsWith('http://localhost/tv/s/', $src);
        $this->assertStringNotContainsString('example-origin', $src);
    }

    public function test_manifest_is_rewritten_so_no_upstream_url_survives(): void
    {
        $channel = $this->makeChannel();

        Http::fake([
            '*' => Http::response(implode("\n", [
                '#EXTM3U',
                '#EXT-X-VERSION:3',
                '#EXT-X-KEY:METHOD=AES-128,URI="https://cdn.example-origin.test/keys/k1.bin"',
                '#EXTINF:6.0,',
                'seg-001.ts',
                '#EXTINF:6.0,',
                '/absolute/seg-002.ts',
                '#EXTINF:6.0,',
                'https://cdn.example-origin.test/live/abc/seg-003.ts',
            ]), 200, ['Content-Type' => 'application/vnd.apple.mpegurl']),
        ]);

        $body = $this->get($this->playbackSrc($channel))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl')
            ->getContent();

        // Every URI now points back at us — relative, root-relative and
        // absolute alike, plus the AES key.
        $this->assertStringNotContainsString('example-origin.test', $body, 'An upstream URL survived the rewrite.');
        $this->assertStringNotContainsString('seg-001.ts', $body);
        // Three segments plus the AES key — every URI in the manifest.
        $this->assertSame(4, substr_count($body, '/tv/s/'), 'Expected all four URIs to be proxied.');
        $this->assertMatchesRegularExpression('#URI="http://localhost/tv/s/[^"]+"#', $body, 'The encryption key URI was not proxied.');

        // Tags we do not rewrite must come through untouched.
        $this->assertStringContainsString('#EXT-X-VERSION:3', $body);
        $this->assertStringContainsString('#EXTINF:6.0,', $body);
    }

    public function test_master_playlist_variants_come_back_as_manifests_not_segments(): void
    {
        $channel = $this->makeChannel();

        Http::fake([
            '*' => Http::response(implode("\n", [
                '#EXTM3U',
                '#EXT-X-STREAM-INF:BANDWIDTH=800000,RESOLUTION=640x360',
                '360p/index.m3u8',
                '#EXT-X-STREAM-INF:BANDWIDTH=2400000,RESOLUTION=1280x720',
                '720p/index.m3u8',
            ]), 200),
        ]);

        $body = $this->get($this->playbackSrc($channel))->assertOk()->getContent();

        // A variant must route to the playlist endpoint so it gets rewritten in
        // turn; sending it to the segment endpoint would stream a raw manifest
        // full of origin URLs straight to the player.
        $this->assertSame(2, substr_count($body, '/index.m3u8'), 'Variants were not routed to the manifest endpoint.');
        $this->assertStringNotContainsString('/seg', $body);
    }

    public function test_a_forged_or_garbage_token_is_rejected(): void
    {
        $this->get('/tv/s/not-a-real-token/index.m3u8')->assertForbidden();
        $this->get('/tv/s/not-a-real-token/seg')->assertForbidden();
    }

    public function test_an_offline_channel_is_not_playable(): void
    {
        $channel = $this->makeChannel();
        $channel->update(['is_active' => false]);

        $this->get("/tv/{$channel->slug}")->assertNotFound();
        $this->post("/tv/{$channel->slug}/session")->assertNotFound();
    }

    public function test_tv_grid_lists_live_channels(): void
    {
        $channel = $this->makeChannel();

        $this->get('/tv')
            ->assertOk()
            ->assertSee($channel->name, false)
            ->assertSee("/tv/{$channel->slug}", false);
    }

    public function test_a_mixed_case_url_redirects_to_the_canonical_slug(): void
    {
        $channel = $this->makeChannel();

        // A hand-typed or hand-written link like /tv/sampleTV must land on the
        // channel, not fall through to the catch-all as a 404 — and must do it
        // via one canonical URL rather than serving the page at two addresses.
        $this->get('/tv/' . strtoupper($channel->slug))
            ->assertRedirect("http://localhost/tv/{$channel->slug}")
            ->assertStatus(301);
    }

    public function test_an_unknown_channel_is_a_404(): void
    {
        $this->get('/tv/no-such-channel-here')->assertNotFound();
    }
}
