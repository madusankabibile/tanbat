<?php

namespace App\Console\Commands;

use App\Models\LegacyArticle;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Materialises migrated Sngine blog articles (legacy_articles) as native
 * `posts` rows (type=article, is_legacy=1) so they appear on their author's
 * profile and in the feed, and gain the full like/comment/share stack. Only
 * articles that matched a real user account are synced; the ~187 with no
 * mapped author keep serving from the read-only legacy view at /blogs.
 *
 *   php artisan legacy:sync-posts
 *
 * Idempotent: re-running updates existing rows (matched on legacy_post_id).
 */
class SyncLegacyPosts extends Command
{
    protected $signature = 'legacy:sync-posts {--chunk=200 : Rows processed per batch}';

    protected $description = 'Create/refresh native posts for migrated legacy blog articles';

    public function handle(): int
    {
        $total = LegacyArticle::whereNotNull('user_id')->count();
        $this->info("Syncing {$total} authored legacy articles into posts…");

        // posts.slug is globally UNIQUE, but legacy titles/slugs are not (the
        // spam-heavy dataset repeats slugs). Preload every slug in use so we can
        // keep the clean slug when it's free and only suffix "-{id}" on a real
        // collision — preserving the original /blogs URLs for unique articles.
        // Map lower(slug) => owning legacy_post_id (0 for native posts) so a
        // re-run doesn't treat an article's OWN slug as a collision (idempotent).
        $usedSlugs = Post::query()->select('slug', 'legacy_post_id')->get()
            ->filter(fn ($p) => filled($p->slug))
            ->mapWithKeys(fn ($p) => [mb_strtolower($p->slug) => (int) $p->legacy_post_id])
            ->all();

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $created = 0; $updated = 0;

        LegacyArticle::query()
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById((int) $this->option('chunk'), function ($articles) use (&$created, &$updated, &$usedSlugs, $bar) {
                foreach ($articles as $a) {
                    $bar->advance();

                    $body    = (string) $a->body;
                    $cover   = $a->coverUrl(); // absolute URL on the old uploads host, or null
                    $excerpt = Str::of(strip_tags($body))->squish()->limit(300);
                    $when    = $a->published_at ?: $a->created_at;
                    $slug    = $this->uniqueSlug((string) $a->slug, (int) $a->old_post_id, $usedSlugs);

                    $post = Post::updateOrCreate(
                        ['legacy_post_id' => $a->old_post_id],
                        [
                            'user_id'           => $a->user_id,
                            'type'              => 'article',
                            'is_legacy'         => true,
                            'title'             => Str::limit((string) $a->title, 250, ''),
                            'slug'              => $slug,
                            'featured_image'    => $cover,
                            'short_description' => (string) $excerpt !== '' ? (string) $excerpt : null,
                            'body'              => $body,
                        ]
                    );

                    $post->wasRecentlyCreated ? $created++ : $updated++;

                    // views_count is not mass-assignable (counts are managed by
                    // the interaction stack), and created_at must reflect the
                    // original publish date for correct chronological ordering.
                    $post->views_count = (int) $a->views;
                    if ($when) {
                        $post->created_at = $when;
                    }
                    $post->save();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Created: {$created}, updated: {$updated}.");

        return self::SUCCESS;
    }

    /**
     * Keep the original clean slug when it's free (preserving the old /blogs
     * URL); otherwise append "-{old_post_id}" for global uniqueness. Treats a
     * slug already owned by this same article as free, so re-runs are stable.
     *
     * @param  array<string,int>  $used  lower(slug) => owning legacy_post_id
     */
    private function uniqueSlug(string $base, int $id, array &$used): string
    {
        $base = Str::limit(trim($base), 240, '') ?: 'article';
        $key  = mb_strtolower($base);

        if (!isset($used[$key]) || $used[$key] === $id) {
            $used[$key] = $id;
            return $base;
        }

        $suffixed = $base . '-' . $id;
        $used[mb_strtolower($suffixed)] = $id;
        return $suffixed;
    }
}
