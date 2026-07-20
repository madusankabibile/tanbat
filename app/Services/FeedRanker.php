<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Personalized feed scoring.
 *
 * Pipeline:
 *   1. candidates(): pull a candidate pool (recent + trending + follow-graph union)
 *      filtered by adult-opt-in and already-seen.
 *   2. score():       compute final score per candidate as a weighted sum of
 *                     content / affinity / demographic / freshness sub-scores,
 *                     then multiply by follow-bonus.
 *   3. rerank():      apply diversity penalties so the result doesn't all come
 *                     from one category/author, and inject exploration slots.
 *   4. paginate():    cursor over the ranked list returning page_size posts.
 *
 * For cold-start users (low interaction count) we lean on demographic+content
 * and crank up exploration so they don't get stuck in an empty bubble.
 */
class FeedRanker
{
    public function __construct(private InteractionRecorder $recorder) {}

    /**
     * Streaming feed: each call returns up to $limit posts (default 1), excluding
     * anything in $excludeIds (the client tracks every post it has already rendered
     * this session). Because affinity is updated synchronously by InteractionRecorder,
     * a like/comment/click on the previous post immediately influences which post
     * comes back next — the algorithm is re-run on every request.
     *
     * @param int[]       $excludeIds Post IDs already rendered in this session.
     * @param string|null $guestKey   For anonymous viewers: a hash of their IP,
     *                                used to persist "already seen" across page
     *                                refreshes so the guest feed rotates.
     * @return array{posts: Collection<int, Post>, cold_start: bool, recycled: bool, exhausted: bool}
     */
    public function feedFor(?User $user, array $excludeIds = [], int $limit = 1, ?string $viewerCountry = null, ?string $guestKey = null): array
    {
        $limit = max(1, min(20, $limit));

        if (!$user) {
            return $this->anonymousFeed($limit, $excludeIds, $viewerCountry, $guestKey);
        }

        // Rank by the viewer's live (IP-resolved) country when we have it,
        // falling back to their self-reported profile country.
        $viewerCountry = $viewerCountry ?: $user->country;

        $interactionCount = (int) DB::table('post_interactions')
            ->where('user_id', $user->id)
            ->count()
            + (int) DB::table('post_likes')->where('user_id', $user->id)->count()
            + (int) DB::table('comments')->where('user_id', $user->id)->count();

        $coldStart = $interactionCount < (int) config('feed.cold_start_threshold');

        // First pass: strict filters (no seen, no engaged, no session-loaded).
        $candidates = $this->candidates($user, $coldStart, relax: false, excludeIds: $excludeIds, viewerCountry: $viewerCountry);
        $recycled = false;

        // Fallback tier: when the strict pool is exhausted, drop the "already seen"
        // and "already engaged" filters — but ALWAYS keep $excludeIds (session-loaded)
        // so the same card never appears twice in one scroll session.
        if ($candidates->isEmpty()) {
            $candidates = $this->candidates($user, $coldStart, relax: true, excludeIds: $excludeIds, viewerCountry: $viewerCountry);
            $recycled = $candidates->isNotEmpty();
        }

        if ($candidates->isEmpty()) {
            // Truly exhausted — there is nothing left in the DB the user hasn't seen this session.
            return ['posts' => collect(), 'cold_start' => $coldStart, 'recycled' => false, 'exhausted' => true];
        }

        $affinityIndex = $this->loadAffinity($user->id);
        $followIndex   = $this->loadFollowGraph($user->id);
        // How much the viewer's country reads each candidate (per-country view
        // table). Drives the country_view_boost so posts people in the viewer's
        // country keep opening surface more, regardless of who authored them.
        $countryViews  = $this->loadCountryViews($viewerCountry, $candidates->pluck('id')->all());

        $botIds            = $this->botUserIds();
        $prioritizeMembers = (bool) config('feed.prioritize_members');
        $memberTierBonus   = (float) config('feed.member_tier_bonus');

        $scored = $candidates->map(function (Post $p) use ($user, $affinityIndex, $followIndex, $coldStart, $viewerCountry, $countryViews, $botIds, $prioritizeMembers, $memberTierBonus) {
            $p->_score = $this->scorePost($p, $user, $affinityIndex, $followIndex, $coldStart, $viewerCountry, $countryViews);
            // Member-first tier: lift every genuine member post above all bots,
            // preserving new/hot order within the member tier.
            if ($prioritizeMembers && !in_array($p->user_id, $botIds, true)) {
                $p->_score += $memberTierBonus;
            }
            return $p;
        })->sortByDesc('_score')->values();

        // Re-rank for diversity (penalties only matter when $limit > 1, but cheap to run).
        $ranked = $this->rerank($scored, $candidates);
        $page   = $ranked->take($limit)->values();

        return [
            'posts'      => $page,
            'cold_start' => $coldStart,
            'recycled'   => $recycled,
            'exhausted'  => false,
        ];
    }

    /**
     * Unauthenticated visitors: trending-newest mix, geo-blended, with
     * IP-keyed rotation. Posts already shown to this guest's IP (within the
     * guest TTL) are excluded so every refresh surfaces new content — the
     * anonymous equivalent of the member "already seen" filter. When the guest
     * has exhausted the fresh pool we recycle (drop the seen filter) so they
     * never hit a dead end.
     */
    private function anonymousFeed(int $limit, array $excludeIds = [], ?string $viewerCountry = null, ?string $guestKey = null): array
    {
        // Guest feed ordering (recency-led, article-first). Tiers, applied in
        // order so the feed opens with recent long-form content:
        //   1. Articles rank above every other post type — the guest feed is
        //      article-led, so status/image/video sink beneath the articles.
        //   2. Within a type, genuine members rank above the automated bot
        //      accounts, so the daily bot status/image/video posts don't lead
        //      the feed just for being the newest thing on the site.
        //   3. Newest first — so recent articles sit at the top and the old
        //      legacy (imported blog) articles fall below them.
        // A bounded random jitter on the recency key reshuffles items of a
        // similar age between requests (variety), while the IP-keyed rotation
        // below keeps surfacing new content across refreshes.
        $botIds = $this->botUserIds();

        // Posts this guest IP has already been shown recently — excluded so the
        // feed rotates across refreshes. Empty when there's no guest key.
        $guestSeenIds = [];
        if ($guestKey) {
            try {
                $ttlHours = (int) config('feed.guest_impression_ttl_hours', 12);
                $guestSeenIds = DB::table('guest_feed_impressions')
                    ->where('ip_hash', $guestKey)
                    ->where('seen_at', '>=', now()->subHours($ttlHours))
                    ->pluck('post_id')
                    ->all();
            } catch (\Throwable $e) {
                // Table not migrated yet (e.g. code deployed before `migrate`):
                // fall back to the non-rotating feed rather than erroring out.
                $guestSeenIds = [];
            }
        }

        // Recency shuffle window (seconds): two posts whose created_at is within
        // this window of each other may swap order for variety. Kept far smaller
        // than the gap between recent and legacy content, so a legacy article can
        // never jump ahead of a recent one — it only reshuffles same-era posts.
        $jitter = max(0, (int) config('feed.guest_recency_shuffle_hours', 48)) * 3600;

        // Query factory — lets us re-run without the seen filter to recycle.
        $build = function (array $skipIds) use ($botIds, $jitter, $limit) {
            // Book posts are members-only — never expose them to anonymous viewers.
            $q = Post::with(['user:id,name,username,profile_picture', 'category', 'media', 'tags'])
                ->select('posts.*')
                ->where('is_adult', false)
                ->where('type', '!=', 'book');
            if (!empty($skipIds)) $q->whereNotIn('id', $skipIds);

            // Tier 1: articles above everything else. Single-quoted SQL literal
            // so it's safe under MySQL's ANSI_QUOTES mode (some managed hosts).
            $q->orderByRaw("(posts.type = 'article') DESC");

            // Tier 2: genuine members above automated bot accounts.
            if (!empty($botIds)) {
                $ph = implode(',', array_fill(0, count($botIds), '?'));
                $q->orderByRaw("(posts.user_id NOT IN ($ph)) DESC", $botIds);
            }

            // Tier 3: newest first, with the bounded jitter for cross-request variety.
            $q->orderByRaw('(UNIX_TIMESTAMP(posts.created_at) + RAND() * ?) DESC', [$jitter]);

            return $q->take($limit)->get();
        };

        // Strict pass: skip both the session-loaded and already-seen-by-IP posts.
        $rows = $build(array_merge($excludeIds, $guestSeenIds));
        $recycled = false;

        // Recycle: this IP has seen everything recent. Drop the seen filter (but
        // keep session excludeIds so a card never repeats mid-scroll) so the
        // guest keeps getting a feed rather than an empty "nothing more" screen.
        if ($rows->isEmpty() && !empty($guestSeenIds)) {
            $rows = $build($excludeIds);
            $recycled = $rows->isNotEmpty();
        }

        return [
            'posts'      => $rows,
            'cold_start' => true,
            'recycled'   => $recycled,
            'exhausted'  => $rows->isEmpty(),
        ];
    }

    /**
     * Build the candidate pool. We pull from three streams and union them:
     *   - Recent (last 7 days) so the feed feels fresh
     *   - Highest-affinity authors / categories (the user's interest graph)
     *   - Follow-graph posts (people they've explicitly opted into)
     * Filtered against already-seen posts and adult-opt-in.
     *
     * When $relax is true (fallback tier), drop the "already seen" and
     * "already engaged" filters and widen the recency window — used when
     * the strict pool is empty so the user always gets *something* to scroll.
     */
    private function candidates(User $user, bool $coldStart, bool $relax = false, array $excludeIds = [], ?string $viewerCountry = null): Collection
    {
        $poolSize = (int) config('feed.candidate_pool_size');
        $ttlDays  = (int) config('feed.impression_ttl_days');

        $excludeOwn = $user->id;
        $query = Post::with(['user:id,name,username,profile_picture,country,age,gender', 'category:id,name,slug', 'media', 'tags:id,name', 'bookDetail'])
            ->where('user_id', '!=', $excludeOwn);

        // Session-loaded posts must NEVER repeat, regardless of relax mode.
        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        // Posts the user has explicitly hidden / marked "not interested" — always exclude.
        $hiddenIds = DB::table('post_hides')->where('user_id', $user->id)->pluck('post_id')->all();
        if (!empty($hiddenIds)) {
            $query->whereNotIn('id', $hiddenIds);
        }

        if (!$relax) {
            // Posts we've already shown — exclude them.
            $seenIds = DB::table('feed_impressions')
                ->where('user_id', $user->id)
                ->where('seen_at', '>=', now()->subDays($ttlDays))
                ->pluck('post_id')
                ->all();

            // Posts the user explicitly engaged with already — also exclude
            // (a like or comment means they've seen it and probably don't need it again).
            $engagedIds = DB::table('post_likes')->where('user_id', $user->id)->pluck('post_id')
                ->merge(DB::table('comments')->where('user_id', $user->id)->pluck('post_id'))
                ->unique()
                ->all();

            $extraExclude = array_unique(array_merge($seenIds, $engagedIds));
            if (!empty($extraExclude)) {
                $query->whereNotIn('id', $extraExclude);
            }
        }

        // Adult content filter: hide is_adult unless the user opted in.
        // (adult_opt_in column not yet on users — default behaviour is "hide".)
        $adultOptIn = (bool) ($user->adult_opt_in ?? false);
        if (!$adultOptIn) {
            $query->where('is_adult', false);
        }

        // Hide posts whose card would render as a broken stub:
        //   - image posts with no attached media row
        //   - video posts with no media AND no thumbnail
        // (article + status are always self-contained, so they pass through unchanged.)
        // Adbot posts intentionally carry no media row (the renderer swaps in
        // an ad-network slot), so they need a bypass on the media-required check.
        $adbotId = DB::table('users')->where('username', 'daniel_whitmore')->value('id');

        $query->where(function ($q) use ($adbotId) {
            $q->whereIn('type', ['status', 'article'])
              ->orWhere(function ($q2) use ($adbotId) {
                  $q2->where('type', 'image')
                     ->where(function ($q3) use ($adbotId) {
                         $q3->whereExists(function ($sub) {
                             $sub->select(DB::raw(1))
                                 ->from('post_media')
                                 ->whereColumn('post_media.post_id', 'posts.id');
                         });
                         if ($adbotId) {
                             $q3->orWhere('posts.user_id', $adbotId);
                         }
                     });
              })
              ->orWhere(function ($q2) {
                  $q2->where('type', 'video')
                     ->where(function ($q3) {
                         $q3->whereNotNull('thumbnail')
                            ->orWhereExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('post_media')
                                    ->whereColumn('post_media.post_id', 'posts.id');
                            });
                     });
              })
              ->orWhere(function ($q2) {
                  // Book posts: require a book_details row so the card has
                  // something to render. The visibility check (auth-only)
                  // happens at the controller layer, not here.
                  $q2->where('type', 'book')
                     ->whereExists(function ($sub) {
                         $sub->select(DB::raw(1))
                             ->from('book_details')
                             ->whereColumn('book_details.post_id', 'posts.id');
                     });
              });
        });

        // Recent + engagement-weighted candidate pool, biggest catchment for ranking.
        // In relaxed mode widen the lookback so older recycled content can surface.
        $lookbackDays = $relax ? 365 : 30;
        $candidates = (clone $query)
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->orderByDesc(DB::raw('(likes_count * 1.0 + comments_count * 1.5 + LOG(GREATEST(views_count, 1)) * 0.3 + UNIX_TIMESTAMP(created_at) / 86400)'))
            ->take($poolSize)
            ->get();

        // For cold-start, top up with global trending so the pool isn't tiny.
        if ($coldStart && $candidates->count() < 60) {
            $top = (clone $query)
                ->orderByDesc('likes_count')
                ->orderByDesc('comments_count')
                ->take(60 - $candidates->count())
                ->get();
            $candidates = $candidates->concat($top)->unique('id')->values();
        }

        // Always seed the pool with recent MEMBER posts, even if they fall
        // outside the recency window above. Bots post daily, so without this the
        // 30-day pool is all bots and genuine (older) member content never gets
        // a chance to be scored. This is the fix for a bot-dominated feed.
        $botIds = $this->botUserIds();
        $topUp  = (int) config('feed.member_topup');
        if (!empty($botIds) && $topUp > 0) {
            $memberPosts = (clone $query)
                ->whereNotIn('user_id', $botIds)
                ->latest()
                ->take($topUp)
                ->get();
            $candidates = $candidates->concat($memberPosts)->unique('id')->values();
        }

        // Seed with the articles the viewer's country reads most, even if they're
        // older than the recency window — otherwise the country_view_boost in
        // scorePost could never surface an evergreen locally-popular article
        // because it would never make it into the candidate pool. This stream is
        // a clone of $query, so it inherits every exclusion above (hidden, seen,
        // engaged, session-loaded, own posts, adult, broken-card filters).
        $localTopUp = (int) config('feed.country_favorites_topup', 25);
        if ($viewerCountry && $localTopUp > 0) {
            // Resolve the most-read-in-country article IDs first, then pull them
            // through a clone of $query (whereIn, no join) so we inherit every
            // exclusion above without making the unqualified `id`/`user_id`
            // filters ambiguous. Over-fetch a little because some IDs will be
            // filtered out by the seen/engaged/render exclusions. Final ranking
            // is handled by the country_view_boost in scorePost, so pool order
            // here doesn't matter.
            $faveIds = DB::table('article_country_views')
                ->where('country_code', $viewerCountry)
                ->where('views', '>', 0)
                ->orderByDesc('views')
                ->limit($localTopUp * 2)
                ->pluck('post_id')
                ->all();

            if (!empty($faveIds)) {
                $localFaves = (clone $query)
                    ->whereIn('id', $faveIds)
                    ->take($localTopUp)
                    ->get();
                $candidates = $candidates->concat($localFaves)->unique('id')->values();
            }
        }

        return $candidates;
    }

    /** O(1) affinity lookups keyed as "dimension:value". */
    private function loadAffinity(int $userId): array
    {
        $rows = DB::table('user_affinity')->where('user_id', $userId)->get();
        // Normalize each dimension to a 0-1 scale using the dimension's own max.
        $byDim = [];
        foreach ($rows as $r) {
            $byDim[$r->dimension][] = $r;
        }
        $index = [];
        foreach ($byDim as $dim => $list) {
            $max = max(0.0001, max(array_map(fn ($r) => max(0, $r->score), $list)));
            foreach ($list as $r) {
                $index[$dim . ':' . $r->dimension_value] = max(0, $r->score) / $max;
            }
        }
        return $index;
    }

    private function loadFollowGraph(int $userId): array
    {
        return array_flip(
            DB::table('follows')->where('follower_id', $userId)->pluck('following_id')->all()
        );
    }

    /**
     * Per-post view counts from the viewer's country (article_country_views),
     * as [post_id => views]. Empty when the viewer's country is unknown or the
     * candidate pool is empty. Only article posts have rows here today.
     *
     * @param int[] $postIds
     * @return array<int,int>
     */
    private function loadCountryViews(?string $viewerCountry, array $postIds): array
    {
        if (!$viewerCountry || empty($postIds)) {
            return [];
        }
        return DB::table('article_country_views')
            ->where('country_code', $viewerCountry)
            ->whereIn('post_id', $postIds)
            ->pluck('views', 'post_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** User ids of the automated/system accounts (config/bots.php), resolved once. */
    private ?array $botIds = null;
    private function botUserIds(): array
    {
        if ($this->botIds !== null) {
            return $this->botIds;
        }
        $names = (array) config('bots.usernames', []);
        return $this->botIds = empty($names)
            ? []
            : DB::table('users')->whereIn('username', $names)->pluck('id')->map('intval')->all();
    }

    private function scorePost(Post $post, User $user, array $affinity, array $follows, bool $coldStart, ?string $viewerCountry = null, array $countryViews = []): float
    {
        $w = config('feed.weights');

        $content     = $this->contentScore($post);
        $affinityS   = $this->affinityScore($post, $affinity);
        $demographic = $this->demographicScore($post, $user, $viewerCountry);
        $freshness   = $this->freshnessScore($post);

        // Cold-start users have weak affinity signals — lean on globally-good
        // engagement and demographic match so the first impression of the feed
        // is "wow, look how alive this community is and how relatable the
        // people I see are" instead of "here are 3 random posts I don't care
        // about". As soon as the user starts interacting we phase affinity
        // back in (interactionCount > threshold flips coldStart off).
        if ($coldStart) {
            $score = ($w['content']     * 1.5  * $content)
                   + ($w['affinity']    * 0.4  * $affinityS)
                   + ($w['demographic'] * 1.6  * $demographic)
                   + ($w['freshness']   * 1.25 * $freshness);
        } else {
            $score = ($w['content']     * $content)
                   + ($w['affinity']    * $affinityS)
                   + ($w['demographic'] * $demographic)
                   + ($w['freshness']   * $freshness);
        }

        // Strong, multiplicative boost for followed authors — they've explicitly
        // opted in, so we want to honour the signal.
        if (isset($follows[$post->user_id])) {
            $score *= (float) config('feed.follow_multiplier');
        }

        // Feature long-form content: boost articles so they surface more often
        // and sit near the top of the feed.
        if ($post->type === 'article') {
            $score *= (float) config('feed.article_multiplier');
        }

        // Give local content more visibility: boost posts whose author is in the
        // viewer's country (viewer country resolved from IP, see GeoLocator).
        if ($viewerCountry && $post->user?->country
            && strcasecmp($viewerCountry, $post->user->country) === 0) {
            $score *= (float) config('feed.same_country_multiplier');
        }

        // "Popular in your country" boost: lift posts that visitors from the
        // viewer's own country keep opening (per-country view table). Scales with
        // how much the country reads this post, saturating at a configurable cap
        // so one runaway article can't dominate. Only articles currently carry
        // this signal (views are recorded on article page loads).
        $cv = (int) ($countryViews[$post->id] ?? 0);
        if ($viewerCountry && $cv > 0) {
            $b = config('feed.country_view_boost');
            $saturation = max(1, (int) ($b['saturation'] ?? 40));
            $ratio = min(1.0, log(1 + $cv) / log(1 + $saturation));
            $score *= 1 + ((float) ($b['multiplier'] ?? 1.0) - 1) * $ratio;
        }

        $isBot = in_array($post->user_id, $this->botUserIds(), true);

        // New + recommended synergy. A post that is BOTH fresh and a good
        // affinity match for this user gets a multiplicative boost so it
        // climbs to the top of the feed. For cold-start users we can't lean on
        // affinity yet, so we accept demographic match as the "recommended"
        // signal — this keeps new users from getting a flat "trending" wall
        // and instead surfaces today's content from people like them.
        //
        // Bots are excluded: they post constantly, so they'd ALWAYS clear the
        // freshness floor and hijack this boost — the very thing that was
        // flooding the feed with bot content.
        $nr = config('feed.new_recommended_boost');
        if (!$isBot && $freshness >= ($nr['freshness_floor'] ?? 0.25)) {
            $recommended = $coldStart
                ? ($demographic >= ($nr['demographic_floor'] ?? 0.30))
                : ($affinityS  >= ($nr['affinity_floor']    ?? 0.30));
            if ($recommended) {
                $score *= (float) ($nr['multiplier'] ?? 1.5);
            }
        }

        // Down-rank automated/system accounts (news/meme/ad/video bots + the
        // shared anonymous account) so genuine members' posts take precedence.
        // Applied LAST so no later boost can undo it — a bot post always ends up
        // below a comparable member post regardless of freshness/engagement.
        if ($isBot) {
            $score *= (float) config('feed.bot_multiplier');
        }

        // Small noise so two posts with the same score don't tie-break
        // deterministically every time (gives feed a little movement).
        $score += mt_rand(0, 1000) / 100000;

        return $score;
    }

    private function contentScore(Post $post): float
    {
        $engagement = ($post->likes_count * 1.0)
                    + ($post->comments_count * 1.5)
                    + (log(max(1, $post->views_count) + 1) * 0.3);

        $hours = max(0, now()->diffInMinutes($post->created_at, false) / -60); // negative→positive
        $hours = abs($hours);
        $halfLife = (float) config('feed.content_half_life_hours');
        $decay = pow(0.5, $hours / $halfLife);

        // Normalize roughly to 0-1 — squash with a soft cap so a runaway viral
        // post doesn't dominate every ranking.
        return min(1.0, ($engagement * $decay) / 80);
    }

    private function affinityScore(Post $post, array $affinity): float
    {
        $mix = config('feed.affinity_mix');
        $score = 0.0;

        if ($post->category_id) {
            $score += $mix['category'] * ($affinity['category:' . $post->category_id] ?? 0);
        }
        if ($post->type) {
            $score += $mix['type'] * ($affinity['type:' . $post->type] ?? 0);
        }
        if ($post->language) {
            $score += $mix['language'] * ($affinity['language:' . $post->language] ?? 0);
        }
        $score += $mix['author'] * ($affinity['author:' . $post->user_id] ?? 0);

        // Tag affinity = average of the tags' individual scores (so a post tagged
        // with one beloved topic ranks similarly to one with several mild ones).
        if ($post->relationLoaded('tags') && $post->tags->count()) {
            $tagSum = 0;
            foreach ($post->tags as $tag) {
                $tagSum += ($affinity['tag:' . $tag->id] ?? 0);
            }
            $score += $mix['tag'] * ($tagSum / $post->tags->count());
        }

        // The post's author country also nudges country affinity (we don't have
        // post.country directly, but the author's country is the best proxy).
        if ($post->user?->country) {
            $score += $mix['country'] * ($affinity['country:' . $post->user->country] ?? 0);
        }

        return $score;
    }

    private function demographicScore(Post $post, User $user, ?string $viewerCountry = null): float
    {
        $d = config('feed.demographic');
        $score = 0.0;
        $author = $post->user;
        if (!$author) return 0.0;

        // Prefer the viewer's live (IP) country; fall back to their profile.
        $viewerCountry = $viewerCountry ?: $user->country;
        if ($viewerCountry && $author->country && strcasecmp($viewerCountry, $author->country) === 0) {
            $score += $d['same_country_weight'];
        }
        if ($user->age && $author->age && abs((int) $user->age - (int) $author->age) <= $d['age_band_years']) {
            $score += $d['age_band_weight'];
        }
        if ($user->gender && $author->gender && strcasecmp($user->gender, $author->gender) === 0) {
            $score += $d['same_gender_weight'];
        }

        return $score;
    }

    private function freshnessScore(Post $post): float
    {
        $hours = abs(now()->diffInMinutes($post->created_at, false) / 60);
        foreach (config('feed.freshness_tiers') as $tier) {
            if ($hours <= $tier['max_hours']) {
                return $tier['bonus'];
            }
        }
        return 0.0;
    }

    /**
     * Diversity-aware re-ranking + exploration injection.
     *
     * Walks the sorted list and demotes posts that share category/author/type
     * with recent picks; the first N slots additionally enforce *hard* author
     * and type uniqueness so the user's first impression of the feed never
     * stacks three posts from the same person or all of one kind. Every Mth
     * slot is filled with a random exploration candidate from outside the
     * user's current affinity bubble.
     */
    private function rerank(Collection $scored, Collection $candidatePool): Collection
    {
        $div = config('feed.diversity_penalty');
        $exp = config('feed.exploration');
        $window = (int) $div['window'];
        $hardUniqueFirstN = (int) ($div['hard_unique_first_n'] ?? 3);

        $result = collect();
        $recentCats = [];
        $recentAuthors = [];
        $recentTypes = [];
        $pool = $scored->all();
        $explorationCounter = 0;

        while (!empty($pool)) {
            $position = $result->count();
            $isImpressionWindow = $position < $hardUniqueFirstN;

            // Score every eligible candidate under the diversity penalty.
            $eligible = []; // [ [pool_index, adjusted_score], ... ]
            foreach ($pool as $i => $p) {
                // Hard exclusion for the first N: skip any post whose author OR
                // type already appears in the result so far. This guarantees
                // the opening fold of the feed feels varied.
                if ($isImpressionWindow) {
                    if (in_array($p->user_id, $recentAuthors, true)) continue;
                    if (in_array($p->type, $recentTypes, true))     continue;
                }
                $penalty = 0.0;
                if (in_array($p->category_id, array_slice($recentCats, -$window), true)) {
                    $penalty += $div['same_category_recent'];
                }
                if (in_array($p->user_id, array_slice($recentAuthors, -$window), true)) {
                    $penalty += $div['same_author_recent'];
                }
                if (in_array($p->type, array_slice($recentTypes, -$window), true)) {
                    $penalty += (float) ($div['same_type_recent'] ?? 0.10);
                }
                $eligible[] = [$i, $p->_score - $penalty];
            }

            // If the hard-unique filter excluded everything (tiny pool, all same
            // author/type), fall back to the full pool with no penalty.
            if (empty($eligible)) {
                foreach ($pool as $i => $p) {
                    $eligible[] = [$i, $p->_score];
                }
                if (empty($eligible)) break;
            }

            // First slot: weighted-random pick from the top-K eligible candidates.
            // This is what stops "the same #1 post" appearing on every refresh —
            // the algorithm still favours the best-scoring posts (they get the
            // bulk of the probability mass) but the opener rotates between
            // logins. Subsequent slots stay deterministic so the rest of the
            // ranked list is faithful to the score.
            if ($position === 0 && count($eligible) > 1) {
                $bestIdx = $this->stochasticTopPick($eligible);
            } else {
                $bestIdx = -1; $bestAdj = -INF;
                foreach ($eligible as [$i, $adj]) {
                    if ($adj > $bestAdj) { $bestAdj = $adj; $bestIdx = $i; }
                }
                if ($bestIdx === -1) break;
            }

            $pick = $pool[$bestIdx];
            array_splice($pool, $bestIdx, 1);

            $result->push($pick);
            $recentCats[]    = $pick->category_id;
            $recentAuthors[] = $pick->user_id;
            $recentTypes[]   = $pick->type;
            $explorationCounter++;

            // Inject an exploration slot every N picks (after the impression window).
            if ($position >= $hardUniqueFirstN
                && $explorationCounter % $exp['slot_every'] === 0
                && !empty($pool)) {
                $explore = $this->pickExploration($pool, $exp['min_engagement']);
                if ($explore) {
                    $idx = array_search($explore, $pool, true);
                    if ($idx !== false) array_splice($pool, $idx, 1);
                    $result->push($explore);
                    $recentCats[]    = $explore->category_id;
                    $recentAuthors[] = $explore->user_id;
                    $recentTypes[]   = $explore->type;
                }
            }
        }

        return $result;
    }

    /**
     * Weighted-random pick from the top-K of an [pool_index, adjusted_score]
     * list — used for the feed's opening slot so the same post doesn't lead
     * every refresh. Weights are shifted to be non-negative and exponentiated
     * with a temperature so the best-scoring posts still get most of the
     * probability mass, but anything in the top K can win.
     */
    private function stochasticTopPick(array $eligible): int
    {
        $topK = (int) (config('feed.first_pick_top_k') ?? 5);
        $temperature = (float) (config('feed.first_pick_temperature') ?? 1.5);

        usort($eligible, fn ($a, $b) => $b[1] <=> $a[1]);
        $top = array_slice($eligible, 0, max(1, $topK));

        if (count($top) === 1) return $top[0][0];

        $minScore = $top[count($top) - 1][1];
        $weights = [];
        $total = 0.0;
        foreach ($top as [$idx, $score]) {
            $w = pow(max(0.001, $score - $minScore + 0.01), $temperature);
            $weights[] = [$idx, $w];
            $total += $w;
        }

        if ($total <= 0) return $top[0][0];

        $r = (mt_rand() / mt_getrandmax()) * $total;
        foreach ($weights as [$idx, $w]) {
            $r -= $w;
            if ($r <= 0) return $idx;
        }
        return $top[0][0];
    }

    /**
     * Random pick from the tail of the ranked pool — gives the user content
     * outside their current bubble. Quality-gated so we don't surface garbage.
     */
    private function pickExploration(array $pool, int $minEngagement): ?Post
    {
        $tail = array_slice($pool, (int) (count($pool) * 0.4));
        $eligible = array_values(array_filter($tail, function (Post $p) use ($minEngagement) {
            return ($p->likes_count + $p->comments_count) >= $minEngagement;
        }));
        if (empty($eligible)) return null;
        return $eligible[array_rand($eligible)];
    }
}
