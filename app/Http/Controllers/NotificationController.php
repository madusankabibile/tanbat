<?php

namespace App\Http\Controllers;

use App\Models\BookDetail;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /** GET /api/notifications — recent notifications + unread count */
    public function index(): JsonResponse
    {
        $uid = Auth::id();

        $notifs = UserNotification::with('actor:id,name,username,profile_picture')
            ->where('user_id', $uid)
            ->latest()
            ->limit(20)
            ->get();

        $unread = UserNotification::where('user_id', $uid)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread' => $unread,
            'items'  => $notifs->map(fn (UserNotification $n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'data'       => $n->data,
                'read'       => !is_null($n->read_at),
                'created_at' => $n->created_at?->diffForHumans(),
                'actor'      => $n->actor ? [
                    'id'              => $n->actor->id,
                    'name'            => $n->actor->name,
                    'username'        => $n->actor->username,
                    'profile_picture' => $n->actor->avatarUrl(),
                ] : null,
                'message'    => $this->message($n),
                'link'       => $this->link($n),
            ]),
        ]);
    }

    /** POST /api/notifications/read — mark all as read */
    public function markRead(): JsonResponse
    {
        UserNotification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function message(UserNotification $n): string
    {
        $actor = $n->data['actor_name'] ?? ($n->actor->name ?? 'Someone');
        $reactionVerb = [
            'like' => 'liked', 'love' => 'loved', 'haha' => 'laughed at',
            'wow'  => 'was wowed by', 'sad' => 'felt sad about', 'angry' => 'was angry about',
        ][$n->data['reaction'] ?? 'like'] ?? 'reacted to';
        $bookTitle = $n->data['title'] ?? 'your book';
        return match ($n->type) {
            'profile_visit' => "{$actor} visited your profile",
            'follow'        => "{$actor} started following you",
            'like_post'     => "{$actor} {$reactionVerb} your post",
            'comment_post'  => "{$actor} commented on your post",
            'reply_post'    => "{$actor} replied on your post",
            'reply_comment' => "{$actor} replied to your comment",
            'share_post'    => "{$actor} shared your post",
            'book_ready'    => "Your book \"{$bookTitle}\" is ready",
            default         => $n->type,
        };
    }

    private function link(UserNotification $n): ?string
    {
        $postId = $n->data['post_id'] ?? null;

        // Book notifications open the dedicated /books/{slug} page — its own
        // URL, its own UI. Falls back to /books if the slug somehow vanished.
        if ($n->type === 'book_ready' && $postId) {
            $slug = BookDetail::where('post_id', $postId)->value('slug');
            return $slug ? url('/books/' . $slug) : url('/books');
        }

        // Other post-related notifications deep-link to the home feed anchor.
        $isPostType = in_array($n->type, ['like_post', 'comment_post', 'reply_post', 'reply_comment', 'share_post'], true);
        if ($isPostType && $postId) {
            return url('/home') . '#post-' . $postId;
        }
        $username = $n->data['actor_username'] ?? $n->actor?->username;
        return $username ? url('/' . $username) : null;
    }
}
