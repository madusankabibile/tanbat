<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Aiko Tanaka',      24, 'female', 'JP', 'aiko',     'aiko@nexus.test'],
            ['Liam O\'Connor',   29, 'male',   'IE', 'liam',     'liam@nexus.test'],
            ['Sofia Rossi',      31, 'female', 'IT', 'sofia',    'sofia@nexus.test'],
            ['Mateo Garcia',     22, 'male',   'ES', 'mateo',    'mateo@nexus.test'],
            ['Chloe Dubois',     27, 'female', 'FR', 'chloe',    'chloe@nexus.test'],
            ['Noah Schmidt',     34, 'male',   'DE', 'noah',     'noah@nexus.test'],
            ['Priya Sharma',     26, 'female', 'IN', 'priya',    'priya@nexus.test'],
            ['Carlos Mendes',    38, 'male',   'BR', 'carlos',   'carlos@nexus.test'],
            ['Emma Wilson',      25, 'female', 'US', 'emma',     'emma@nexus.test'],
            ['Oliver Brown',     30, 'male',   'GB', 'oliver',   'oliver@nexus.test'],
            ['Mei Lin',          23, 'female', 'CN', 'meilin',   'mei@nexus.test'],
            ['Ahmed Hassan',     33, 'male',   'EG', 'ahmed',    'ahmed@nexus.test'],
            ['Isabella Silva',   28, 'female', 'PT', 'bella',    'bella@nexus.test'],
            ['Jin-ho Park',      32, 'male',   'KR', 'jinho',    'jinho@nexus.test'],
            ['Layla Karimi',     21, 'female', 'IR', 'layla',    'layla@nexus.test'],
            ['Daniel Cohen',     36, 'male',   'IL', 'daniel',   'daniel@nexus.test'],
            ['Nadia Ahmed',      29, 'female', 'PK', 'nadia',    'nadia@nexus.test'],
            ['Lucas Andersson',  27, 'male',   'SE', 'lucas',    'lucas@nexus.test'],
            ['Zara Khan',        24, 'female', 'AE', 'zara',     'zara@nexus.test'],
            ['Tomas Novak',      35, 'male',   'CZ', 'tomas',    'tomas@nexus.test'],
            ['Ananya Perera',    26, 'female', 'LK', 'ananya',   'ananya@nexus.test'],
            ['Diego Hernandez',  31, 'male',   'MX', 'diego',    'diego@nexus.test'],
            ['Hana Yamamoto',    22, 'female', 'JP', 'hana',     'hana@nexus.test'],
            ['Ethan Smith',      40, 'male',   'CA', 'ethan',    'ethan@nexus.test'],
            ['Amara Okafor',     28, 'female', 'NG', 'amara',    'amara@nexus.test'],
        ];

        foreach ($users as [$name, $age, $gender, $country, $username, $email]) {
            User::firstOrCreate(
                ['username' => $username],
                [
                    'name'     => $name,
                    'age'      => $age,
                    'gender'   => $gender,
                    'country'  => $country,
                    'email'    => $email,
                    'password' => bcrypt('Password@123'),
                    'role'     => 'user',
                ]
            );
        }
    }
}
