<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name'            => 'Administrator',
                'age'             => 30,
                'gender'          => 'prefer_not',
                'country'         => 'US',
                'email'           => 'admin@nexus.com',
                'password'        => bcrypt('Nexus@Admin2025'),
                'profile_picture' => null,
                'role'            => 'admin',
            ]
        );
    }
}
