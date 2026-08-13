<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookDetail;
use App\Services\TelegramPoster;
use App\Support\TelegramSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Admin screen for the Telegram channel cross-poster.
 *
 *   GET    /admin/telegram            → status + settings form
 *   PUT    /admin/telegram            → save bot token, channel, caption, …
 *   POST   /admin/telegram/toggle     → enable / disable automatic posting
 *   POST   /admin/telegram/test       → send a test message to the channel
 *   DELETE /admin/telegram            → clear the stored credentials
 *
 * Unlike Reddit and Pinterest there's no OAuth dance here — a Telegram bot
 * authenticates with a static token from @BotFather, so the whole setup is
 * "paste the token, add the bot to the channel as an admin".
 */
class TelegramController extends Controller
{
    public function index()
    {
        $poster = new TelegramPoster();

        $bot     = null;
        $chat    = null;
        $error   = null;

        // Both probes are read-only, so it's safe to run them on every page
        // load — they're what turns this screen into a real health check
        // rather than a form that only *claims* to be connected.
        if ($poster->isConfigured()) {
            try {
                $bot  = $poster->getMe();
                $chat = $poster->getChat();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $maxAttempts = (int) config('telegram.max_attempts', 5);

        $stats = [
            'posted'  => BookDetail::whereNotNull('telegram_message_id')->count(),
            'pending' => BookDetail::whereNull('telegram_message_id')
                            ->where('telegram_attempts', '<', $maxAttempts)
                            ->whereNotNull('cover_url')->count(),
            'failed'  => BookDetail::whereNull('telegram_message_id')
                            ->where('telegram_attempts', '>=', $maxAttempts)->count(),
            'nocover' => BookDetail::whereNull('telegram_message_id')
                            ->whereNull('cover_url')->count(),
        ];

        return view('admin.telegram', [
            'enabled'      => TelegramSettings::enabled(),
            'configured'   => $poster->isConfigured(),
            'maskedToken'  => TelegramSettings::maskedToken(),
            'chatId'       => TelegramSettings::chatId(),
            'channelUrl'   => TelegramSettings::channelUrl(),
            'caption'      => TelegramSettings::captionTemplate(),
            'buttonText'   => TelegramSettings::buttonText(),
            'spacing'      => TelegramSettings::spacingMinutes(),
            'siteUrl'      => rtrim((string) config('telegram.site_url'), '/'),
            'bot'          => $bot,
            'chat'         => $chat,
            'probeError'   => $error,
            'stats'        => $stats,
            'maxAttempts'  => $maxAttempts,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            // Blank leaves the stored token untouched, so an admin can edit the
            // caption without re-pasting the secret.
            'bot_token'            => ['nullable', 'string', 'max:255', 'regex:/^\d+:[A-Za-z0-9_-]{30,}$/'],
            'chat_id'              => ['required', 'string', 'max:255'],
            'caption_template'     => ['required', 'string', 'max:2000'],
            'button_text'          => ['required', 'string', 'max:64'],
            'post_spacing_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ], [
            'bot_token.regex' => 'That doesn\'t look like a bot token. Expected the "123456789:AA…" form from @BotFather.',
        ]);

        $save = [
            'chat_id'              => trim($data['chat_id']),
            'caption_template'     => $data['caption_template'],
            'button_text'          => $data['button_text'],
            'post_spacing_minutes' => (string) $data['post_spacing_minutes'],
        ];
        if (!empty($data['bot_token'])) {
            $save['bot_token'] = trim($data['bot_token']);
        }

        TelegramSettings::save($save);

        // Immediately verify the new credentials so the admin finds out here
        // rather than from a silently failing heartbeat an hour later.
        $poster = new TelegramPoster();
        try {
            $chat  = $poster->getChat();
            $title = $chat['title'] ?? ($chat['username'] ?? $save['chat_id']);
            return redirect()->route('admin.telegram.index')
                ->with('status', "Saved — verified access to “{$title}”.");
        } catch (\Throwable $e) {
            return redirect()->route('admin.telegram.index')
                ->with('error', 'Saved, but Telegram rejected the check: ' . $e->getMessage());
        }
    }

    /** Flip automatic posting on/off without touching the credentials. */
    public function toggle(Request $request)
    {
        $on = $request->boolean('enabled');
        TelegramSettings::setEnabled($on);

        return back()->with('status', $on
            ? 'Telegram cross-posting enabled — new books will be announced in the channel.'
            : 'Telegram cross-posting disabled.');
    }

    /**
     * Send a one-off message to the channel. This posts publicly, so it's
     * behind an explicit button + confirm rather than running on page load.
     */
    public function test()
    {
        $poster = new TelegramPoster();

        if (!$poster->isConfigured()) {
            return back()->with('error', 'Set the bot token and channel first.');
        }

        try {
            $poster->sendMessage('✅ Tanbat is connected to this channel. New books will be posted here automatically.');
            return back()->with('status', 'Test message sent to the channel.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Test failed: ' . $e->getMessage());
        }
    }

    /** Clear the stored credentials and switch the integration off. */
    public function disconnect()
    {
        TelegramSettings::save(['bot_token' => null, 'chat_id' => null]);
        TelegramSettings::setEnabled(false);
        Cache::forget('telegram:last_post_at');

        return redirect()->route('admin.telegram.index')
            ->with('status', 'Telegram credentials cleared.');
    }
}
