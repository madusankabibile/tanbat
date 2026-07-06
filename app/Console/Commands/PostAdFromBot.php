<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;

class PostAdFromBot extends Command
{
    protected $signature   = 'bot:post-ad {--username=daniel_whitmore : The bot user that owns the post}';
    protected $description = 'Publish a sponsored image-style post on behalf of the ad bot. The post carries no media; the feed renderer swaps in the configured ad slot for this bot.';

    public function handle(): int
    {
        $bot = User::where('username', $this->option('username'))->first();
        if (!$bot) {
            $this->error("Bot user '{$this->option('username')}' not found. Run `php artisan db:seed --class=AdBotSeeder` first.");
            return self::FAILURE;
        }

        $category = Category::where('slug', 'other')->first()
            ?? Category::orderBy('id')->first();

        $post = Post::create([
            'user_id'     => $bot->id,
            'type'        => 'image',
            'category_id' => $category?->id,
            'is_adult'    => false,
        ]);

        $this->info("Posted ad #{$post->id}");
        return self::SUCCESS;
    }
}
