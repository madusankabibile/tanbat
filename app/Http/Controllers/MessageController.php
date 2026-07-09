<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MessageController extends Controller
{
    private const TYPING_TTL   = 3;       // seconds the typing flag stays alive
    private const TYPING_FRESH = 2;       // server only reports typing if last ping is within this many seconds
    private const MSG_MAX_LEN  = 4000;
    private const ATTACH_DIR   = 'messages';

    /* ------------------------------------------------------------------ */
    /* Pages                                                              */
    /* ------------------------------------------------------------------ */

    public function index(): View|RedirectResponse
    {
        if (!Auth::check()) return redirect()->route('home');
        return view('messages', ['peer' => null]);
    }

    public function show(User $user): View|RedirectResponse
    {
        if (!Auth::check()) return redirect()->route('home');
        if ($user->id === Auth::id()) return redirect()->route('messages.index');
        // The legacy BabyBoss persona has been replaced by Tanbat Assistant.
        // Any "Message BabyBoss" link in the wild now lands on the wizard.
        if ($user->username === (string) config('services.babyboss.username', 'babyboss')) {
            return redirect()->route('assistant');
        }
        return view('messages', ['peer' => $user]);
    }

    /* ------------------------------------------------------------------ */
    /* JSON: threads list                                                 */
    /* ------------------------------------------------------------------ */

    public function threads(): JsonResponse
    {
        $uid = Auth::id();
        $this->markActive($uid);

        $convs = Conversation::query()
            ->where(fn ($q) => $q->where('user_one_id', $uid)->orWhere('user_two_id', $uid))
            ->with([
                'userOne:id,name,username,profile_picture,updated_at',
                'userTwo:id,name,username,profile_picture,updated_at',
                'latestMessage',
            ])
            ->orderByDesc('last_message_at')
            ->limit(40)
            ->get();

        $convIds = $convs->pluck('id');

        // Visiting the threads list = peer is "online" right now → mark every
        // pending message as delivered.
        $this->markDeliveredForUser($convIds, $uid);

        // Bulk unread count per conversation
        $unreadByConv = Message::query()
            ->whereIn('conversation_id', $convIds)
            ->where('sender_id', '!=', $uid)
            ->whereNull('read_at')
            ->select('conversation_id', DB::raw('COUNT(*) as c'))
            ->groupBy('conversation_id')
            ->pluck('c', 'conversation_id');

        return response()->json([
            'threads' => $convs->map(function (Conversation $c) use ($uid, $unreadByConv) {
                $peer = $c->user_one_id === $uid ? $c->userTwo : $c->userOne;
                $last = $c->latestMessage->first();
                return [
                    'conversation_id' => $c->id,
                    'peer'            => $this->shapePeer($peer),
                    'last_message'    => $last ? [
                        'id'         => $last->id,
                        'body'       => $last->body,
                        'has_image'  => !empty($last->attachment_path),
                        'is_mine'    => $last->sender_id === $uid,
                        'created_at' => $last->created_at?->toIso8601String(),
                        'read'       => !is_null($last->read_at),
                    ] : null,
                    'unread'          => (int) ($unreadByConv[$c->id] ?? 0),
                    'updated_at'      => $c->last_message_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* JSON: open a thread with a peer (creates if needed)                */
    /* ------------------------------------------------------------------ */

    public function thread(User $user): JsonResponse
    {
        $uid = Auth::id();
        $this->markActive($uid);
        if ($user->id === $uid) {
            return response()->json(['message' => 'Cannot message yourself.'], 422);
        }

        $conv = Conversation::between($uid, $user->id);

        // Opening a thread implies viewer is online → mark incoming as delivered, then read.
        $this->markDelivered($conv->id, $uid);
        $this->markRead($conv->id, $uid);

        $messages = Message::where('conversation_id', $conv->id)
            ->orderBy('id', 'asc')
            ->limit(80)
            ->get();

        return response()->json([
            'conversation_id'        => $conv->id,
            'peer'                   => $this->shapePeer($user),
            'peer_online'            => $this->isPeerOnline($user->refresh()),
            'messages'               => $messages->map(fn (Message $m) => $this->shapeMessage($m, $uid)),
            'peer_typing'            => $this->isTyping($conv->id, $user->id),
            'peer_last_read_id'      => $this->peerLastReadId($conv->id, $uid),
            'peer_last_delivered_id' => $this->peerLastDeliveredId($conv->id, $uid),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* JSON: send a message                                               */
    /* ------------------------------------------------------------------ */

    public function send(User $user, Request $request): JsonResponse
    {
        $uid = Auth::id();
        $this->markActive($uid);
        if ($user->id === $uid) {
            return response()->json(['message' => 'Cannot message yourself.'], 422);
        }

        $data = $request->validate([
            'body'       => 'nullable|string|max:' . self::MSG_MAX_LEN,
            'attachment' => 'nullable|image|max:5120', // 5 MB
        ]);

        $body = trim((string) ($data['body'] ?? ''));
        $hasAttachment = $request->hasFile('attachment');

        if ($body === '' && !$hasAttachment) {
            return response()->json(['message' => 'Empty message.'], 422);
        }

        $conv = Conversation::between($uid, $user->id);

        $attrs = [
            'conversation_id' => $conv->id,
            'sender_id'       => $uid,
            'body'            => $body,
        ];

        if ($hasAttachment) {
            $file = $request->file('attachment');
            $stored = $file->store(self::ATTACH_DIR . '/' . $conv->id, 'public');
            $attrs['attachment_path'] = $stored;
            $attrs['attachment_type'] = 'image';
        }

        // If the peer is online right now, mark this message as delivered immediately.
        if ($this->isPeerOnline($user)) {
            $attrs['delivered_at'] = now();
        }

        $msg = Message::create($attrs);

        $conv->forceFill(['last_message_at' => $msg->created_at])->save();
        $this->clearTyping($conv->id, $uid);

        return response()->json(['message' => $this->shapeMessage($msg, $uid)]);
    }

    /* ------------------------------------------------------------------ */
    /* JSON: poll for updates                                             */
    /* ------------------------------------------------------------------ */

    public function poll(User $user, Request $request): JsonResponse
    {
        $uid     = Auth::id();
        $this->markActive($uid);
        $afterId = (int) $request->query('after_id', 0);

        $conv = Conversation::between($uid, $user->id);

        // Polling = I'm online for this conv → my pending messages get delivered.
        $this->markDelivered($conv->id, $uid);

        $newOnes = Message::where('conversation_id', $conv->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get();

        // If any of those are incoming, they're also now read.
        if ($newOnes->contains(fn ($m) => $m->sender_id !== $uid)) {
            $this->markRead($conv->id, $uid);
        }

        return response()->json([
            'conversation_id'        => $conv->id,
            'peer_online'            => $this->isPeerOnline($user->refresh()),
            'messages'               => $newOnes->map(fn (Message $m) => $this->shapeMessage($m, $uid)),
            'peer_typing'            => $this->isTyping($conv->id, $user->id),
            'peer_last_read_id'      => $this->peerLastReadId($conv->id, $uid),
            'peer_last_delivered_id' => $this->peerLastDeliveredId($conv->id, $uid),
            // Highest message id currently stored for this conversation, or
            // null if the thread is empty. Clients use this to detect a
            // server-side history wipe (e.g. BabyBoss's post-success reset)
            // and clear their local view accordingly.
            'thread_max_id'          => Message::where('conversation_id', $conv->id)->max('id'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* JSON: typing / stop-typing                                         */
    /* ------------------------------------------------------------------ */

    public function typing(User $user): JsonResponse
    {
        $uid = Auth::id();
        $this->markActive($uid);
        $conv = Conversation::between($uid, $user->id);
        Cache::put($this->typingKey($conv->id, $uid), time(), now()->addSeconds(self::TYPING_TTL));
        return response()->json(['ok' => true]);
    }

    public function stopTyping(User $user): JsonResponse
    {
        $uid = Auth::id();
        $conv = Conversation::between($uid, $user->id);
        $this->clearTyping($conv->id, $uid);
        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */
    /* JSON: explicit read                                                */
    /* ------------------------------------------------------------------ */

    public function read(User $user): JsonResponse
    {
        $uid = Auth::id();
        $conv = Conversation::between($uid, $user->id);
        $this->markDelivered($conv->id, $uid);
        $count = $this->markRead($conv->id, $uid);
        return response()->json(['marked' => $count]);
    }

    /* ------------------------------------------------------------------ */
    /* JSON: total unread (for navbar badge)                              */
    /* ------------------------------------------------------------------ */

    public function unreadCount(): JsonResponse
    {
        $uid = Auth::id();
        $this->markActive($uid);

        $convIds = Conversation::query()
            ->where('user_one_id', $uid)->orWhere('user_two_id', $uid)
            ->pluck('id');

        $total = Message::whereIn('conversation_id', $convIds)
            ->where('sender_id', '!=', $uid)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread' => $total]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function shapeMessage(Message $m, int $viewerId): array
    {
        return [
            'id'              => $m->id,
            'body'            => $m->body,
            'attachment_url'  => $m->attachmentUrl(),
            'attachment_type' => $m->attachment_type,
            'is_mine'         => $m->sender_id === $viewerId,
            'sender_id'       => $m->sender_id,
            'created_at'      => $m->created_at?->toIso8601String(),
            'delivered'       => !is_null($m->delivered_at),
            'read'            => !is_null($m->read_at),
        ];
    }

    private function shapePeer(?User $u): ?array
    {
        if (!$u) return null;
        return [
            'id'              => $u->id,
            'name'            => $u->name,
            'username'        => $u->username,
            'profile_picture' => $u->avatarUrl(),
            'online'          => $this->isPeerOnline($u),
        ];
    }

    private function isPeerOnline(User $u): bool
    {
        return $u->updated_at && $u->updated_at->gte(now()->subMinutes(5));
    }

    /** Refresh the auth user's updated_at so peers see them as online while messaging. */
    private function markActive(int $uid): void
    {
        // Bare UPDATE so we don't hydrate the model or fire Eloquent events.
        User::where('id', $uid)->update(['updated_at' => now()]);
    }

    private function markRead(int $convId, int $viewerId): int
    {
        return Message::where('conversation_id', $convId)
            ->where('sender_id', '!=', $viewerId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())')]);
    }

    private function markDelivered(int $convId, int $viewerId): int
    {
        return Message::where('conversation_id', $convId)
            ->where('sender_id', '!=', $viewerId)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]);
    }

    /** Bulk delivery sweep across many conversations (threads list visit). */
    private function markDeliveredForUser($convIds, int $viewerId): int
    {
        if (empty($convIds) || (is_countable($convIds) && count($convIds) === 0)) return 0;
        return Message::whereIn('conversation_id', $convIds)
            ->where('sender_id', '!=', $viewerId)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]);
    }

    private function peerLastReadId(int $convId, int $viewerId): ?int
    {
        return Message::where('conversation_id', $convId)
            ->where('sender_id', $viewerId)
            ->whereNotNull('read_at')
            ->max('id');
    }

    private function peerLastDeliveredId(int $convId, int $viewerId): ?int
    {
        return Message::where('conversation_id', $convId)
            ->where('sender_id', $viewerId)
            ->whereNotNull('delivered_at')
            ->max('id');
    }

    private function typingKey(int $convId, int $userId): string
    {
        return "msg.typing.{$convId}.{$userId}";
    }

    private function isTyping(int $convId, int $peerId): bool
    {
        $ts = Cache::get($this->typingKey($convId, $peerId));
        if (!$ts) return false;
        return (time() - (int) $ts) <= self::TYPING_FRESH;
    }

    private function clearTyping(int $convId, int $userId): void
    {
        Cache::forget($this->typingKey($convId, $userId));
    }
}
