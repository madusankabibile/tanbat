<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAgentParser;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Read-only coverage for the admin statistics page.
 *
 * phpunit.xml leaves DB_CONNECTION/DB_DATABASE unset, so these run against the
 * real database — nothing here writes, and there is no RefreshDatabase.
 */
class StatisticsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL carries a subdirectory (XAMPP install), so $this->get('/admin/...')
        // would otherwise resolve to /tanbat-xd/tanbat/admin/... , match no route,
        // and be served by Route::fallback() with a 200 and the public home feed.
        URL::forceRootUrl('http://localhost');
    }

    private function admin(): User
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $this->markTestSkipped('No admin user in the database to authenticate as.');
        }
        return $admin;
    }

    public function test_guests_are_redirected_away(): void
    {
        $this->get('/admin/statistics')->assertRedirect();
    }

    public function test_non_admins_cannot_reach_the_page(): void
    {
        $user = User::where('role', '!=', 'admin')->first();
        if (!$user) {
            $this->markTestSkipped('No non-admin user in the database.');
        }

        $response = $this->actingAs($user)->get('/admin/statistics');
        $this->assertContains($response->status(), [403, 302], 'Non-admins must not reach the statistics page.');
    }

    public function test_page_renders_both_halves_for_an_admin(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/statistics');

        $response->assertOk();
        // Assert on markers unique to this page: the fallback route also returns 200.
        $response->assertSee('admin-shell', false);
        $response->assertSee('id="visitor-log"', false);
        $response->assertSee('chartTraffic', false);
        $response->assertSee('chartSignups', false);
        $response->assertSee('Views per visitor');
        $response->assertSee('Account health');
    }

    public function test_range_selector_accepts_known_windows_and_falls_back_otherwise(): void
    {
        $admin = $this->admin();

        foreach ([7, 30, 90] as $days) {
            $this->actingAs($admin)->get("/admin/statistics?days={$days}")
                ->assertOk()
                ->assertSee("last {$days} days");
        }

        // An unsupported window silently falls back to 30 rather than erroring.
        $this->actingAs($admin)->get('/admin/statistics?days=9999')
            ->assertOk()
            ->assertSee('last 30 days');
    }

    public function test_visitor_log_filters_do_not_error(): void
    {
        $admin = $this->admin();

        foreach (['all', 'human', 'bot'] as $traffic) {
            $this->actingAs($admin)
                ->get("/admin/statistics?traffic={$traffic}&sort=hits&q=1.1.1.1")
                ->assertOk()
                ->assertSee('id="visitor-log"', false);
        }
    }

    public function test_user_agent_parser_classifies_the_cases_the_page_relies_on(): void
    {
        $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $ipad   = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/604.1';
        $edge   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0';
        $crawler = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

        $this->assertSame(['Chrome', 'Windows', 'Desktop', false], array_values(UserAgentParser::parse($chrome)));
        $this->assertSame(['Safari', 'iOS', 'Mobile', false], array_values(UserAgentParser::parse($iphone)));

        // Tablets must win over phones: an iPad UA carries neither "mobile" nor "iphone".
        $this->assertSame('Tablet', UserAgentParser::parse($ipad)['device']);

        // Edge announces itself as Chrome, so specificity order matters.
        $this->assertSame('Edge', UserAgentParser::parse($edge)['browser']);

        $this->assertTrue(UserAgentParser::parse($crawler)['is_bot']);
        $this->assertTrue(UserAgentParser::isBot('curl/8.4.0'));
        $this->assertFalse(UserAgentParser::isBot($chrome));

        // An absent user agent is unknown, not automated.
        $this->assertFalse(UserAgentParser::parse(null)['is_bot']);
        $this->assertSame('Unknown', UserAgentParser::parse('')['device']);
    }
}
