<?php

namespace App\Console\Commands;

use App\Models\BookDetail;
use App\Services\TelegramPoster;
use App\Support\TelegramSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Announce not-yet-posted books in the Telegram channel.
 *
 * The heartbeat (TaskRunnerController) posts one book per tick; this command
 * is the cron/manual equivalent and can drain a backlog with --limit.
 */
class PostBooksToTelegram extends Command
{
    protected $signature = 'books:post-telegram
                            {--limit=1 : How many pending books to post in this run}
                            {--book= : Post one specific book by id or slug, ignoring the queue order}
                            {--force : Run even when the integration toggle is off}';

    protected $description = 'Post pending books to the configured Telegram channel as photo messages.';

    public function handle(): int
    {
        $poster = new TelegramPoster();

        if (!$poster->isConfigured()) {
            $this->error('Telegram is not configured. Set the bot token + channel at /admin/telegram.');
            return self::FAILURE;
        }
        if (!TelegramSettings::enabled() && !$this->option('force')) {
            $this->warn('Telegram cross-posting is disabled. Enable it at /admin/telegram or pass --force.');
            return self::SUCCESS;
        }

        $limit       = max(1, (int) $this->option('limit'));
        $maxAttempts = (int) config('telegram.max_attempts', 5);

        if ($target = $this->option('book')) {
            // Explicit target: post exactly this book, even if it's already
            // been announced or has burned through its retry budget.
            $books = BookDetail::query()
                ->where('id', $target)
                ->orWhere('slug', $target)
                ->limit(1)
                ->get();

            if ($books->isEmpty()) {
                $this->error("No book matches \"{$target}\" (tried id and slug).");
                return self::FAILURE;
            }
        } else {
            $books = BookDetail::query()
                ->whereNull('telegram_message_id')
                ->where('telegram_attempts', '<', $maxAttempts)
                ->whereNotNull('cover_url')
                ->orderBy('id')
                ->limit($limit)
                ->get();
        }

        if ($books->isEmpty()) {
            $this->info('Nothing pending.');
            return self::SUCCESS;
        }

        $posted = 0;
        foreach ($books as $book) {
            try {
                $messageId = $poster->postBook($book);
                $book->update([
                    'telegram_message_id' => $messageId,
                    'telegram_posted_at'  => now(),
                    'telegram_last_error' => null,
                ]);
                $posted++;
                $this->line("  ✓ {$book->title} → message {$messageId}");
            } catch (\Throwable $e) {
                $book->increment('telegram_attempts');
                $book->update(['telegram_last_error' => mb_substr($e->getMessage(), 0, 500)]);
                Log::warning('Telegram cross-post failed', [
                    'book_id' => $book->id,
                    'error'   => $e->getMessage(),
                ]);
                $this->error("  ✗ {$book->title}: {$e->getMessage()}");
            }
        }

        $this->info("Posted {$posted} of {$books->count()}.");
        return self::SUCCESS;
    }
}
