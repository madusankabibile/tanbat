<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('bot:post-meme')
            ->cron('*/50 * * * *')
            ->withoutOverlapping()
            ->runInBackground();

        // Every 90 minutes. Cron has no native "every 90m" expression, so we register
        // two events whose firings interleave: 00:00, 01:30, 03:00, 04:30, …
        $schedule->command('bot:post-video')
            ->cron('0 0,3,6,9,12,15,18,21 * * *')
            ->withoutOverlapping()
            ->runInBackground();
        $schedule->command('bot:post-video')
            ->cron('30 1,4,7,10,13,16,19,22 * * *')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('bot:post-news')
            ->cron('15 */2 * * *')
            ->withoutOverlapping()
            ->runInBackground();

        // Offset 45 past the hour so it doesn't pile up with the news bot's :15 slot.
        $schedule->command('bot:post-ad')
            ->cron('45 */2 * * *')
            ->withoutOverlapping()
            ->runInBackground();

        // Book RSS feed → anonymous book posts. Hourly at :05; the command
        // no-ops when the importer is disabled at /admin/book-rss.
        $schedule->command('books:import-rss')
            ->hourlyAt(5)
            ->withoutOverlapping()
            ->runInBackground();

        // Announce imported books in the Telegram channel. Runs every 10
        // minutes but posts a single book per run, so a batch drains steadily
        // instead of flooding the channel.
        $schedule->command('books:post-telegram --limit=1')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
