<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UserController extends Controller
{
    /** GET /u/{username} — Facebook-style profile page */
    public function show(string $username): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('landing');
        }

        // The BabyBoss persona has been retired in favour of the Tanbat
        // Assistant wizard. Anyone landing on his profile (legacy links,
        // bookmarks, the old "Message Boss" alias) is sent to the wizard.
        if (strcasecmp($username, (string) config('services.babyboss.username', 'babyboss')) === 0) {
            return redirect()->route('assistant');
        }

        $profile = User::where('username', $username)->firstOrFail();
        $viewer  = Auth::user();

        // Record a "profile_visit" notification on every visit (no throttle — owner wants
        // to see each individual view, including repeats from the same person).
        if ($viewer->id !== $profile->id) {
            UserNotification::create([
                'user_id'  => $profile->id,
                'actor_id' => $viewer->id,
                'type'     => 'profile_visit',
                'data'     => [
                    'actor_name'     => $viewer->name,
                    'actor_username' => $viewer->username,
                ],
            ]);
        }

        return view('profile', ['profile' => $profile]);
    }

    /** GET /api/users/{user}/posts — paginated feed of a single user's posts */
    public function posts(User $user, Request $request): JsonResponse
    {
        // bookDetail is required for book-type posts to shape correctly — without
        // it shapePublic() emits a null book payload and the card renders as a
        // bare "No cover" + title. Matches the main feed's eager-loads.
        $with = ['user:id,name,username,profile_picture', 'category', 'media', 'tags', 'bookDetail'];
        if (Auth::check()) {
            $uid = Auth::id();
            $with['likers'] = fn ($q) => $q->where('users.id', $uid);
        }

        $posts = Post::with($with)
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($posts->items())->map(fn ($p) => (new PostController())->shapePublic($p)),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'total'        => $posts->total(),
            ],
        ]);
    }

    /** GET /api/users/{user} — profile JSON for the SPA */
    public function profile(User $user): JsonResponse
    {
        $viewer = Auth::user();
        $row = Post::where('user_id', $user->id)
            ->selectRaw('COUNT(*) AS posts, COALESCE(SUM(views_count),0) AS reach, COALESCE(SUM(likes_count),0) AS likes, COALESCE(SUM(comments_count),0) AS comments')
            ->first();

        return response()->json([
            'profile' => [
                'id'              => $user->id,
                'name'            => $user->name,
                'username'        => $user->username,
                'profile_picture' => $user->avatarUrl(),
                'banner_image'    => $user->bannerUrl(),
                'bio'             => $user->bio,
                'role'            => $user->role,
                'country'         => $user->country,
                'created_at'      => $user->created_at?->format('F Y'),
                'is_self'         => $viewer && $viewer->id === $user->id,
                'is_following'    => $viewer ? $viewer->isFollowing($user->id) : false,
                'counts' => [
                    'posts'      => (int) ($row->posts ?? 0),
                    'reach'      => (int) ($row->reach ?? 0),
                    'likes'      => (int) ($row->likes ?? 0),
                    'comments'   => (int) ($row->comments ?? 0),
                    'followers'  => $user->followers()->count(),
                    'following'  => $user->following()->count(),
                ],
            ],
        ]);
    }

    /** PATCH /api/users/{user} — update the authenticated user's editable profile fields. */
    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless(Auth::id() === $user->id, 403);

        $data = $request->validate([
            'name'    => 'sometimes|required|string|max:80',
            'bio'     => 'sometimes|nullable|string|max:280',
            'country' => 'sometimes|nullable|string|max:5',
        ]);

        $user->fill($data)->save();

        return response()->json([
            'success' => true,
            'profile' => [
                'name'    => $user->name,
                'bio'     => $user->bio,
                'country' => $user->country,
            ],
        ]);
    }

    /** POST /api/users/{user}/avatar — replace the authenticated user's profile picture. */
    public function updateAvatar(Request $request, User $user): JsonResponse
    {
        abort_unless(Auth::id() === $user->id, 403);
        $request->validate(['avatar' => 'required|image|max:8192']);

        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->profile_picture = $path;
        $user->save();

        return response()->json(['success' => true, 'profile_picture' => $user->avatarUrl()]);
    }

    /** POST /api/users/{user}/banner — replace the authenticated user's profile banner. */
    public function updateBanner(Request $request, User $user): JsonResponse
    {
        abort_unless(Auth::id() === $user->id, 403);
        $request->validate(['banner' => 'required|image|max:12288']);

        if ($user->banner_image) {
            Storage::disk('public')->delete($user->banner_image);
        }

        $path = $request->file('banner')->store('banners', 'public');
        $user->banner_image = $path;
        $user->save();

        return response()->json(['success' => true, 'banner_image' => $user->bannerUrl()]);
    }

    /** POST /api/users/{user}/follow — toggle follow */
    public function toggleFollow(User $user): JsonResponse
    {
        $viewer = Auth::user();
        if ($viewer->id === $user->id) {
            return response()->json(['message' => 'You cannot follow yourself.'], 422);
        }

        $existing = $viewer->following()->where('users.id', $user->id)->exists();
        if ($existing) {
            $viewer->following()->detach($user->id);
            $following = false;
        } else {
            $viewer->following()->attach($user->id);
            $following = true;

            // Notify the followed user
            UserNotification::create([
                'user_id'  => $user->id,
                'actor_id' => $viewer->id,
                'type'     => 'follow',
                'data'     => [
                    'actor_name'     => $viewer->name,
                    'actor_username' => $viewer->username,
                ],
            ]);
        }

        return response()->json([
            'success'   => true,
            'following' => $following,
            'followers' => $user->followers()->count(),
        ]);
    }
}
