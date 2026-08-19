<?php

namespace Tests\Feature;

use App\Models\TvChannel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The admin side of the TV post type: create, edit, toggle, delete, plus the
 * validation and access rules around them.
 *
 * Read/writes the real database (phpunit.xml leaves DB_* unset), so each test
 * cleans up after itself and skips when there is no admin account to act as.
 */
class AdminTvTest extends TestCase
{
    private ?User $admin = null;

    /** Slugs created during a test, torn down afterwards. */
    private array $made = [];

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL has a subdirectory; without this, requests resolve under it,
        // match no route, and the fallback serves the home feed instead.
        URL::forceRootUrl('http://localhost');

        $this->admin = User::where('role', 'admin')->first();
        if (!$this->admin) {
            $this->markTestSkipped('No admin user in the database.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->made as $slug) {
            $channel = TvChannel::where('slug', $slug)->first();
            $channel?->post?->delete();   // cascades to tv_channels
            $channel?->delete();
        }

        parent::tearDown();
    }

    public function test_the_index_and_create_screens_render(): void
    {
        $this->actingAs($this->admin)->get('/admin/tv')
            ->assertOk()
            ->assertSee('TV Channels', false);

        $this->actingAs($this->admin)->get('/admin/tv/create')
            ->assertOk()
            ->assertSee('m3u8 stream link', false);
    }

    public function test_an_admin_can_create_edit_toggle_and_delete_a_channel(): void
    {
        Storage::fake('public');

        // CREATE, with a logo upload.
        $this->actingAs($this->admin)->post('/admin/tv', [
            'name'        => 'Flow Test Channel',
            'description' => 'Created by the admin flow test.',
            'stream_url'  => 'https://cdn.flow.test/live/index.m3u8',
            'logo_file'   => UploadedFile::fake()->image('logo.png', 120, 120),
            'is_active'   => '1',
        ])->assertRedirect('/admin/tv');

        $channel = TvChannel::where('name', 'Flow Test Channel')->firstOrFail();
        $this->made[] = $channel->slug;

        $this->assertSame('flow-test-channel', $channel->slug);
        $this->assertSame('tv', $channel->post->type, 'The parent post should be of type tv.');
        $this->assertTrue($channel->is_active);
        Storage::disk('public')->assertExists($channel->logo);

        // EDIT — no new file, so the existing logo must survive.
        $this->actingAs($this->admin)->put("/admin/tv/{$channel->id}", [
            'name'        => 'Flow Test Renamed',
            'slug'        => $channel->slug,
            'description' => 'Edited.',
            'stream_url'  => 'https://cdn.flow.test/live/other.m3u8',
            'is_active'   => '1',
        ])->assertRedirect('/admin/tv');

        $channel->refresh();
        $this->assertSame('Flow Test Renamed', $channel->name);
        $this->assertSame('Flow Test Renamed', $channel->post->fresh()->title, 'The post title should follow the name.');
        $this->assertSame('https://cdn.flow.test/live/other.m3u8', $channel->stream_url);
        $this->assertNotNull($channel->logo, 'An edit without a new file must keep the existing logo.');

        // TOGGLE.
        $this->actingAs($this->admin)->post("/admin/tv/{$channel->id}/toggle")->assertRedirect();
        $this->assertFalse($channel->fresh()->is_active);

        // DELETE takes the parent post with it.
        $postId = $channel->post_id;
        $this->actingAs($this->admin)->delete("/admin/tv/{$channel->id}")->assertRedirect('/admin/tv');
        $this->assertDatabaseMissing('tv_channels', ['id' => $channel->id]);
        $this->assertDatabaseMissing('posts', ['id' => $postId]);
        array_pop($this->made);
    }

    public function test_validation_rejects_a_stream_link_that_is_not_http(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/tv', ['name' => 'Bad', 'stream_url' => 'rtmp://nope/stream'])
            ->assertSessionHasErrors('stream_url');
    }

    public function test_validation_requires_a_name_and_a_stream_link(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/tv', [])
            ->assertSessionHasErrors(['name', 'stream_url']);
    }

    public function test_a_non_admin_cannot_reach_the_tv_admin(): void
    {
        $user = User::where('role', '!=', 'admin')->first();
        if (!$user) {
            $this->markTestSkipped('No non-admin user in the database.');
        }

        $response = $this->actingAs($user)->get('/admin/tv');

        $this->assertNotSame(200, $response->getStatusCode(), 'A non-admin reached the TV admin.');
    }
}
