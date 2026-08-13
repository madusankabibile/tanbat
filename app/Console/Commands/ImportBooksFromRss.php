<?php

namespace App\Console\Commands;

use App\Services\BookRssImporter;
use App\Support\BookRssSettings;
use Illuminate\Console\Command;

/**
 * Poll the configured book RSS feed and publish any new items as anonymous
 * book posts. Safe to run repeatedly — items already imported are skipped on
 * their md5(guid) dedup key.
 */
class ImportBooksFromRss extends Command
{
    protected $signature = 'books:import-rss
                            {--feed= : Override the configured feed URL}
                            {--force : Run even when the importer is disabled}';

    protected $description = 'Import new books from the configured RSS feed and publish them as anonymous book posts.';

    public function handle(): int
    {
        if (!BookRssSettings::enabled() && !$this->option('force')) {
            $this->warn('Book RSS importer is disabled. Enable it at /admin/book-rss or pass --force.');
            return self::SUCCESS;
        }

        $feed = $this->option('feed') ?: BookRssSettings::feedUrl();
        $this->info("Polling {$feed} …");

        try {
            $result = (new BookRssImporter())->import($feed);
        } catch (\Throwable $e) {
            BookRssSettings::recordRun('Failed: ' . $e->getMessage());
            $this->error('Import failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $summary = "{$result['created']} created, {$result['skipped']} already known, {$result['failed']} failed";
        BookRssSettings::recordRun($summary);

        foreach ($result['titles'] as $title) {
            $this->line('  + ' . $title);
        }
        $this->info($summary);

        return self::SUCCESS;
    }
}
