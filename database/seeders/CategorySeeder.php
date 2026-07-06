<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Art & Design', 'Photography', 'Travel', 'Food', 'Fashion',
            'Technology', 'Gaming', 'Music', 'Movies', 'Sports',
            'Fitness', 'Lifestyle', 'Business', 'Education', 'Science',
            'Nature', 'Pets', 'DIY & Crafts', 'Quotes', 'Other',
        ];
        foreach ($names as $name) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
