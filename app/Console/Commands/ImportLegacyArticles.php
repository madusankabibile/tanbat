<?php

namespace App\Console\Commands;

use App\Models\LegacyArticle;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports old Sngine blog articles from the `old` DB connection into the
 * self-contained `legacy_articles` table.
 *
 *   php artisan legacy:import-articles
 *
 * Idempotent: re-running updates existing rows (matched on old_post_id).
 * Reads posts_articles + posts (author/date) + blogs_categories (category)
 * + users (old author), and links each article to a migrated user account
 * by matching username, then email.
 */
class ImportLegacyArticles extends Command
{
    protected $signature = 'legacy:import-articles
                            {--chunk=200 : How many articles to process per batch}
                            {--fresh : Truncate legacy_articles before importing}';

    protected $description = 'Import old Sngine blog articles into the legacy_articles table';

    /** Lowercased new-user lookup: username => id, and email => id. */
    private array $usersByUsername = [];
    private array $usersByEmail = [];

    public function handle(): int
    {
        $old = DB::connection('old');

        // Sanity: make sure the source tables are reachable.
        try {
            $total = $old->table('posts_articles')->count();
        } catch (\Throwable $e) {
            $this->error('Cannot read the `old` connection ('.config('database.connections.old.database').'): '.$e->getMessage());
            $this->line('Import part2.sql into that database and check your LEGACY_DB_* env values.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if ($this->confirm('Truncate legacy_articles before importing?', false)) {
                DB::table('legacy_articles')->truncate();
                $this->warn('legacy_articles truncated.');
            }
        }

        $this->buildUserMaps();

        $defaultAuthorId = config('legacy.default_author_id');
        $uploadsBase = config('legacy.uploads_base');
        $this->info("Found {$total} article rows in the old database.");
        $this->line("Uploads base: {$uploadsBase}");
        $this->line('Default author id: '.($defaultAuthorId ?? '(none)'));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $imported = 0; $matched = 0; $skipped = 0;

        $old->table('posts_articles as pa')
            ->join('posts as p', function ($j) {
                $j->on('p.post_id', '=', 'pa.post_id')->where('p.post_type', '=', 'article');
            })
            ->leftJoin('blogs_categories as bc', 'bc.category_id', '=', 'pa.category_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'p.user_id')
            ->select(
                'pa.article_id', 'pa.post_id', 'pa.cover', 'pa.title', 'pa.text',
                'pa.category_id', 'pa.tags', 'pa.views',
                'p.time as published_at', 'p.user_id as old_user_id', 'p.user_type',
                'bc.category_name',
                'u.user_name', 'u.user_firstname', 'u.user_lastname', 'u.user_email'
            )
            ->orderBy('pa.article_id')
            ->chunk((int) $this->option('chunk'), function ($rows) use (&$imported, &$matched, &$skipped, $defaultAuthorId, $bar) {
                foreach ($rows as $r) {
                    $bar->advance();

                    // A page-authored (user_type='page') post won't match the
                    // users table; author fields simply stay null in that case.
                    $authorName = trim(($r->user_firstname ?? '').' '.($r->user_lastname ?? ''));
                    if ($authorName === '') {
                        $authorName = $r->user_name ?: null;
                    }

                    $userId = $this->resolveUserId($r->user_name, $r->user_email) ?? $defaultAuthorId;
                    if ($userId) {
                        $matched++;
                    }

                    $title = html_entity_decode((string) $r->title, ENT_QUOTES);
                    $categoryName = $r->category_name ?: null;

                    LegacyArticle::updateOrCreate(
                        ['old_post_id' => $r->post_id],
                        [
                            'old_article_id'  => $r->article_id,
                            'user_id'         => $userId,
                            'author_name'     => $authorName,
                            'author_username' => $r->user_name ?: null,
                            'title'           => mb_substr($title, 0, 500),
                            'slug'            => mb_substr(LegacyArticle::slugify($title), 0, 500),
                            'cover'           => $r->cover ?: null,
                            'body'            => $this->decodeBody($r->text),
                            'old_category_id' => $r->category_id ?: null,
                            'category_name'   => $categoryName,
                            'category_slug'   => $categoryName ? LegacyArticle::slugify($categoryName) : null,
                            'tags'            => $this->decodeTags($r->tags),
                            'views'           => (int) $r->views,
                            'published_at'    => $r->published_at,
                        ]
                    );
                    $imported++;
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Imported/updated: {$imported}. Authors linked to accounts: {$matched}.");

        return self::SUCCESS;
    }

    /** Old article bodies are HTML-entity-encoded; decode so they render as markup. */
    private function decodeBody(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
    }

    /** Old tags are a comma-separated (sometimes entity-encoded) string. */
    private function decodeTags(?string $tags): ?string
    {
        if ($tags === null || trim($tags) === '') {
            return null;
        }

        return html_entity_decode(trim($tags), ENT_QUOTES);
    }

    /** Build case-insensitive maps of migrated users for author matching. */
    private function buildUserMaps(): void
    {
        User::query()->select('id', 'username', 'email')->chunk(1000, function ($users) {
            foreach ($users as $u) {
                if ($u->username) {
                    $this->usersByUsername[mb_strtolower($u->username)] = $u->id;
                }
                if ($u->email) {
                    $this->usersByEmail[mb_strtolower($u->email)] = $u->id;
                }
            }
        });

        $this->line('Loaded '.count($this->usersByUsername).' migrated users for author matching.');
    }

    private function resolveUserId(?string $username, ?string $email): ?int
    {
        if ($username && isset($this->usersByUsername[mb_strtolower($username)])) {
            return $this->usersByUsername[mb_strtolower($username)];
        }
        if ($email && isset($this->usersByEmail[mb_strtolower($email)])) {
            return $this->usersByEmail[mb_strtolower($email)];
        }

        return null;
    }
}
