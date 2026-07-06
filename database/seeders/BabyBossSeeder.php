<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BabyBossSeeder extends Seeder
{
    public function run(): void
    {
        $username = config('services.babyboss.username', 'babyboss');

        User::firstOrCreate(
            ['username' => $username],
            [
                'name'            => 'Baby Boss',
                'age'             => 27,
                'gender'          => 'prefer_not',
                'country'         => 'US',
                'email'           => $username . '@nexus.test',
                'password'        => bcrypt(Str::random(40)),
                'profile_picture' => null,
                'role'            => 'user',
            ]
        );
    }
}
