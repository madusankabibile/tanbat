<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoBotSeeder extends Seeder
{
    public function run(): void
    {
        $name = 'Lucas Carlisle';

        $bot = User::firstOrNew(['username' => 'lucas_carlisle']);

        $bot->name     = $name;
        $bot->age      = $bot->age      ?? 29;
        $bot->gender   = $bot->gender   ?? 'male';
        $bot->country  = $bot->country  ?? 'GB';
        $bot->email    = $bot->email    ?? 'vidbot@nexus.com';
        $bot->password = $bot->password ?? bcrypt(Str::random(40));
        $bot->bio      = 'Curating the funniest clips on the internet, one post at a time.';
        $bot->role     = 'user';

        if (!$bot->profile_picture) {
            $bot->profile_picture = $this->downloadRandomPortrait();
        }

        $bot->save();
    }

    private function downloadRandomPortrait(): ?string
    {
        try {
            $resp = Http::withOptions(['verify' => false])
                ->timeout(15)
                ->get('https://randomuser.me/api/', ['gender' => 'male', 'nat' => 'gb']);

            $url = $resp->json('results.0.picture.large');
            if (!$url) return null;

            $img = Http::withOptions(['verify' => false])->timeout(20)->get($url);
            if (!$img->ok()) return null;

            $path = 'avatars/' . Str::random(40) . '.jpg';
            Storage::disk('public')->put($path, $img->body());
            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
