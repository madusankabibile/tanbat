<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostMemeFromBot extends Command
{
    protected $signature   = 'bot:post-meme {--username=james_caldwell : The bot user that owns the post}';
    protected $description = 'Fetch a random meme from meme-api.com and publish it as an image post on behalf of the bot user.';

    public function handle(): int
    {
        $bot = User::where('username', $this->option('username'))->first();
        if (!$bot) {
            $this->error("Bot user '{$this->option('username')}' not found. Run `php artisan db:seed --class=MemeBotSeeder` first.");
            return self::FAILURE;
        }

        // Pull a batch (not a single meme) so we can skip any we've already
        // posted and always publish a fresh one — meme-api hands back random
        // memes with no memory, so a single fetch can repeat earlier posts.
        $resp = Http::withOptions(['verify' => false])
            ->timeout(15)->retry(2, 500)
            ->get('https://meme-api.com/gimme/Funnymemes/30');
        if (!$resp->ok()) {
            $this->error('Meme API request failed: HTTP ' . $resp->status());
            return self::FAILURE;
        }

        $memes = $resp->json('memes') ?? [];
        if (empty($memes)) {
            $this->error('Meme API returned no memes.');
            return self::FAILURE;
        }
        shuffle($memes);

        $imageUrl = null;
        $title    = null;
        foreach ($memes as $meme) {
            if (!empty($meme['nsfw']) || !empty($meme['spoiler'])) {
                continue;
            }
            $url   = $meme['url']   ?? null;
            $name  = $meme['title'] ?? null;
            if (!$url || !$name) {
                continue;
            }
            // Dedup by title scoped to this bot so we never re-post the same meme.
            if (Post::where('user_id', $bot->id)->where('description', $name)->exists()) {
                continue;
            }
            $imageUrl = $url;
            $title    = $name;
            break;
        }

        if (!$imageUrl) {
            $this->warn('No fresh, unposted meme found in this batch.');
            return self::SUCCESS;
        }

        $binary = Http::withOptions(['verify' => false])->timeout(30)->get($imageUrl);
        if (!$binary->ok()) {
            $this->error('Failed to download meme image.');
            return self::FAILURE;
        }

        $ext = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }
        $path = 'posts/images/' . Str::random(40) . '.' . $ext;
        Storage::disk('public')->put($path, $binary->body());

        $category = Category::where('slug', 'other')->first()
            ?? Category::orderBy('id')->first();

        $post = Post::create([
            'user_id'     => $bot->id,
            'type'        => 'image',
            'category_id' => $category?->id,
            'description' => $title,
            'is_adult'    => false,
        ]);

        $post->media()->create([
            'kind'     => 'image',
            'path'     => $path,
            'position' => 0,
        ]);

        $this->info("Posted meme #{$post->id}: {$title}");
        return self::SUCCESS;
    }
}
