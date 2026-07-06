<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\UserNotification;
use App\Services\InteractionRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    /** POST /api/posts/{post}/comments */
    public function store(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'body'      => 'nullable|string|max:1000',
            'image'     => 'nullable|image|max:8192',
            'media_id'  => [
                'nullable',
                'integer',
                Rule::exists('post_media', 'id')->where(fn ($q) => $q->where('post_id', $post->id)),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')->where(fn ($q) => $q->where('post_id', $post->id)),
            ],
        ]);

        if (empty($data['body']) && !$request->hasFile('image')) {
            return response()->json([
                'message' => 'Comment must contain text or an image.',
                'errors'  => ['body' => ['Write something or attach an image.']],
            ], 422);
        }

        // Only allow two levels — if the parent is itself a reply, attach to its parent instead.
        // Replies always inherit the thread's media_id so they stay in the right image thread.
        $parentId = null;
        $mediaId  = $data['media_id'] ?? null;
        if (!empty($data['parent_id'])) {
            $parent = Comment::find($data['parent_id']);
            if ($parent) {
                $root = $parent->parent_id ? Comment::find($parent->parent_id) : $parent;
                $parentId = $root?->id;
                $mediaId  = $root?->media_id;
            }
        }

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('comments', 'public')
            : null;

        $c = $post->comments()->create([
            'user_id'    => Auth::id(),
            'media_id'   => $mediaId,
            'parent_id'  => $parentId,
            'body'       => $data['body'] ?? '',
            'image_path' => $imagePath,
        ]);

        $post->increment('comments_count');

        // Feed the recommendation affinity: a comment is a strong interest signal.
        app(InteractionRecorder::class)->record(Auth::id(), $post->id, 'comment');

        // Notify the post owner — and (for replies) the parent comment author if different.
        $actor = Auth::user();
        if ($post->user_id !== $actor->id) {
            $isReply = (bool) $parentId;
            UserNotification::create([
                'user_id'  => $post->user_id,
                'actor_id' => $actor->id,
                'type'     => $isReply ? 'reply_post' : 'comment_post',
                'data'     => [
                    'actor_name'     => $actor->name,
                    'actor_username' => $actor->username,
                    'post_id'        => $post->id,
                    'media_id'       => $mediaId,
                    'comment_id'     => $c->id,
                ],
            ]);
        }
        if ($parentId) {
            $parentRow = Comment::find($parentId);
            if ($parentRow && $parentRow->user_id !== $actor->id && $parentRow->user_id !== $post->user_id) {
                UserNotification::create([
                    'user_id'  => $parentRow->user_id,
                    'actor_id' => $actor->id,
                    'type'     => 'reply_comment',
                    'data'     => [
                        'actor_name'     => $actor->name,
                        'actor_username' => $actor->username,
                        'post_id'        => $post->id,
                        'media_id'       => $mediaId,
                        'comment_id'     => $c->id,
                    ],
                ]);
            }
        }

        $c->load('user:id,name,username,profile_picture');

        $mediaCount = $mediaId === null
            ? null
            : (int) DB::table('comments')->where('post_id', $post->id)->where('media_id', $mediaId)->count();

        return response()->json([
            'success'              => true,
            'comment'              => $this->shape($c),
            'comments_count'       => $post->fresh()->comments_count,
            'media_id'             => $mediaId,
            'media_comments_count' => $mediaCount,
        ], 201);
    }

    /** DELETE /api/comments/{comment} */
    public function destroy(Comment $comment): JsonResponse
    {
        abort_unless($comment->user_id === Auth::id() || Auth::user()?->isAdmin(), 403);
        $post = $comment->post;

        // Count this comment + cascaded reply rows so we can decrement properly.
        $deleted = 1 + $comment->replies()->count();
        $comment->delete();
        $post->decrement('comments_count', $deleted);

        return response()->json([
            'success'        => true,
            'deleted'        => $deleted,
            'comments_count' => $post->fresh()->comments_count,
        ]);
    }

    public static function shape(Comment $c, bool $withReplies = false): array
    {
        $payload = [
            'id'         => $c->id,
            'parent_id'  => $c->parent_id,
            'media_id'   => $c->media_id,
            'body'       => $c->body,
            'image_url'  => $c->image_url,
            'created_at' => $c->created_at?->diffForHumans(),
            'user'       => $c->user ? [
                'id'              => $c->user->id,
                'name'            => $c->user->name,
                'username'        => $c->user->username,
                'profile_picture' => $c->user->profile_picture
                    ? asset('storage/' . $c->user->profile_picture) : null,
            ] : null,
        ];

        if ($withReplies) {
            $payload['replies'] = $c->replies->map(fn ($r) => self::shape($r))->values();
        }

        return $payload;
    }
}
