<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\VideoEmbed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostFunnyVideoFromBot extends Command
{
    protected $signature   = 'bot:post-video {--username=lucas_carlisle : The bot user that owns the post}';
    protected $description = 'Pull a recent video from a curated set of funny YouTube channels (via public channel RSS) and publish it as an embed post.';

    /**
     * Curated YouTube channels that consistently publish short, funny content.
     * The channel-RSS endpoint (https://www.youtube.com/feeds/videos.xml) is
     * public, requires no API key, and isn't rate-limited the way Reddit's
     * JSON endpoint is from datacenter IPs — so it works on the live host.
     *
     * Each entry returns the last 15 uploads as Atom XML.
     */
    private array $channels = [
        ['name' => 'Daily Dose Of Internet',  'id' => 'UCdC0An4ZPNr_YiFiYoVbwaw'],
        ['name' => 'FailArmy',                'id' => 'UCPDis9pjXuqyI7RYLJ-TTSA'],
        ['name' => 'Just for Laughs Gags',    'id' => 'UCl7w62FrLDIBLkSNagYXgZw'],
        ['name' => 'America\'s Funniest Home Videos', 'id' => 'UC2dnNqq9b9RZclFwxbm9wXg'],
        ['name' => 'Funhaus',                 'id' => 'UCboMX_UNgaPBsUOIgasn3-Q'],
        ['name' => 'Smosh',                   'id' => 'UCY30JRSgfhYXA6i6xX1erWg'],
    ];

    public function handle(): int
    {
        $bot = User::where('username', $this->option('username'))->first();
        if (!$bot) {
            $this->error("Bot user '{$this->option('username')}' not found. Run `php artisan db:seed --class=VideoBotSeeder` first.");
            return self::FAILURE;
        }

        $channels = $this->channels;
        shuffle($channels);

        foreach ($channels as $ch) {
            $candidates = $this->fetchYouTubeCandidates($ch['id']);
            if (empty($candidates)) continue;
            shuffle($candidates);

            foreach ($candidates as $c) {
                if (Post::where('embed_provider', 'youtube')->where('embed_id', $c['video_id'])->exists()) {
                    continue;
                }

                $category = Category::where('slug', 'other')->first()
                    ?? Category::orderBy('id')->first();

                $post = Post::create([
                    'user_id'        => $bot->id,
                    'type'           => 'video',
                    'category_id'    => $category?->id,
                    'description'    => $c['title'],
                    'is_adult'       => false,
                    'thumbnail'      => VideoEmbed::fetchPoster('youtube', $c['video_id']),
                    'embed_provider' => 'youtube',
                    'embed_id'       => $c['video_id'],
                ]);

                $this->info("Posted video #{$post->id}: {$c['title']} ({$ch['name']})");
                return self::SUCCESS;
            }
        }

        $this->warn('No fresh, unposted YouTube clips found in any source channel.');
        return self::SUCCESS;
    }

    /**
     * Pull a channel's last-15 uploads from YouTube's public RSS feed and return
     * [['video_id'=>..., 'title'=>...], ...]. Logs on failure so silent feed
     * outages on prod are visible.
     */
    private function fetchYouTubeCandidates(string $channelId): array
    {
        try {
            $url = "https://www.youtube.com/feeds/videos.xml?channel_id={$channelId}";
            $resp = Http::withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; tanbat-vidbot/1.0; +https://tanbat.example/bot)'])
                ->timeout(15)
                ->get($url);

            if (!$resp->ok()) {
                Log::warning('vidbot: channel feed fetch failed', ['channel' => $channelId, 'status' => $resp->status()]);
                return [];
            }

            $prev = libxml_use_internal_errors(true);
            $xml  = simplexml_load_string($resp->body());
            libxml_use_internal_errors($prev);
            if ($xml === false) {
                Log::warning('vidbot: channel feed parse failed', ['channel' => $channelId]);
                return [];
            }

            // YouTube channel feeds are Atom; entries live at root <entry>.
            $entries = $xml->entry ?? null;
            if (!$entries) return [];

            $out = [];
            foreach ($entries as $entry) {
                // The video ID is in <yt:videoId> (namespaced) — fall back to
                // parsing the entry's <link href> if the namespace strips out.
                $videoId = '';
                foreach ($entry->children('yt', true) as $node) {
                    if ($node->getName() === 'videoId') {
                        $videoId = (string) $node;
                        break;
                    }
                }
                if ($videoId === '') {
                    $href = (string) ($entry->link['href'] ?? '');
                    $parsed = VideoEmbed::parse($href);
                    if ($parsed && $parsed[0] === 'youtube') $videoId = $parsed[1];
                }
                if ($videoId === '') continue;

                $title = trim((string) ($entry->title ?? ''));
                if ($title === '') continue;

                $out[] = ['video_id' => $videoId, 'title' => $title];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('vidbot: channel feed exception', ['channel' => $channelId, 'error' => $e->getMessage()]);
            return [];
        }
    }
}
