<?php

/*
|--------------------------------------------------------------------------
| Feed personalization tuning
|--------------------------------------------------------------------------
|
| All weights and constants for the recommendation algorithm live here so
| product can tune ranking without touching code. The FeedRanker composes
| a per-post score as a weighted sum of normalized sub-scores, then applies
| a follow multiplier and a hard adult filter.
|
*/

return [

    // Weighted sum components (relative magnitudes — they don't have to add to 1).
    'weights' => [
        'content'     => 1.00,  // engagement velocity, age-decayed
        'affinity'    => 1.40,  // category/tag/author/type/language match
        'demographic' => 0.60,  // country / age-band / gender alignment
        'freshness'   => 0.45,  // small bump for very new posts
        'diversity'   => 0.35,  // penalty for repeated category/author in the result list
    ],

    // Strong multiplicative boost when the viewer follows the author.
    'follow_multiplier' => 1.6,

    // Sub-component tuning for the affinity score.
    'affinity_mix' => [
        'category' => 0.35,
        'tag'      => 0.30,
        'author'   => 0.20,
        'type'     => 0.07,
        'language' => 0.05,
        'country'  => 0.03,
    ],

    // Per-event raw signal strengths fed into AffinityCalculator.
    // Higher = stronger evidence of user interest.
    'event_weights' => [
        'impression' => 0.05,   // weak — just seeing it
        'click'      => 0.50,   // chose to open
        'dwell'      => 1.20,   // scaled by dwell_ms in calculator
        'like'       => 1.50,
        'unlike'     => -1.20,
        'comment'    => 2.00,
        'share'      => 2.50,
        'hide'       => -3.00,  // strong negative signal — user opted out
    ],

    // Half-life for affinity decay (days). Older events exponentially fade.
    'affinity_half_life_days' => 21,

    // Content score half-life — how fast a post's engagement loses heat.
    'content_half_life_hours' => 30,

    // Freshness windows (hours → bonus).
    'freshness_tiers' => [
        ['max_hours' => 3,  'bonus' => 1.00],
        ['max_hours' => 12, 'bonus' => 0.55],
        ['max_hours' => 36, 'bonus' => 0.25],
    ],

    // Diversity: penalty applied during re-ranking when consecutive posts share a dimension.
    'diversity_penalty' => [
        'same_category_recent' => 0.18,  // soft penalty if same category appears in last N
        'same_author_recent'   => 0.30,
        'same_type_recent'     => 0.10,  // discourages "all images" or "all videos" runs
        'window'               => 4,     // look back this many positions
        'hard_unique_first_n'  => 3,     // first 3 picks must have distinct author AND distinct type
    ],

    // Cold-start: until a user has this many recorded interactions, lean harder on
    // demographic + global engagement, and inject more exploration.
    'cold_start_threshold' => 5,

    // Exploration: every Nth slot is a random (but quality-gated) post the user
    // would not otherwise see. Discovery loop for both new and active users.
    'exploration' => [
        'slot_every'     => 7,    // 1-in-7 results
        'min_engagement' => 3,    // candidate must have at least this many like+comment+share
    ],

    // How long an impression keeps a post out of the user's feed (days).
    'impression_ttl_days' => 7,

    // Guest (IP-keyed) rotation: how long a post stays out of an anonymous
    // visitor's feed after it's been served to their IP. Shorter than the
    // member window so guests still see popular content again fairly soon, but
    // long enough that refreshing repeatedly keeps surfacing new posts.
    'guest_impression_ttl_hours' => 12,

    // Demographic scoring (small contributions — never the dominant signal).
    'demographic' => [
        'same_country_weight'   => 0.45,
        'age_band_years'        => 6,      // ±6 years considered "same band"
        'age_band_weight'       => 0.30,
        'same_gender_weight'    => 0.25,
    ],

    // Multiplicative score factor for posts authored in the viewer's own
    // country (country resolved from the viewer's IP, see App\Services\GeoLocator).
    // >1 gives local content more visibility. Stacks on the additive
    // demographic.same_country_weight above.
    'same_country_multiplier' => 1.35,

    // Multiplicative boost for content that readers from the VIEWER'S country
    // actually open the most — sourced from the per-country view table
    // (article_country_views, populated on article page views; see
    // App\Services\ArticleGeoViews and the /blog geo ranking). This is distinct
    // from same_country_multiplier above: that rewards the author's country,
    // this rewards "people in your country keep reading this", regardless of who
    // wrote it. The boost scales with how much the viewer's country reads a
    // post: multiplier ~1 at zero local views, approaching `multiplier` once
    // local views reach `saturation`. Only article posts currently carry this
    // signal, so in practice it lifts locally-popular articles in the feed.
    'country_view_boost' => [
        'multiplier' => 1.8,   // max multiplicative factor at/above saturation
        'saturation' => 40,    // local views where the boost is ~fully applied
    ],

    // Seed the candidate pool with up to this many articles that the viewer's
    // country reads the most (top by article_country_views for their country),
    // on top of the normal recency/engagement streams. Without this, an article
    // that's popular in the viewer's country but too old for the recency window
    // would never enter the pool, so the country_view_boost above could never
    // surface it. 0 disables the top-up (boost still applies to in-pool posts).
    'country_favorites_topup' => 25,

    // Multiplicative score factor for posts by automated/system accounts
    // (config/bots.php). <1 pushes bot content below genuine members' posts.
    // Applied last in scorePost (after every boost) so it can't be undone.
    'bot_multiplier' => 0.25,

    // Hard member-first tiering. When true, every genuine member post ranks
    // above every bot post in the feed (bots only appear once member content is
    // exhausted), regardless of age/engagement. The bonus is a large additive
    // constant that dwarfs the normal 0-6 score range so the two tiers never
    // interleave; within each tier the usual new/hot ordering still applies.
    'prioritize_members' => true,
    'member_tier_bonus'  => 1000.0,

    // Always seed the candidate pool with this many of the most-recent member
    // posts, even when they're older than the recency window below — otherwise
    // daily-posting bots crowd every genuine (but older) post out of the pool.
    'member_topup' => 150,

    // Multiplicative score boost for article-type posts, so long-form member
    // content is featured near the top of the feed. >1 surfaces more articles.
    'article_multiplier' => 3.0,

    // Candidate pool size — pull this many posts before scoring to keep ranking cheap.
    'candidate_pool_size' => 400,

    // Stochastic first pick: the opening slot of the feed is sampled from the
    // top-K candidates (weighted by score, smoothed by temperature) so the
    // user doesn't see the same #1 post on every refresh/login. Higher
    // temperature → tighter concentration on the best-scoring post; lower →
    // more uniform across the top-K.
    'first_pick_top_k'        => 5,
    'first_pick_temperature'  => 1.5,

    // "New + recommended" synergy. A post that crosses BOTH the freshness
    // floor and the affinity floor (or demographic floor for cold-start users)
    // gets this multiplicative score boost. Tuned to roughly +50% — strong
    // enough that today's-content-for-you dominates the opening of the feed,
    // not so strong that it crowds out evergreen high-engagement classics.
    'new_recommended_boost' => [
        'multiplier'         => 1.5,
        'freshness_floor'    => 0.25,  // ≥ the 36-hour freshness tier
        'affinity_floor'     => 0.30,  // normalized affinityScore output
        'demographic_floor'  => 0.30,  // used for cold-start users only
    ],

    // Final page size returned by GET /api/feed.
    'page_size' => 12,
];
