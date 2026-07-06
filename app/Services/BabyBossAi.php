<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * BabyBoss — a human-passing book-finder persona that lives behind the regular
 * messaging UI. The bot NEVER greets first; it only reacts when the user sends
 * a message. Conversation state is held in cache (keyed per conversation),
 * delivery is driven by the user-facing /poll endpoint. All replies are picked
 * randomly from predefined sets — no external AI calls.
 *
 * Flow:
 *   new            — user just messaged. Buffer their text(s), at +20s decide
 *                    via keyword regex whether it's a book request.
 *                       yes → greeting + "book title?"  → awaiting_title
 *                       no  → small-talk reply          → chitchat
 *   awaiting_title — capture next user message verbatim as the book title,
 *                    send a "give me some time" line, then ~4 min later drop
 *                    link-A + "try this." All user messages during the wait
 *                    are silently swallowed.            → searching_a
 *   searching_a/b/c — wait for user verdict on the link. Acks ("ok", "will try")
 *                    are silently ignored. Real replies are regex-classified:
 *                       not_working → next category (2 min wait), or give up
 *                                     after C → offline cooldown
 *                       found       → humble shoutout request → offline cooldown
 *                       other       → silently wait for a clearer signal
 *   chitchat       — non-book small talk; up to 3 short replies, then offline.
 *   offline        — 5-minute cooldown. Any incoming user message is silently
 *                    swallowed AND never marked read (no double-tick) so the
 *                    user can tell the bot is "away". Once the cooldown elapses,
 *                    the next user message starts a brand-new session from `new`.
 */
class BabyBossAi
{
    private const STATE_TTL_SECONDS         = 60 * 60 * 36;     // 36h
    private const TYPING_CACHE_PREFIX       = 'msg.typing.';    // matches MessageController
    private const TYPING_HOLD_SECONDS       = 4;
    private const IDLE_RESET_SECONDS        = 60 * 60;          // 1h of silence → brand-new session
    private const OFFLINE_COOLDOWN_MINUTES  = 5;
    private const MAX_CHITCHAT_REPLIES      = 3;

    private const INITIAL_WAIT_SECONDS      = 20;               // pre-greeting / classification delay
    private const GREETING_TO_TITLE_SECONDS = 4;
    private const TITLE_TO_ACK_SECONDS      = 5;                // 5s after user types the title → ack line
    private const ACK_TO_WAIT_SECONDS       = 2;                // small natural beat before "give me a min"
    private const WAIT_TO_LINK_SECONDS      = 4 * 60;           // 4 min between "give me a min" + link
    private const LINK_TO_TRY_THIS_SECONDS  = 3;                // link & "found this." land a few sec apart
    private const LINK_RETRY_DELAY_SECONDS  = 2 * 60;           // 2 min between failed link attempts
    private const FOUND_TO_GOODBYE_SECONDS  = 5;                // 5s before farewell when user reports success
    private const BODY_BUFFER_MAX_CHARS     = 2000;

    /* ──────────────────────────────────────────────────────────────────────
     | Canned reply pools — picked at random for a human feel.
     ─────────────────────────────────────────────────────────────────────── */

    private const GREETINGS = [
        'hi', 'hey', 'hi bro', 'yo', 'hey there', 'hii', 'hello', 'hey hey',
        'yo yo', 'hi there', 'sup', 'whatsapp?', 'wassup', "what's up",
    ];

    private const TITLE_ASKS = [
        'title of the book?',
        'book title?',
        "what's the book title?",
        "what's the title?",
        'which book?',
        'whats the name of the book?',
        'drop the title',
        'name of the book?',
        'what is the book you looking?',
        'what is the book title you looking for?',
        'what is the title?',
    ];

    // Ack the user's title — short acknowledgement before the wait-line.
    private const TITLE_ACKS = [
        'ok',
        'got it',
        'ahh ok',
        "let's see",
        'alright',
        'okay',
        'noted',
        'cool, got it',
        'ah right',
        'k',
    ];

    private const WAIT_LINES = [
        'give me a min',
        'please wait',
        'wait few min',
        'give me some time',
        'give me a minute to check',
        'lemme check',
        'gimme a sec',
        'one sec, checking',
        'hold on, looking',
        'wait a bit, on it',
        'let me dig around',
    ];

    private const NO_LUCK_FALLBACK = [
        "no luck this time. want to try a different title?",
        "couldn't find it. different title?",
        "nothing came up. try another title?",
        "didn't find it. got another title?",
    ];

    // Sent right after the link to nudge the user to click.
    private const LINK_INTRO_LINES = [
        'found this.',
        'try this.',
        'see this.',
        'check this.',
        'try this one.',
        'see if this works.',
        'this should work.',
    ];

    private const TRY_THIS_NEXT_LINES = [
        'then try this.',
        'try this instead.',
        'how about this one.',
        'this one then.',
        'see if this works.',
    ];

    private const CHITCHAT_REPLIES = [
        'yeah', 'huh', 'oh ok', 'cool', 'gotcha', 'hmm yeah', 'alright',
        'haha ok', 'right', 'fair', 'mhm', 'nice', 'true', 'lol',
        'oh nice', 'i see', 'yeah for sure', 'mhm true', 'yeah man',
    ];

    private const CHITCHAT_OFFTOPIC_REPLIES = [
        'yeah', 'huh', 'oh ok', 'hmm', 'gotcha', 'right',
        'haha', 'fair enough', 'mhm', 'i see', 'oh',
    ];

    private const APOLOGY_AFTER_C = [
        "damn, that's all i got today. hit me up tomorrow, i'll dig again",
        "sorry, out of sources for now. ping me tomorrow?",
        "ran out of ideas for today. try me again tomorrow",
        "thats all i can find rn. catch me tomorrow and i'll try again",
        "no luck on my end today. hit me up later, i'll keep looking",
    ];

    /* ──────────────────────────────────────────────────────────────────────
     | Identity
     ─────────────────────────────────────────────────────────────────────── */

    public static function user(): ?User
    {
        static $cache = null;
        if ($cache === false) return null;
        if ($cache instanceof User) return $cache;

        $username = (string) config('services.babyboss.username', 'babyboss');
        $u = User::where('username', $username)->first();
        $cache = $u ?: false;
        return $u;
    }

    public static function isBabyBoss(?User $u): bool
    {
        if (!$u) return false;
        $username = (string) config('services.babyboss.username', 'babyboss');
        return $u->username === $username;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Public hooks — called from MessageController
     ─────────────────────────────────────────────────────────────────────── */

    /**
     * Called immediately after a user message has been persisted to a
     * conversation whose peer is BabyBoss. Routes the message through the
     * current phase, optionally queues replies / typing pulses.
     */
    public function handleIncoming(Conversation $conv, User $sender, string $body): void
    {
        $bb = self::user();
        if (!$bb || $sender->id === $bb->id) return;

        $state = $this->loadState($conv->id);

        // ── Reset on long idle ─────────────────────────────────────────────
        if (!empty($state['last_activity_at'])) {
            $idleFor = now()->diffInSeconds(Carbon::parse($state['last_activity_at']), absolute: true);
            if ($idleFor >= self::IDLE_RESET_SECONDS) {
                $state = [];
            }
        }

        // ── Reset on offline-cooldown expiry ───────────────────────────────
        // Once the 5-min cooldown lapses, the next incoming message starts a
        // fresh session (re-runs classification, greeting, etc.).
        if (!empty($state['offline_until']) && now()->gte(Carbon::parse($state['offline_until']))) {
            $state = [];
        }

        // ── Reset on unknown phase ─────────────────────────────────────────
        $validPhases = ['new', 'awaiting_title', 'searching_a', 'searching_b', 'searching_c', 'chitchat', 'offline'];
        if (!empty($state['phase']) && !in_array($state['phase'], $validPhases, true)) {
            $state = [];
        }

        $state['phase']            = $state['phase']   ?? 'new';
        $state['pending']          = $state['pending'] ?? [];
        $state['last_activity_at'] = now()->toIso8601String();

        $offlineActive = !empty($state['offline_until']) && now()->lt(Carbon::parse($state['offline_until']));

        // Mark this incoming message as "read" by BabyBoss after a small delay.
        // Skipped while the bot is in its post-session cooldown so the user can
        // see that messages aren't being seen during the 5-min break.
        if (!$offlineActive) {
            $state['mark_read_at'] = now()->addSeconds(random_int(2, 4))->toIso8601String();
        }

        // ── Offline gate — bot is "offline", swallow message ───────────────
        if ($offlineActive) {
            $this->saveState($conv->id, $state);
            return;
        }

        // ── Pause gate — bot is busy/searching, swallow message ────────────
        if (!empty($state['paused_until']) && now()->lt(Carbon::parse($state['paused_until']))) {
            $this->saveState($conv->id, $state);
            return;
        }
        if (!empty($state['paused_until'])) {
            unset($state['paused_until']);
        }

        // Bump BabyBoss's online indicator while actively in a session.
        User::where('id', $bb->id)->update(['updated_at' => now()]);

        $this->routeIncoming($state, $body);

        // If routing requested "pause until the last pending message lands",
        // resolve that against whatever's now at the tail of the queue.
        if (!empty($state['pause_until_last_pending']) && !empty($state['pending'])) {
            $state['paused_until'] = end($state['pending'])['deliver_at'];
        }
        unset($state['pause_until_last_pending']);

        $this->saveState($conv->id, $state);
    }

    /**
     * Called from MessageController on poll / thread / threads / unread.
     * Drives deferred work: the +20s classification, scheduled replies,
     * read-receipt landing, and the typing-indicator pulse. Idempotent.
     */
    public function tick(Conversation $conv): bool
    {
        $bb = self::user();
        if (!$bb) return false;

        $state = $this->loadState($conv->id);
        if (empty($state)) return false;

        $now = now();
        $changed = false;
        $deliveredAny = false;

        $offlineActive = !empty($state['offline_until']) && $now->lt(Carbon::parse($state['offline_until']));

        // 1) Mark the user's messages as read after the small delay — but only
        //    if we're not in the offline cooldown.
        if (!$offlineActive && !empty($state['mark_read_at']) && $now->gte(Carbon::parse($state['mark_read_at']))) {
            Message::where('conversation_id', $conv->id)
                ->where('sender_id', '!=', $bb->id)
                ->whereNull('read_at')
                ->update([
                    'read_at'      => $now,
                    'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())'),
                ]);
            unset($state['mark_read_at']);
            $changed = true;
        }

        // While offline, keep stomping mark_read_at off so a stale value never
        // fires once cooldown ends.
        if ($offlineActive && isset($state['mark_read_at'])) {
            unset($state['mark_read_at']);
            $changed = true;
        }

        // 2) Deferred classification — runs ~20s after the first message of a
        //    new session. Decides between book-finder flow and chitchat flow.
        if (!empty($state['classify_at']) && $now->gte(Carbon::parse($state['classify_at']))) {
            $buffer = (string) ($state['body_buffer'] ?? '');
            unset($state['classify_at'], $state['body_buffer'], $state['classify_started_at']);

            if ($this->looksLikeBook($buffer)) {
                $this->scheduleReply($state, $this->pickFrom(self::GREETINGS), random_int(1, 2));
                $this->scheduleReply($state, $this->pickFrom(self::TITLE_ASKS), self::GREETING_TO_TITLE_SECONDS, fromLastPending: true);
                $state['phase'] = 'awaiting_title';
                // Pause until title-ask lands so the user's reply afterwards is
                // unambiguously the title.
                $state['paused_until'] = end($state['pending'])['deliver_at'];
            } else {
                $this->scheduleReply($state, $this->pickFrom(self::CHITCHAT_OFFTOPIC_REPLIES), random_int(1, 3));
                $state['phase'] = 'chitchat';
                $state['chitchat_count'] = 1;
            }
            $changed = true;
        }

        // 3) Walk the pending queue. Anything due → insert; anything still
        //    pending but inside its typing window → pulse the typing flag.
        $remaining = [];
        $anyTyping = false;
        foreach ($state['pending'] ?? [] as $p) {
            $deliverAt  = Carbon::parse($p['deliver_at']);
            $typingFrom = Carbon::parse($p['typing_from_at']);

            if ($now->gte($deliverAt)) {
                Message::create([
                    'conversation_id' => $conv->id,
                    'sender_id'       => $bb->id,
                    'body'            => $p['body'],
                    'delivered_at'    => $now,
                ]);
                Conversation::where('id', $conv->id)->update(['last_message_at' => $now]);
                $deliveredAny = true;
                $changed = true;
                // Bot is actively delivering → keep the green dot alive.
                User::where('id', $bb->id)->update(['updated_at' => $now]);
                continue;
            }

            if ($now->gte($typingFrom)) $anyTyping = true;
            $remaining[] = $p;
        }
        $state['pending'] = $remaining;

        if ($anyTyping) {
            Cache::put(
                self::TYPING_CACHE_PREFIX . $conv->id . '.' . $bb->id,
                time(),
                now()->addSeconds(self::TYPING_HOLD_SECONDS),
            );
        }

        if ($changed) $this->saveState($conv->id, $state);
        return $deliveredAny;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Routing — decide what to do with the incoming message
     ─────────────────────────────────────────────────────────────────────── */

    private function routeIncoming(array &$state, string $body): void
    {
        $body  = trim($body);
        $phase = $state['phase'] ?? 'new';

        if ($phase === 'new') {
            $buffer = trim((string) ($state['body_buffer'] ?? '') . "\n" . $body);
            if (mb_strlen($buffer) > self::BODY_BUFFER_MAX_CHARS) {
                $buffer = mb_substr($buffer, -self::BODY_BUFFER_MAX_CHARS);
            }
            $state['body_buffer'] = $buffer;

            if (empty($state['classify_started_at'])) {
                $state['classify_started_at'] = now()->toIso8601String();
            }
            $started = Carbon::parse($state['classify_started_at']);
            $hardCap = $started->copy()->addSeconds(self::INITIAL_WAIT_SECONDS * 2);

            if (empty($state['classify_at'])) {
                $state['classify_at'] = now()->addSeconds(self::INITIAL_WAIT_SECONDS)->toIso8601String();
            } else {
                // Each follow-up message nudges classify_at out a bit, so the
                // user has room to finish their thought — but never beyond the
                // hard cap from when they first messaged.
                $current = Carbon::parse($state['classify_at']);
                $proposed = now()->addSeconds(self::INITIAL_WAIT_SECONDS);
                if ($proposed->gt($current)) {
                    if ($proposed->gt($hardCap)) $proposed = $hardCap;
                    $state['classify_at'] = $proposed->toIso8601String();
                }
            }
            return;
        }

        if ($phase === 'awaiting_title') {
            $title = $this->cleanTitle($body);
            if ($title === '') return; // wait for a non-empty reply

            $state['book_title'] = $title;
            $state['phase']      = 'searching_a';

            // 5s later: a short ack ("ok / got it / ahh ok").
            $this->scheduleReply($state, $this->pickFrom(self::TITLE_ACKS), self::TITLE_TO_ACK_SECONDS);
            // Brief natural beat, then the "give me a min" wait line.
            $this->scheduleReply($state, $this->pickFrom(self::WAIT_LINES), self::ACK_TO_WAIT_SECONDS, fromLastPending: true);

            // 4 min later: the actual search URL from Category A.
            $linkA = $this->resolveLink('A', $title) ?? $this->pickFrom(self::NO_LUCK_FALLBACK);
            $this->scheduleReply($state, $linkA, self::WAIT_TO_LINK_SECONDS, fromLastPending: true);
            // Then the "found this / try this / see this" nudge.
            $this->scheduleReply($state, $this->pickFrom(self::LINK_INTRO_LINES), self::LINK_TO_TRY_THIS_SECONDS, fromLastPending: true);

            $state['pause_until_last_pending'] = true;
            return;
        }

        if (in_array($phase, ['searching_a', 'searching_b', 'searching_c'], true)) {
            // Hardcoded acks are silently ignored — wait for a real signal.
            if ($this->isAck($body)) return;

            $verdict = $this->classifyLinkFeedback($body);
            $title   = (string) ($state['book_title'] ?? 'book');

            if ($verdict === 'not_working') {
                if ($phase === 'searching_c') {
                    $this->scheduleReply($state, $this->pickFrom(self::APOLOGY_AFTER_C), random_int(3, 5));
                    $this->scheduleOfflineCooldown($state);
                    return;
                }

                $nextCategory = $phase === 'searching_a' ? 'B' : 'C';
                $nextPhase    = $phase === 'searching_a' ? 'searching_b' : 'searching_c';

                $linkUrl = $this->resolveLink($nextCategory, $title) ?? $this->pickFrom(self::NO_LUCK_FALLBACK);
                $this->scheduleReply($state, $linkUrl, self::LINK_RETRY_DELAY_SECONDS);
                $this->scheduleReply($state, $this->pickFrom(self::TRY_THIS_NEXT_LINES), self::LINK_TO_TRY_THIS_SECONDS, fromLastPending: true);

                $state['phase'] = $nextPhase;
                $state['pause_until_last_pending'] = true;
                return;
            }

            if ($verdict === 'found') {
                $this->scheduleReply($state, $this->buildGoodbyeLine(), self::FOUND_TO_GOODBYE_SECONDS);
                $this->scheduleOfflineCooldown($state);
                return;
            }

            // 'other' — ambiguous, neither found nor failure. Stay quiet and
            // wait for a clearer follow-up from the user.
            return;
        }

        if ($phase === 'chitchat') {
            // Pivot mid-chitchat if the user now mentions a book — drop the
            // small-talk track and jump straight to asking for the title.
            // (No greeting; we've already exchanged messages.)
            if ($this->looksLikeBook($body)) {
                $this->scheduleReply($state, $this->pickFrom(self::TITLE_ASKS), random_int(2, 4));
                $state['phase'] = 'awaiting_title';
                $state['pause_until_last_pending'] = true;
                unset($state['chitchat_count']);
                return;
            }

            $count = (int) ($state['chitchat_count'] ?? 0);
            if ($count >= self::MAX_CHITCHAT_REPLIES) {
                // Already at the cap — go offline silently. Defensive; the
                // offline_until below should have closed this off already.
                $this->scheduleOfflineCooldown($state);
                return;
            }

            $this->scheduleReply($state, $this->pickFrom(self::CHITCHAT_REPLIES), random_int(5, 9));
            $state['chitchat_count'] = $count + 1;

            if ($state['chitchat_count'] >= self::MAX_CHITCHAT_REPLIES) {
                $this->scheduleOfflineCooldown($state);
            }
            return;
        }

        // 'offline' or anything unexpected → no reply.
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Scheduling
     ─────────────────────────────────────────────────────────────────────── */

    /** Persist a single reply on the pending queue with delivery + typing windows. */
    private function scheduleReply(array &$state, string $text, int $delaySeconds, bool $fromLastPending = false): void
    {
        $base = now();
        if ($fromLastPending && !empty($state['pending'])) {
            $lastDeliverAt = end($state['pending'])['deliver_at'];
            $base = Carbon::parse($lastDeliverAt);
        }

        $deliverAt = $base->copy()->addSeconds(max(1, $delaySeconds));

        // Typing flag appears a few seconds before the reply lands, but never
        // before "now" (so the wave doesn't precede the user's own bubble).
        $typingLead = min(4, max(1, (int) floor($delaySeconds * 0.4)));
        $typingFrom = $deliverAt->copy()->subSeconds($typingLead);
        if ($typingFrom->lt(now())) $typingFrom = now()->addSecond();

        $state['pending'][] = [
            'body'           => $text,
            'typing_from_at' => $typingFrom->toIso8601String(),
            'deliver_at'     => $deliverAt->toIso8601String(),
        ];
    }

    /** Set offline_until to "after the last scheduled reply lands + 5 minutes". */
    private function scheduleOfflineCooldown(array &$state): void
    {
        $lastDeliverAt = !empty($state['pending'])
            ? end($state['pending'])['deliver_at']
            : now()->toIso8601String();

        $state['offline_until'] = Carbon::parse($lastDeliverAt)
            ->addMinutes(self::OFFLINE_COOLDOWN_MINUTES)
            ->toIso8601String();
        $state['phase'] = 'offline';
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Classification — pure regex / keyword based.
     ─────────────────────────────────────────────────────────────────────── */

    /** Heuristic: does the buffered first message read like a book request? */
    private function looksLikeBook(string $body): bool
    {
        $body = trim($body);
        if ($body === '') return false;

        return (bool) preg_match(
            '/\b(book|novel|ebook|e-book|pdf|epub|mobi|read|reading|author|title|chapter|memoir|textbook|story|fiction|nonfiction|non-fiction|biography|series|volume)\b/iu',
            $body
        );
    }

    /**
     * 3-way classifier for a reply that came after a link was sent.
     * Returns one of 'not_working' | 'found' | 'other'.
     */
    private function classifyLinkFeedback(string $body): string
    {
        $body = trim($body);
        if ($body === '') return 'other';

        if (preg_match('/\b(not\s*work(ing|ed)?|didn\'?t\s*work|doesn\'?t\s*work|not\s*found|can\'?t\s*find|cant\s*find|broken|empty|404|nothing|no\s*luck|dead\s*link|fail(ed)?|error|missing|wrong\s*book|removed|unavailable|blocked)\b/iu', $body)) {
            return 'not_working';
        }

        if (preg_match('/\b(found|got\s*it|works|worked|working|thanks|thank\s*you|thx|ty|tysm|tyvm|perfect|awesome|legend|nice\s*one|great|amazing|done|downloaded|grabbed|saved|appreciate|cheers|sweet|yess+|got\s*the\s*book|reading\s*now)\b/iu', $body)) {
            return 'found';
        }

        return 'other';
    }

    /* ──────────────────────────────────────────────────────────────────────
     | urls.txt parsing
     ─────────────────────────────────────────────────────────────────────── */

    /** Pick a random URL from the given category (A/B/C) with the title substituted. */
    private function resolveLink(string $category, string $title): ?string
    {
        $categories = $this->loadCategorizedUrls();
        $catKey = strtoupper(trim($category));
        if (empty($categories[$catKey])) return null;

        $pool = $categories[$catKey];
        $template = $pool[array_rand($pool)];
        return $this->substituteTitle($template, $title);
    }

    /**
     * Parse urls.txt into ['A' => [url, ...], 'B' => [...], 'C' => [...]].
     * Header lines look like "Category A". URL lines start with http(s)://.
     * Blank lines and lines starting with '#' are ignored.
     */
    private function loadCategorizedUrls(): array
    {
        $path = (string) config('services.babyboss.search_urls_path') ?: base_path('urls.txt');
        if (!is_file($path) || !is_readable($path)) return [];

        $contents = @file_get_contents($path);
        if ($contents === false) return [];

        $cats = [];
        $current = null;
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) continue;

            if (preg_match('/^Category\s+([A-Za-z0-9]+)$/i', $line, $m)) {
                $current = strtoupper($m[1]);
                if (!isset($cats[$current])) $cats[$current] = [];
                continue;
            }

            if ($current !== null && (str_starts_with($line, 'http://') || str_starts_with($line, 'https://'))) {
                $cats[$current][] = $line;
            }
        }
        return $cats;
    }

    /** Replace the "sample book" placeholder in a URL template with the real title. */
    private function substituteTitle(string $template, string $title): string
    {
        $title = trim($title) ?: 'book';
        $pct   = rawurlencode($title);
        $plus  = str_replace('%20', '+', $pct);

        $replacements = [
            'sample%20book' => $pct,
            'sample+book'   => $plus,
            'sample book'   => $pct,
        ];
        foreach ($replacements as $needle => $value) {
            if (stripos($template, $needle) !== false) {
                return str_ireplace($needle, $value, $template);
            }
        }
        $sep = parse_url($template, PHP_URL_QUERY) === null ? '?' : '&';
        return $template . $sep . 'q=' . $plus;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Misc helpers
     ─────────────────────────────────────────────────────────────────────── */

    /** Farewell + shoutout combo — sent once the user reports the link worked. */
    private function buildGoodbyeLine(): string
    {
        $handle = (string) config('services.babyboss.shoutout_handle', 'tanbat');
        $variants = [
            "nice to meet you. please do a shoutout about our service on reddit or anywhere else for {$handle}",
            "see you again. give us a shoutout for {$handle} on socials if you can",
            "see you later. a quick mention of {$handle} on reddit would really help us",
            "bye bye. if you can drop a shoutout for {$handle} somewhere it'd mean a lot",
            "glad it worked. catch you later, and if you can shoutout {$handle} on socials, much appreciated",
            "nice meeting you. a small shoutout for {$handle} on reddit or any social would help us a ton",
        ];
        return $variants[array_rand($variants)];
    }

    /** Is a post-link reply just an acknowledgement (ignore silently)? */
    private function isAck(string $body): bool
    {
        $b = strtolower(trim($body));
        $b = preg_replace('/[!.?,]+/u', '', $b) ?? $b;
        $b = preg_replace('/\s+/u', ' ', $b) ?? $b;
        $b = trim($b);
        if ($b === '' || mb_strlen($b) > 30) return false;

        $patterns = [
            '/^(ok+|okay|kk+|k|kay)$/u',
            '/^(sure|alright|aight|right|fine|cool)$/u',
            '/^(will try|i\'?ll try|let me try|gonna try|trying|let me check|lemme check)$/u',
            '/^(got it|gotcha|noted)$/u',
            '/^(brb|hold on|hang on|one sec|gimme a sec|wait)$/u',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $b)) return true;
        }
        return false;
    }

    /** Lightly clean a user's title reply — strip common filler phrases. */
    private function cleanTitle(string $body): string
    {
        $t = trim($body);
        if ($t === '') return '';

        $t = preg_replace('/^(yes|yep|yeah|no|nope|nah)[,.\s]+/i', '', $t) ?? $t;
        $t = preg_replace('/^(hi+|hey+|hello+|yo+)\b[,.\s!?\-]+/iu', '', $t) ?? $t;

        $fillerPatterns = [
            '/^(i\'?m|i am|im)\s+(just\s+)?(looking|searching|trying\s+to\s+find|after|interested\s+in)(\s+for)?\s+/iu',
            '/^(just\s+)?(looking|searching)\s+for\s+/iu',
            '/^i\s+(want|need|wanna|would\s+like|\'?d\s+like)(\s+(to\s+)?(read|find|get|have))?\s+/iu',
            '/^(can|could|would|will)\s+(you|u)\s+(please\s+)?(find|get|send|share|give|recommend|help\s+me\s+(find|get|with))\s+(me\s+)?/iu',
            '/^please\s+(find|get|send|share|give|help)\s+(me\s+)?/iu',
            '/^(find|get|send|share|give)\s+me\s+/iu',
            '/^(do\s+(you|u)\s+(have|got)|(you|u)\s+got|got)\s+/iu',
            '/^(the|a|an)?\s*book\s+(called|named|titled|by|about)\s+/iu',
            '/^(it\'?s|its)\s+(called|named|titled)\s+/iu',
            '/^the\s+title\s+is\s+/iu',
        ];
        foreach ($fillerPatterns as $p) {
            $t = preg_replace($p, '', $t) ?? $t;
        }

        $t = preg_replace('/[,.\s]+(please|pls|plz|thanks|thank\s*you|thx|ty)\s*[!.\s]*$/iu', '', $t) ?? $t;
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        $t = trim($t, " \t\n\r\0\x0B.,;:!?\"'`-");

        if (mb_strlen($t) > 120) $t = mb_substr($t, 0, 120);
        return $t;
    }

    private function pickFrom(array $variants): string
    {
        return $variants[array_rand($variants)];
    }

    /* ──────────────────────────────────────────────────────────────────────
     | State storage
     ─────────────────────────────────────────────────────────────────────── */

    private function stateKey(int $convId): string
    {
        return 'babyboss.state.' . $convId;
    }

    private function loadState(int $convId): array
    {
        return (array) Cache::get($this->stateKey($convId), []);
    }

    private function saveState(int $convId, array $state): void
    {
        Cache::put($this->stateKey($convId), $state, self::STATE_TTL_SECONDS);
    }
}
