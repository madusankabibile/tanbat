<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoPostSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'user')->get();
        if ($users->isEmpty()) {
            $this->command?->warn('No demo users found — run DemoUserSeeder first.');
            return;
        }

        $cat = fn(string $slug) => Category::where('slug', $slug)->value('id');

        // ---------- STATUS POSTS ----------
        $statuses = [
            ['Travel', 'Just landed in Kyoto. The cherry blossoms are unreal.', '#1e3a8a', '#ffffff', 'JP', 'en'],
            ['Food', 'Sunday brunch goals: pancakes, espresso, and zero responsibilities.', '#7c2d12', '#fef3c7', 'US', 'en'],
            ['Fitness', 'Day 30 of the 5am club. Energy levels: maximum.', '#064e3b', '#ecfdf5', 'GB', 'en'],
            ['Quotes', '"The journey of a thousand miles begins with a single step." — Lao Tzu', '#111827', '#fbbf24', 'CN', 'en'],
            ['Technology', 'Hot take: dark mode should be the OS default. Fight me.', '#0f172a', '#22d3ee', 'DE', 'en'],
            ['Music', 'New album dropped and I have not been productive since. Worth it.', '#581c87', '#f5d0fe', 'KR', 'en'],
            ['Lifestyle', 'Tiny apartment, big plants, bigger plans.', '#365314', '#d9f99d', 'IT', 'en'],
            ['Gaming', 'Finally beat the final boss after 47 attempts. No notes.', '#1e1b4b', '#a5b4fc', 'BR', 'pt'],
            ['Business', 'Reminder: your first idea is rarely your best idea. Iterate.', '#0c0a09', '#fbbf24', 'IN', 'en'],
            ['Quotes', 'Be the person your dog thinks you are.', '#7c2d12', '#fed7aa', 'CA', 'en'],
        ];

        foreach ($statuses as [$catName, $text, $bg, $fc, $country, $lang]) {
            $author = $users->where('country', $country)->first() ?? $users->random();
            Post::create([
                'user_id'     => $author->id,
                'type'        => 'status',
                'category_id' => $cat(Str::slug($catName)),
                'status_text' => $text,
                'bg_color'    => $bg,
                'font_color'  => $fc,
                'language'    => $lang,
            ]);
        }

        // ---------- ARTICLE POSTS ----------
        $articles = [
            [
                'Education',
                'Why Learning a Second Language Rewires Your Brain',
                'Bilingual brains show denser grey matter and better executive function. Here is what the research says.',
                "Recent neuroscience papers have shown that adults who learn a second language develop measurable changes in the anterior cingulate cortex. This region is associated with attention and conflict resolution.\n\nThe gains are not limited to childhood acquisition — adults beginning at 30 or 40 still see structural change after 6 months of consistent practice. The mechanism appears to be the constant inhibition required to suppress one language while speaking another.",
                'FR', 'en',
            ],
            [
                'Technology',
                '5 Tiny Habits That Make Senior Engineers Look Like Wizards',
                'It is rarely the framework. It is almost always the workflow. A short field guide.',
                "1. Reproduce the bug before reading the code. 2. Write the rollback before the deploy. 3. Read the failing test, not the passing ones. 4. Name the variable like the next person is tired. 5. Commit messages explain why, never what.\n\nNone of these are clever. All of them compound.",
                'DE', 'en',
            ],
            [
                'Science',
                'The Quiet Comeback of Mushroom Mycelium in Material Design',
                'Mycelium-based packaging is now shipping at scale. Here is how it actually works.',
                "Fungal mycelium can be grown around agricultural waste to form rigid foams that are home-compostable in 30 days. Production temperatures stay near room temperature — no kiln, no plastics, no oil.\n\nThe interesting part is anisotropy: by controlling fiber direction during growth, manufacturers can target stiffness on a per-axis basis. That is something polystyrene cannot do at any price.",
                'NL', 'en',
            ],
            [
                'Travel',
                'A Slow Week in Lisbon: Trams, Tarts, and Tile Walls',
                'Skip the rooftop bars. The best Lisbon hides in its laundry-strung side streets.',
                "Start in Alfama early — before the tour buses. The miradouro at Santa Luzia has the same view as the postcards, only at 7am you have it to yourself with a coffee.\n\nDo not eat pastéis de nata at the airport. Walk to Manteigaria in Chiado, watch them flame-finish the tops, eat one standing up. That is the rule.",
                'PT', 'en',
            ],
            [
                'Fitness',
                'Why Walking 8,000 Steps Beats 10,000 (For Most People)',
                'The famous 10k number was a 1960s marketing slogan. The real evidence points lower.',
                "Recent cohort studies of older adults show all-cause mortality benefits plateau around 7,500 to 8,000 daily steps. Past that, additional benefit is small and statistically noisy.\n\nIf walking 10k feels like a chore that you skip — walk 8k consistently instead. Adherence beats optimality every time.",
                'GB', 'en',
            ],
            [
                'Business',
                'The Pricing Mistake Every First-Time Founder Makes',
                'Spoiler: you are charging too little, and you are charging the wrong unit.',
                "First-time founders almost always price per seat when they should price per outcome. Seats grow linearly with headcount; outcomes grow with the customer's revenue. Tie your price to their wins and your TAM expands without selling harder.\n\nThe second mistake is anchoring on competitor pricing. Your competitors set prices three years ago at a different stage of the market. You are not them.",
                'US', 'en',
            ],
        ];

        foreach ($articles as [$catName, $title, $short, $body, $country, $lang]) {
            $author = $users->where('country', $country)->first() ?? $users->random();
            $slug = Str::slug($title) . '-' . Str::lower(Str::random(5));
            Post::create([
                'user_id'           => $author->id,
                'type'              => 'article',
                'category_id'       => $cat(Str::slug($catName)),
                'title'             => $title,
                'slug'              => $slug,
                'short_description' => $short,
                'body'              => $body,
                'language'          => $lang,
            ]);
        }

        // ---------- IMAGE POSTS ----------
        // No actual files in storage — featured_image stays null so the post
        // shows a placeholder in the UI. The post record itself is real.
        $images = [
            ['Photography', 'Golden hour from the rooftop. No filter, just patience.',                  'JP', 'en', false],
            ['Nature',      'Found this little mushroom kingdom on the morning hike.',                  'CA', 'en', false],
            ['Pets',        'He insists on watching me work. Strict supervisor.',                       'IE', 'en', false],
            ['Fashion',     'Thrifted the whole fit for under $40. Outfit details in comments.',       'IT', 'en', false],
            ['Food',        'Homemade ramen, take #6. Finally got the broth right.',                    'KR', 'en', false],
            ['Art & Design','Sketchbook spread from this week. Ink + watercolor + chaos.',              'FR', 'en', false],
            ['Travel',      'The blue hour over the souq.',                                             'AE', 'en', false],
            ['DIY & Crafts','Built this floating shelf from a single pine board. Tutorial soon.',      'SE', 'en', false],
        ];

        foreach ($images as [$catName, $desc, $country, $lang, $adult]) {
            $author = $users->where('country', $country)->first() ?? $users->random();
            Post::create([
                'user_id'     => $author->id,
                'type'        => 'image',
                'category_id' => $cat(Str::slug($catName)),
                'description' => $desc,
                'is_adult'    => $adult,
                'language'    => $lang,
            ]);
        }

        // ---------- VIDEO POSTS ----------
        $videos = [
            ['Music',    '30-second guitar riff I cannot get out of my head.',          'BR', 'pt'],
            ['Sports',   'Slow-mo of the game-winning shot. Still in disbelief.',       'US', 'en'],
            ['Gaming',   'Speedrun PB — shaved 12 seconds off the final split.',        'JP', 'en'],
            ['Pets',     'When the treat bag rustles. Every single time.',              'GB', 'en'],
            ['Fitness',  'Form check on my deadlift. Roast me kindly.',                 'DE', 'en'],
            ['Movies',   'My honest 60-second review of the new sci-fi release.',      'IN', 'en'],
        ];

        foreach ($videos as [$catName, $desc, $country, $lang]) {
            $author = $users->where('country', $country)->first() ?? $users->random();
            Post::create([
                'user_id'     => $author->id,
                'type'        => 'video',
                'category_id' => $cat(Str::slug($catName)),
                'description' => $desc,
                'language'    => $lang,
            ]);
        }

        // ---------- TAGS on a few posts ----------
        $tagsByCategory = [
            'travel'     => ['wanderlust', 'sunset', 'streetphotography'],
            'food'       => ['homemade', 'comfortfood', 'recipe'],
            'technology' => ['devlife', 'productivity', 'opensource'],
            'fitness'    => ['discipline', 'training', 'morningroutine'],
            'pets'       => ['catsofnexus', 'dogsofnexus', 'goodboy'],
        ];

        Post::whereHas('category', function ($q) use ($tagsByCategory) {
            $q->whereIn('slug', array_keys($tagsByCategory));
        })->with('category')->get()->each(function (Post $post) use ($tagsByCategory) {
            $names = $tagsByCategory[$post->category->slug] ?? [];
            $tagIds = collect($names)
                ->map(fn($n) => Tag::findOrCreateByName($n)->id)
                ->all();
            $post->tags()->syncWithoutDetaching($tagIds);
        });
    }
}
