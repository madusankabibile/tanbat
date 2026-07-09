<?php

namespace App\Http\Controllers;

use App\Models\GeneratedArticle;
use App\Services\ArticleGenerator;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Serves old WoWonder-style /read-blog/{id}_{slug}.html URLs. The original
 * content is gone, so on the FIRST hit we synthesise an article from the URL's
 * title via the Groq LLM, persist it, and render a blog page. Every later hit
 * (any URL with the same numeric id) serves the stored copy — never regenerated.
 */
class ReadBlogController extends Controller
{
    public function show(string $slug, ArticleGenerator $generator)
    {
        // /read-blog/2664_investment-...-market.html
        //            └id┘ └────────── slug ───────────┘ (".html" optional)
        if (!preg_match('/^(\d+)(?:_(.*?))?(?:\.html?)?$/i', $slug, $m)) {
            abort(404);
        }
        $oldId    = (int) $m[1];
        $urlSlug  = trim($m[2] ?? '');

        $article = GeneratedArticle::where('old_id', $oldId)->first();

        if (!$article) {
            $article = $this->generateAndStore($oldId, $urlSlug, $generator);
        }

        // Best-effort view counter — never fail the request over it.
        try {
            $article->increment('views');
        } catch (\Throwable $e) {
            // ignore
        }

        $related = GeneratedArticle::query()
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->limit(6)
            ->get(['id', 'old_id', 'slug', 'title', 'excerpt', 'published_at']);

        return view('read-blog-show', [
            'article' => $article,
            'related' => $related,
        ]);
    }

    /**
     * Generate the article once, under a per-id lock so two simultaneous first
     * hits don't both call the LLM. The DB unique key on old_id is the ultimate
     * guard: if a racing request beat us to the insert, we re-fetch its row.
     */
    private function generateAndStore(int $oldId, string $urlSlug, ArticleGenerator $generator): GeneratedArticle
    {
        // Without a slug we have no title to generate from → treat as gone.
        $title = GeneratedArticle::titleFromSlug($urlSlug);
        if ($title === '') {
            abort(404);
        }

        $lock = Cache::lock('readblog:gen:' . $oldId, 40);

        try {
            $lock->block(30);

            // Someone may have generated it while we waited for the lock.
            if ($existing = GeneratedArticle::where('old_id', $oldId)->first()) {
                return $existing;
            }

            $gen = $generator->generate($title);
            if (!$gen) {
                // Don't persist a failure — a later visit will retry generation.
                abort(503, 'Article is being prepared. Please try again in a moment.');
            }

            return GeneratedArticle::create([
                'old_id'       => $oldId,
                'slug'         => $urlSlug,
                // Prefer the faithful de-slugged title (correct stop-word casing,
                // matches the original URL) over the model's re-capitalised one.
                'title'        => $title,
                'excerpt'      => $gen['excerpt'],
                'body'         => $gen['body'],
                'category'     => $gen['category'],
                'tags'         => !empty($gen['tags']) ? implode(', ', $gen['tags']) : null,
                'model'        => $gen['model'],
                'published_at' => Carbon::now(),
            ]);
        } catch (LockTimeoutException $e) {
            // Couldn't get the lock in time — the other worker is likely still
            // generating. Serve its row if ready, else ask the visitor to retry.
            if ($existing = GeneratedArticle::where('old_id', $oldId)->first()) {
                return $existing;
            }
            abort(503, 'Article is being prepared. Please try again in a moment.');
        } catch (QueryException $e) {
            // Lost the unique-key race — the winner's row now exists.
            if ($existing = GeneratedArticle::where('old_id', $oldId)->first()) {
                return $existing;
            }
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }
}
