<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            BabyBossSeeder::class,
            CategorySeeder::class,
            DemoUserSeeder::class,
            DemoPostSeeder::class,
            MemeBotSeeder::class,
            VideoBotSeeder::class,
            NewsBotSeeder::class,
            AdBotSeeder::class,
        ]);
    }
}
