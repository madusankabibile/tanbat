<?php

namespace Tests\Feature;

use App\Models\BookDetail;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Verifies the SEO surface of the public pages: one canonical each, a
 * description, Open Graph, and valid JSON-LD where expected.
 *
 * Read-only against the real database (phpunit.xml leaves DB_* unset), so the
 * tests sample existing rows and skip when a content type is absent.
 */
class PublicSeoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL has a subdirectory; without this, $this->get('/...') resolves
        // under it, matches no route, and the fallback serves the home feed.
        URL::forceRootUrl('http://localhost');
    }

    /** Pull every JSON-LD block out of a document and assert each one parses. */
    private function jsonLdBlocks(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        $blocks = [];
        foreach ($m[1] as $raw) {
            $decoded = json_decode($raw, true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), "Invalid JSON-LD: {$raw}");
            $blocks[] = $decoded;
        }
        return $blocks;
    }

    private function assertHasExactlyOneCanonical(string $html): void
    {
        $this->assertSame(1, substr_count($html, 'rel="canonical"'), 'A page must have exactly one canonical link.');
    }

    private function assertBaselineMeta(string $html): void
    {
        $this->assertHasExactlyOneCanonical($html);
        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]+"/', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:url"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
    }

    public function test_home_has_website_and_organization_structured_data(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertBaselineMeta($html);

        $types = array_column($this->jsonLdBlocks($html), '@type');
        $this->assertContains('WebSite', $types);
        $this->assertContains('Organization', $types);

        // The sitelinks search box needs a SearchAction with a query template.
        $this->assertStringContainsString('SearchAction', $html);
        $this->assertStringContainsString('search?q={search_term_string}', $html);
    }

    public function test_books_listing_has_meta(): void
    {
        $html = $this->get('/books')->assertOk()->getContent();
        $this->assertBaselineMeta($html);
        $this->assertStringContainsString('og:url', $html);
    }

    public function test_book_page_has_book_structured_data(): void
    {
        $book = BookDetail::whereNotNull('slug')->where('slug', '!=', '')->whereHas('post')->first();
        if (!$book) {
            $this->markTestSkipped('No book with a slug to sample.');
        }

        $html = $this->get('/books/' . $book->slug)->assertOk()->getContent();

        $this->assertBaselineMeta($html);
        // Books get the OG "book" vertical, not the default "website".
        $this->assertStringContainsString('<meta property="og:type" content="book">', $html);

        $types = array_column($this->jsonLdBlocks($html), '@type');
        $this->assertContains('Book', $types);
    }

    public function test_profile_page_has_profilepage_structured_data(): void
    {
        // A username that collides with a fixed top-level route (e.g. "admin",
        // "books") resolves to that route, not a profile — those are registered
        // before /{username}. Sample a normal member whose username is clear of them.
        $reserved = ['admin', 'api', 'auth', 'books', 'blog', 'search', 'home',
            'people', 'discover', 'posts', 'articles', 'messages', 'assistant', 'u', 'tasks'];

        $user = User::where('role', '!=', 'admin')
            ->whereNotNull('username')->where('username', '!=', '')
            ->whereNotIn('username', $reserved)
            ->first();
        if (!$user) {
            $this->markTestSkipped('No sampleable member profile.');
        }

        $html = $this->get('/' . $user->username)->assertOk()->getContent();

        $this->assertBaselineMeta($html);
        $this->assertStringContainsString('<meta property="og:type" content="profile">', $html);

        $blocks = $this->jsonLdBlocks($html);
        $types = array_column($blocks, '@type');
        $this->assertContains('ProfilePage', $types);
    }

    public function test_post_page_has_socialmediaposting_structured_data(): void
    {
        $post = Post::whereIn('type', ['status', 'image', 'video'])->latest('id')->first();
        if (!$post) {
            $this->markTestSkipped('No post to sample.');
        }

        $html = $this->get("/posts/{$post->type}/{$post->id}")->assertOk()->getContent();

        $this->assertHasExactlyOneCanonical($html);
        $types = array_column($this->jsonLdBlocks($html), '@type');
        $this->assertContains('SocialMediaPosting', $types);
    }

    public function test_article_page_still_has_a_single_canonical_and_blogposting(): void
    {
        // Regression guard: the article views were already optimised and must
        // not have gained a duplicate canonical from any shared default.
        $article = Post::where('type', 'article')->where('is_legacy', false)
            ->whereNotNull('slug')->where('slug', '!=', '')->latest('id')->first();
        if (!$article) {
            $this->markTestSkipped('No native article to sample.');
        }

        $html = $this->get('/articles/' . $article->slug)->assertOk()->getContent();

        $this->assertHasExactlyOneCanonical($html);
        $types = array_column($this->jsonLdBlocks($html), '@type');
        $this->assertContains('BlogPosting', $types);
    }

    public function test_sitemap_lists_articles_and_books_and_is_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = $response->getContent();
        $this->assertNotFalse(simplexml_load_string($xml), 'Sitemap must be well-formed XML.');

        // Well beyond the old hard cap of 50.
        $this->assertGreaterThan(50, substr_count($xml, '<loc>'));

        // A known article permalink and a known book URL are both present.
        $article = Post::where('type', 'article')->whereNotNull('slug')->where('slug', '!=', '')->first();
        if ($article) {
            $this->assertStringContainsString($article->permalink(), $xml);
        }
        $book = BookDetail::whereNotNull('slug')->where('slug', '!=', '')->first();
        if ($book) {
            $this->assertStringContainsString('/books/' . $book->slug, $xml);
        }

        // The people page redirects guests, so it must not be advertised.
        $this->assertStringNotContainsString('/discover/people', $xml);
    }
}
