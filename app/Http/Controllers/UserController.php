<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UserController extends Controller
{
    /** Profile tabs that are addressable at /{username}/{section}. */
    public const PROFILE_SECTIONS = [
        'posts', 'about', 'photos', 'videos', 'followers', 'following', 'articles',
    ];

    /**
     * GET /{username} and /{username}/{section} — Facebook-style profile page.
     * Open to guests; a $section (e.g. "followers", "photos") pre-selects the tab.
     */
    public function show(string $username, ?string $section = null): View|RedirectResponse|Response
    {
        // The BabyBoss persona has been retired in favour of the Tanbat
        // Assistant wizard. Anyone landing on his profile (legacy links,
        // bookmarks, the old "Message Boss" alias) is sent to the wizard.
        if (strcasecmp($username, (string) config('services.babyboss.username', 'babyboss')) === 0) {
            return redirect()->route('assistant');
        }

        // Old-site links point at members who have since been removed. Rather
        // than a bare 404 / bounce to the home page, show a themed "account
        // deleted" recovery page that helps the visitor connect with people.
        $profile = User::where('username', $username)->first();
        if (!$profile) {
            return $this->deletedProfile($username);
        }

        $viewer = Auth::user();

        // Record a "profile_visit" notification on every visit (no throttle — owner wants
        // to see each individual view, including repeats from the same person). Guests
        // have no actor to attribute, so only logged-in visitors are recorded.
        if ($viewer && $viewer->id !== $profile->id) {
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

        $tab = in_array($section, self::PROFILE_SECTIONS, true) ? $section : 'posts';

        return view('profile', ['profile' => $profile, 'tab' => $tab]);
    }

    /**
     * GET /albums/{username} — legacy WoWonder photo-album permalink. If the
     * member still exists, forward to their photos tab; otherwise fall through
     * to the same "account deleted" recovery page as the profile routes.
     */
    public function albums(string $username): RedirectResponse|Response
    {
        $user = User::where('username', $username)->first();
        if ($user) {
            return redirect()->route('profile.section', [
                'username' => $user->username,
                'section'  => 'photos',
            ]);
        }

        return $this->deletedProfile($username);
    }

    /**
     * Render the "this account was deleted" recovery page (HTTP 410 Gone).
     * Carries the requested handle plus a few site stats + the country list
     * used to seed the people-discovery filters. The suggested-people cards
     * themselves are loaded client-side from /api/discover/suggested.
     */
    private function deletedProfile(string $username): Response
    {
        $stats = [
            'posts'    => Post::count(),
            'users'    => User::count(),
            'likes'    => (int) Post::sum('likes_count'),
            'comments' => Comment::count(),
        ];

        $countries = User::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return response()->view('user-deleted', [
            'username'  => $username,
            'stats'     => $stats,
            'countries' => $countries,
        ], 410);
    }

    /** GET /api/users/{user}/followers — public list of a user's followers */
    public function followers(User $user): JsonResponse
    {
        return $this->relationList($user->followers());
    }

    /** GET /api/users/{user}/following — public list of who a user follows */
    public function following(User $user): JsonResponse
    {
        return $this->relationList($user->following());
    }

    /**
     * Shape a followers/following BelongsToMany relation into a paginated card list.
     * Guest-safe: follow state is only resolved when a viewer is authenticated.
     */
    private function relationList(BelongsToMany $relation): JsonResponse
    {
        $viewer = Auth::user();

        $paginator = $relation
            ->select(
                'users.id',
                'users.name',
                'users.username',
                'users.profile_picture',
                'users.banner_image',
                'users.country',
                'users.created_at',
            )
            ->orderBy('users.name')
            ->paginate(24);

        $userIds = collect($paginator->items())->pluck('id')->all();

        $followedSet = [];
        if ($viewer && $userIds) {
            $followedSet = array_flip(
                $viewer->following()->whereIn('users.id', $userIds)->pluck('users.id')->all()
            );
        }

        $items = collect($paginator->items())->map(fn (User $u) => [
            'id'              => $u->id,
            'name'            => $u->name,
            'username'        => $u->username,
            'profile_picture' => $u->avatarUrl(),
            'banner_image'    => $u->bannerUrl(),
            'country'         => $u->country,
            'joined_at'       => $u->created_at?->format('M Y'),
            'url'             => url('/' . $u->username),
            'is_self'         => $viewer && $viewer->id === $u->id,
            'is_following'    => isset($followedSet[$u->id]),
        ]);

        return response()->json([
            'items'     => $items,
            'page'      => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total'     => $paginator->total(),
            'has_more'  => $paginator->hasMorePages(),
        ]);
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
