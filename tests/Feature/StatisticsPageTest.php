<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CountryFlag;
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
    /** Every tab, and a marker that only that tab's partial can produce. */
    private const TAB_MARKERS = [
        'today'     => 'active in the last',
        'traffic'   => 'Views per visitor',
        'countries' => 'ranks countries by engagement',
        'map'       => 'Where visitors are',
        'referrers' => 'External sites sending visitors here',
        'devices'   => 'Automated traffic',
        'log'       => 'Visitor log',
        'members'   => 'Account health',
    ];

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

    public function test_every_tab_renders_its_own_content_and_is_marked_current(): void
    {
        $admin = $this->admin();

        foreach (self::TAB_MARKERS as $tab => $marker) {
            $response = $this->actingAs($admin)->get("/admin/statistics?tab={$tab}");

            $response->assertOk();
            // The fallback route also returns 200, so assert on page-unique markup.
            $response->assertSee('admin-shell', false);
            $response->assertSee($marker, false);

            // Exactly one tab link is the current one, and it is this tab.
            preg_match_all(
                '/href="[^"]*tab=([a-z]+)[^"]*"\s+aria-current="page"/',
                $response->getContent(),
                $m
            );
            $this->assertSame([$tab], $m[1], "Tab '{$tab}' should be the only one marked aria-current.");
        }
    }

    public function test_unknown_tab_falls_back_to_today(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/statistics?tab=bogus');

        $response->assertOk();
        $response->assertSee(self::TAB_MARKERS['today'], false);
        $this->assertStringContainsString('tab=today', $response->getContent());
    }

    public function test_default_tab_is_today_and_it_hides_the_window_selector(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/statistics')->getContent();

        $this->assertStringContainsString(self::TAB_MARKERS['today'], $html);
        // "Today" always means today, so there is no window to choose. Match the
        // rendered attribute, not the bare class name — that also appears in the
        // page's stylesheet as `.range__opt`.
        $this->assertStringNotContainsString('class="range__opt"', $html);

        // ...but a windowed tab does offer it.
        $traffic = $this->actingAs($this->admin())->get('/admin/statistics?tab=traffic')->getContent();
        $this->assertStringContainsString('class="range__opt"', $traffic);
    }

    public function test_range_selector_applies_to_windowed_tabs_and_rejects_unknown_windows(): void
    {
        $admin = $this->admin();

        foreach ([7, 30, 90] as $days) {
            $this->actingAs($admin)->get("/admin/statistics?tab=traffic&days={$days}")
                ->assertOk()
                ->assertSee("last {$days} days");
        }

        // An unsupported window silently falls back to 30 rather than erroring.
        $this->actingAs($admin)->get('/admin/statistics?tab=traffic&days=9999')
            ->assertOk()
            ->assertSee('last 30 days');
    }

    public function test_visitor_log_filters_keep_the_tab_and_do_not_error(): void
    {
        $admin = $this->admin();

        foreach (['all', 'human', 'bot'] as $traffic) {
            $response = $this->actingAs($admin)
                ->get("/admin/statistics?tab=log&traffic={$traffic}&sort=hits&q=1.1.1.1");

            $response->assertOk();
            // The filter form and the reset link must both come back to this tab,
            // or paging/filtering would bounce the reader to the default tab.
            $response->assertSee('name="tab" value="log"', false);
            $response->assertSee('tab=log', false);
        }
    }

    public function test_today_tab_wires_up_the_live_poller(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/statistics?tab=today')->getContent();

        $this->assertStringContainsString('admin/statistics/live', $html);
        // Each polled field needs a hook for the poller to patch in place.
        foreach (['views', 'uniques', 'new_visitors', 'returning', 'live'] as $key) {
            $this->assertStringContainsString("data-live-value=\"{$key}\"", $html);
        }
        foreach (['views_trend', 'uniques_trend', 'pages', 'visitors'] as $key) {
            $this->assertStringContainsString("data-live-html=\"{$key}\"", $html);
        }
    }

    public function test_only_the_today_tab_polls(): void
    {
        // The other tabs are multi-day aggregates, and two own Chart.js canvases
        // that an innerHTML swap would destroy.
        foreach (['traffic', 'countries', 'map', 'referrers', 'devices', 'log', 'members'] as $tab) {
            $html = $this->actingAs($this->admin())->get("/admin/statistics?tab={$tab}")->getContent();
            $this->assertStringNotContainsString('id="liveBar"', $html, "Tab '{$tab}' must not poll.");
        }
    }

    public function test_live_endpoint_returns_the_fields_the_poller_patches(): void
    {
        $response = $this->actingAs($this->admin())->getJson('/admin/statistics/live');

        $response->assertOk();
        $response->assertJsonStructure([
            'values' => ['views', 'uniques', 'new_visitors', 'returning', 'live'],
            'html'   => ['views_trend', 'uniques_trend', 'pages', 'visitors'],
            'server_time',
        ]);

        // A cached "live" number is worse than no live number at all.
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));

        // The trend chip is rendered by the same partial the full page uses.
        $chip = $response->json('html.views_trend');
        $this->assertStringContainsString('class="trend', $chip);

        // Today compares against yesterday, never against a 30-day window. With a
        // zero baseline the partial states the raw count ("today") instead of a
        // dishonest percentage, so accept either wording — but never "N days".
        $this->assertMatchesRegularExpression('/today|yesterday/', $chip);
        $this->assertStringNotContainsString('days', $chip);
    }

    public function test_live_endpoint_is_behind_the_admin_gate(): void
    {
        $this->get('/admin/statistics/live')->assertRedirect();

        $user = User::where('role', '!=', 'admin')->first();
        if ($user) {
            $status = $this->actingAs($user)->get('/admin/statistics/live')->status();
            $this->assertContains($status, [403, 302], 'Non-admins must not read the live feed.');
        }
    }

    /** Render the referrers partial directly: the live DB may hold no external referrer. */
    private function renderReferrers(?string $refHost, array $refLinks = []): string
    {
        return view('admin.statistics.tabs._referrers', [
            'referrers' => [
                ['host' => 'www.google.com',       'visitors' => 4, 'hits' => 9],
                ['host' => 'news.ycombinator.com', 'visitors' => 1, 'hits' => 2],
            ],
            'refHost'          => $refHost,
            'refLinks'         => $refLinks,
            'visitorTableDays' => 30,
            'days'             => 30,
        ])->render();
    }

    public function test_a_referrer_opens_the_exact_links_and_the_pages_they_landed_on(): void
    {
        // Closed: each host is a link that opens itself, and nothing is drilled into.
        $closed = $this->renderReferrers(null);
        $this->assertStringContainsString('ref=www.google.com', $closed);
        $this->assertStringContainsString('ref=news.ycombinator.com', $closed);
        $this->assertStringNotContainsString('class="drill"', $closed);

        $open = $this->renderReferrers('www.google.com', [[
            'url'       => 'https://www.google.com/search?q=tanbat',
            'page'      => '/books',
            'visitors'  => 3,
            'hits'      => 7,
            'last_seen' => now()->subHour(),
        ]]);

        // The whole point of the drill-down: the exact link clicked, and the exact
        // page it opened, both reachable.
        $this->assertStringContainsString('href="https://www.google.com/search?q=tanbat"', $open);
        $this->assertStringContainsString('href="' . url('/books') . '"', $open);
        $this->assertStringContainsString('aria-expanded="true"', $open);

        // Opening one host must not drag the other open, and the window survives.
        $this->assertSame(1, substr_count($open, 'aria-expanded="true"'));
        $this->assertStringContainsString('tab=referrers', $open);
        $this->assertStringContainsString('days=30', $open);
    }

    public function test_an_unknown_referrer_host_opens_nothing(): void
    {
        // `ref` is only honoured when it matches a host actually in the window, so
        // an invented one can neither drill nor be echoed back into the page.
        $html = $this->actingAs($this->admin())
            ->get('/admin/statistics?tab=referrers&ref=not-a-real-referrer.example')
            ->getContent();

        $this->assertStringNotContainsString('class="drill"', $html);
        $this->assertStringNotContainsString('not-a-real-referrer.example', $html);
    }

    /** Render the map partial directly: the live DB may hold no geolocated visitor. */
    private function renderMap(array $mapCountries): string
    {
        return view('admin.statistics.tabs._map', [
            'mapCountries'     => $mapCountries,
            'visitorTableDays' => 30,
            'days'             => 30,
        ])->render();
    }

    public function test_map_plots_countries_and_keeps_unplottable_ones_visible(): void
    {
        // The apostrophe is the point: it would close the single-quoted
        // data-countries attribute without JSON_HEX_APOS in the partial.
        $html = $this->renderMap([
            ['code' => 'US', 'name' => 'United States',  'visitors' => 25, 'hits' => 60],
            ['code' => 'GB', 'name' => 'United Kingdom', 'visitors' => 8,  'hits' => 10],
            ['code' => 'CI', 'name' => "Côte d'Ivoire",  'visitors' => 1,  'hits' => 1],
            ['code' => 'XX', 'name' => 'Unknown',        'visitors' => 6,  'hits' => 9],
        ]);

        // The map is served from our own origin, never a CDN.
        $this->assertFileExists(public_path('maps/world.svg'));
        $this->assertStringContainsString('/maps/world.svg', $html);

        // The choropleth scales against the largest plotted country.
        $this->assertStringContainsString('data-max="25"', $html);

        $this->assertMatchesRegularExpression("/data-countries='[^']*'/", $html);
        preg_match("/data-countries='([^']*)'/", $html, $m);
        $decoded = json_decode($m[1], true);

        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'data-countries must be valid JSON.');
        $this->assertSame(['us', 'gb', 'ci'], array_keys($decoded));
        $this->assertSame("Côte d'Ivoire", $decoded['ci']['n']);

        // 'XX' has no shape on the map, so it is reported beneath it, not dropped.
        $this->assertArrayNotHasKey('xx', $decoded);
        $this->assertStringContainsString('could not be resolved to a country', $html);
        $this->assertStringContainsString('6', $html);

        // Every code plotted must have a path in the SVG, or it silently vanishes.
        $svg = file_get_contents(public_path('maps/world.svg'));
        foreach (array_keys($decoded) as $code) {
            $this->assertStringContainsString("id=\"{$code}\"", $svg, "world.svg has no path for '{$code}'.");
        }
    }

    public function test_map_shows_an_empty_state_when_no_country_can_be_resolved(): void
    {
        // Every visitor on a local install is 127.0.0.1 / ::1, which geolocates
        // to nothing — the map must say so rather than fetch a 1.2MB SVG to
        // shade zero countries.
        $html = $this->renderMap([
            ['code' => 'XX', 'name' => 'Local network', 'visitors' => 6, 'hits' => 81],
        ]);

        $this->assertStringContainsString('No countries to plot', $html);
        $this->assertStringNotContainsString('/maps/world.svg', $html);
    }

    public function test_country_flag_resolves_real_codes_and_rejects_placeholders(): void
    {
        // Case-insensitive, and the SVG is actually on disk.
        foreach (['US', 'gb', 'LK', 'in'] as $code) {
            $this->assertTrue(CountryFlag::exists($code), "Expected a flag for {$code}.");
            $this->assertStringEndsWith(
                '/flags/' . strtolower($code) . '.svg',
                (string) CountryFlag::url($code)
            );
        }

        // 'XX' fills users.country for ~99% of rows and must never render a
        // broken image; the rest are the other ways a code can be absent.
        foreach (['XX', 'T1', 'ZZ', 'gb-eng', '', null] as $missing) {
            $this->assertFalse(CountryFlag::exists($missing), var_export($missing, true) . ' must have no flag.');
            $this->assertNull(CountryFlag::url($missing));
        }
    }

    public function test_every_flag_the_page_renders_points_at_a_real_file(): void
    {
        // The members tab shows member countries, which carry real ISO codes.
        $html = $this->actingAs($this->admin())->get('/admin/statistics?tab=members')->getContent();

        preg_match_all('/<img class="flag-img" src="([^"]+)"/', $html, $matches);

        foreach (array_unique($matches[1]) as $src) {
            $this->assertFileExists(
                public_path('flags/' . basename(parse_url($src, PHP_URL_PATH))),
                "Rendered flag {$src} has no backing SVG."
            );
        }

        // Unknown countries fall back to the placeholder, never to a broken <img>.
        $this->assertStringNotContainsString('src=""', $html);
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
