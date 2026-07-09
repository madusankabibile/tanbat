<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\GeoLocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PeopleController extends Controller
{
    /** GET /discover/people — People Discovery page */
    public function page(): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('home');
        }
        return view('people');
    }

    /** GET /api/people/summary — top-line counts for the three sections */
    public function summary(): JsonResponse
    {
        $viewer = Auth::user();
        if (!$viewer) return response()->json(['message' => 'Unauthenticated'], 401);

        $following = $viewer->following()->count();
        $followers = $viewer->followers()->count();
        $visitors  = UserNotification::where('user_id', $viewer->id)
            ->where('type', 'profile_visit')
            ->whereNotNull('actor_id')
            ->distinct('actor_id')
            ->count('actor_id');

        return response()->json([
            'counts' => [
                'following' => $following,
                'followers' => $followers,
                'visitors'  => $visitors,
                'discover'  => User::where('id', '!=', $viewer->id)->count(),
            ],
        ]);
    }

    /** GET /api/people/{section} — paginated, filtered list of users */
    public function list(Request $request, string $section): JsonResponse
    {
        $viewer = Auth::user();
        if (!$viewer) return response()->json(['message' => 'Unauthenticated'], 401);

        $perPage = max(6, min(48, (int) $request->query('per_page', 18)));
        $page    = max(1, (int) $request->query('page', 1));

        $base = $this->baseQueryForSection($section, $viewer->id);

        $base = $this->applyFilters($base, $request);

        $sort = (string) $request->query('sort', $section === 'visitors' ? 'recent' : 'name');
        $base = $this->applySort($base, $sort, $section, $viewer->id);

        $paginator = $base
            ->select(
                'users.id',
                'users.name',
                'users.username',
                'users.profile_picture',
                'users.banner_image',
                'users.country',
                'users.gender',
                'users.age',
                'users.created_at',
            )
            ->paginate($perPage, ['*'], 'page', $page);

        $userIds = collect($paginator->items())->pluck('id')->all();

        // Bulk-load follow state so we can show Follow/Following on each card.
        $followedIds = $userIds
            ? $viewer->following()->whereIn('users.id', $userIds)->pluck('users.id')->all()
            : [];
        $followedSet = array_flip($followedIds);

        // For the "visitors" section we also surface the most recent visit timestamp.
        $visitMeta = [];
        if ($section === 'visitors' && $userIds) {
            $visitMeta = UserNotification::where('user_id', $viewer->id)
                ->where('type', 'profile_visit')
                ->whereIn('actor_id', $userIds)
                ->select('actor_id', DB::raw('MAX(created_at) as last_visit'), DB::raw('COUNT(*) as visit_count'))
                ->groupBy('actor_id')
                ->get()
                ->keyBy('actor_id');
        }

        $items = collect($paginator->items())->map(function (User $u) use ($followedSet, $visitMeta, $viewer) {
            $card = [
                'id'              => $u->id,
                'name'            => $u->name,
                'username'        => $u->username,
                'profile_picture' => $u->avatarUrl(),
                'banner_image'    => $u->bannerUrl(),
                'country'         => $u->country,
                'gender'          => $u->gender,
                'age'             => $u->age,
                'joined_at'       => $u->created_at?->format('M Y'),
                'url'             => url('/' . $u->username),
                'is_self'         => $viewer->id === $u->id,
                'is_following'    => isset($followedSet[$u->id]),
            ];
            if ($visit = $visitMeta[$u->id] ?? null) {
                $card['last_visit']   = $visit->last_visit
                    ? \Illuminate\Support\Carbon::parse($visit->last_visit)->diffForHumans()
                    : null;
                $card['visit_count']  = (int) $visit->visit_count;
            }
            return $card;
        });

        return response()->json([
            'section'  => $section,
            'items'    => $items,
            'page'     => $paginator->currentPage(),
            'last_page'=> $paginator->lastPage(),
            'total'    => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * GET /api/discover/suggested — PUBLIC suggested-people list.
     *
     * Powers the "account deleted" recovery page: when a visitor lands on a
     * profile URL for a member who no longer exists, we surface people to
     * connect with instead. Results are prioritised by the visitor's own
     * country (resolved from their IP) and by how recently active each member
     * is. Guest-safe — follow state is only resolved for authenticated viewers.
     */
    public function suggested(Request $request, GeoLocator $geo): JsonResponse
    {
        $perPage = max(6, min(48, (int) $request->query('per_page', 18)));
        $page    = max(1, (int) $request->query('page', 1));

        $viewer = Auth::user();

        $base = User::query()
            // Hide automated content bots + the shared anonymous account.
            ->whereNotIn('users.username', config('bots.usernames', []));
        if ($viewer) {
            $base->where('users.id', '!=', $viewer->id);
        }

        $base = $this->applyFilters($base, $request);

        // Only auto-detect the visitor's country when they haven't picked one.
        $explicitCountry = trim((string) $request->query('country', ''));
        $geoCountry = ($explicitCountry === '' || $explicitCountry === 'all')
            ? $geo->country($request)
            : null;

        $recentWindow = now()->subDay();
        $onlineWindow = now()->subMinutes(5);

        // Select the explicit columns first, THEN withCount — so its subquery
        // aliases (recent_posts, …) are appended and remain available to the
        // ORDER BY below (a later ->select would drop them).
        $base->select(
            'users.id', 'users.name', 'users.username',
            'users.profile_picture', 'users.banner_image',
            'users.country', 'users.gender', 'users.age',
            'users.created_at', 'users.updated_at',
        )->withCount([
            'posts as recent_posts'        => fn ($q) => $q->where('created_at', '>=', $recentWindow),
            'comments as recent_comments'  => fn ($q) => $q->where('created_at', '>=', $recentWindow),
            'posts as total_posts',
        ]);

        $sort = (string) $request->query('sort', 'suggested');
        if ($sort === 'suggested') {
            // Home-country members first, then most recently active, then prolific.
            if ($geoCountry) {
                $base->orderByRaw('CASE WHEN users.country = ? THEN 0 ELSE 1 END', [$geoCountry]);
            }
            $base->orderByRaw('(recent_posts + recent_comments) desc')
                 ->orderByDesc('total_posts')
                 ->orderByDesc('users.updated_at');
        } else {
            $base = $this->applySort($base, $sort, 'discover', $viewer?->id ?? 0);
        }

        $paginator = $base->paginate($perPage, ['*'], 'page', $page);

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
            'gender'          => $u->gender,
            'age'             => $u->age,
            'joined_at'       => $u->created_at?->format('M Y'),
            'url'             => url('/' . $u->username),
            'online'          => $u->updated_at && $u->updated_at->gte($onlineWindow),
            'is_self'         => $viewer && $viewer->id === $u->id,
            'is_following'    => isset($followedSet[$u->id]),
        ]);

        return response()->json([
            'items'       => $items,
            'geo_country' => $geoCountry,
            'page'        => $paginator->currentPage(),
            'last_page'   => $paginator->lastPage(),
            'total'       => $paginator->total(),
            'has_more'    => $paginator->hasMorePages(),
        ]);
    }

    /** GET /api/people/filters — countries / age range options for the filter UI */
    public function filters(): JsonResponse
    {
        $countries = User::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        $ageMin = (int) (User::min('age') ?? 13);
        $ageMax = (int) (User::max('age') ?? 99);

        return response()->json([
            'countries' => $countries,
            'genders'   => ['male', 'female', 'other'],
            'age_min'   => $ageMin,
            'age_max'   => $ageMax,
        ]);
    }

    private function baseQueryForSection(string $section, int $viewerId): Builder
    {
        switch ($section) {
            case 'following':
                return User::query()
                    ->join('follows', function ($j) use ($viewerId) {
                        $j->on('follows.following_id', '=', 'users.id')
                          ->where('follows.follower_id', '=', $viewerId);
                    });

            case 'followers':
                return User::query()
                    ->join('follows', function ($j) use ($viewerId) {
                        $j->on('follows.follower_id', '=', 'users.id')
                          ->where('follows.following_id', '=', $viewerId);
                    });

            case 'visitors':
                return User::query()
                    ->join('user_notifications', function ($j) use ($viewerId) {
                        $j->on('user_notifications.actor_id', '=', 'users.id')
                          ->where('user_notifications.user_id', '=', $viewerId)
                          ->where('user_notifications.type', '=', 'profile_visit');
                    })
                    ->groupBy(
                        'users.id', 'users.name', 'users.username',
                        'users.profile_picture', 'users.banner_image',
                        'users.country', 'users.gender', 'users.age',
                        'users.created_at'
                    );

            case 'discover':
            default:
                return User::query()->where('users.id', '!=', $viewerId);
        }
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('users.name', 'like', $like)
                  ->orWhere('users.username', 'like', $like);
            });
        }

        $country = trim((string) $request->query('country', ''));
        if ($country !== '' && $country !== 'all') {
            $query->where('users.country', $country);
        }

        $gender = trim((string) $request->query('gender', ''));
        if ($gender !== '' && $gender !== 'all') {
            $query->where('users.gender', $gender);
        }

        $ageMin = $request->query('age_min');
        if ($ageMin !== null && $ageMin !== '') {
            $query->where('users.age', '>=', (int) $ageMin);
        }

        $ageMax = $request->query('age_max');
        if ($ageMax !== null && $ageMax !== '') {
            $query->where('users.age', '<=', (int) $ageMax);
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort, string $section, int $viewerId): Builder
    {
        switch ($sort) {
            case 'recent':
                if ($section === 'visitors') {
                    $query->orderByDesc(DB::raw('MAX(user_notifications.created_at)'));
                } elseif ($section === 'following' || $section === 'followers') {
                    $query->orderByDesc('follows.created_at');
                } else {
                    $query->orderByDesc('users.created_at');
                }
                break;
            case 'newest':
                $query->orderByDesc('users.created_at');
                break;
            case 'oldest':
                $query->orderBy('users.created_at');
                break;
            case 'age_desc':
                $query->orderByDesc('users.age');
                break;
            case 'age_asc':
                $query->orderBy('users.age');
                break;
            case 'name':
            default:
                $query->orderBy('users.name');
                break;
        }
        return $query;
    }
}
