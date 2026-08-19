<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\TvChannel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin management for TV posts (posts of type "tv").
 *
 * Same shape as Admin\BookController: each row is a TvChannel joined to its
 * parent Post, and deleting the channel deletes the post so it disappears
 * site-wide (tv_channels cascades off the FK).
 *
 * A channel's m3u8 is write-only from the operator's point of view — it is
 * stored here and never rendered on the public page; see TvStreamController.
 */
class TvController extends Controller
{
    public function index(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $status = $request->query('status'); // live | offline

        $query = TvChannel::query()->with('post:id,user_id,created_at');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like));
        }

        $query = match ($status) {
            'live'    => $query->where('is_active', true),
            'offline' => $query->where('is_active', false),
            default   => $query,
        };

        $channels = $query->latest()->paginate(20)->withQueryString();

        $totals = [
            'all'     => TvChannel::count(),
            'live'    => TvChannel::where('is_active', true)->count(),
            'offline' => TvChannel::where('is_active', false)->count(),
            'views'   => (int) TvChannel::sum('views'),
        ];

        return view('admin.tv.index', compact('channels', 'q', 'status', 'totals'));
    }

    public function create()
    {
        return view('admin.tv.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data) {
            $post = Post::create([
                'user_id'     => $this->anonymousUserId(),
                'type'        => 'tv',
                'title'       => $data['name'],
                'description' => null,
            ]);

            TvChannel::create([
                'post_id'     => $post->id,
                'name'        => $data['name'],
                'slug'        => $this->makeUniqueSlug($data['name']),
                'logo'        => $this->resolveLogo($request),
                'description' => $data['description'] ?? null,
                'stream_url'  => $data['stream_url'],
                'referer'     => $data['referer']    ?? null,
                'user_agent'  => $data['user_agent'] ?? null,
                'is_active'   => $request->boolean('is_active', true),
            ]);
        });

        return redirect()
            ->route('admin.tv.index')
            ->with('status', "TV channel \"{$data['name']}\" added.");
    }

    public function edit(TvChannel $tv)
    {
        return view('admin.tv.edit', ['channel' => $tv]);
    }

    public function update(Request $request, TvChannel $tv)
    {
        $data = $this->validated($request, $tv);

        $logo = $this->resolveLogo($request, $tv);

        $tv->update([
            'name'        => $data['name'],
            // Renaming re-slugs, but only when the admin didn't pin a slug —
            // an existing /tv/{slug} may already be linked from elsewhere.
            'slug'        => $data['slug'] !== '' ? $data['slug'] : $tv->slug,
            'logo'        => $logo,
            'description' => $data['description'] ?? null,
            'stream_url'  => $data['stream_url'],
            'referer'     => $data['referer']    ?? null,
            'user_agent'  => $data['user_agent'] ?? null,
            'is_active'   => $request->boolean('is_active'),
        ]);

        // Keep the parent post's title in step so admin post lists and search
        // don't show a stale name.
        $tv->post?->update(['title' => $data['name']]);

        return redirect()
            ->route('admin.tv.index')
            ->with('status', "TV channel \"{$tv->name}\" updated.");
    }

    /** Flip a channel between live and offline without deleting it. */
    public function toggle(TvChannel $tv)
    {
        $tv->update(['is_active' => !$tv->is_active]);

        return back()->with('status', $tv->is_active
            ? "\"{$tv->name}\" is live."
            : "\"{$tv->name}\" is now offline.");
    }

    public function destroy(TvChannel $tv)
    {
        $name = $tv->name;

        $this->deleteUploadedLogo($tv->logo);

        if ($tv->post) {
            $tv->post->delete(); // cascades to tv_channels
        } else {
            $tv->delete();
        }

        return redirect()
            ->route('admin.tv.index')
            ->with('status', "TV channel \"{$name}\" deleted.");
    }

    /**
     * Shared validation. On update, the slug's uniqueness check has to ignore
     * the row being edited.
     */
    private function validated(Request $request, ?TvChannel $existing = null): array
    {
        $slugRule = Rule::unique('tv_channels', 'slug');
        if ($existing) {
            $slugRule = $slugRule->ignore($existing->id);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugRule],
            'description' => ['nullable', 'string', 'max:20000'],
            // Accept .m3u8 and .m3u alike; some providers serve a query-string
            // manifest with no extension at all, so only the scheme is required.
            'stream_url'  => ['required', 'string', 'max:2048', 'regex:~^https?://~i'],
            'referer'     => ['nullable', 'string', 'max:1024', 'regex:~^https?://~i'],
            'user_agent'  => ['nullable', 'string', 'max:512'],
            'logo_file'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:2048'],
            'logo_url'    => ['nullable', 'url', 'max:1024'],
        ], [
            'stream_url.regex' => 'The stream link must start with http:// or https://.',
            'slug.regex'       => 'The URL slug may only contain lowercase letters, numbers and single hyphens.',
        ]);

        $data['slug'] = trim((string) ($data['slug'] ?? ''));

        return $data;
    }

    /**
     * Decide what goes in tv_channels.logo: a newly uploaded file wins, then a
     * pasted URL, then whatever the row already had. An upload that replaces a
     * previously uploaded file cleans the old one up.
     */
    private function resolveLogo(Request $request, ?TvChannel $existing = null): ?string
    {
        if ($request->hasFile('logo_file')) {
            $this->deleteUploadedLogo($existing?->logo);
            return $request->file('logo_file')->store('tv/logos', 'public');
        }

        $url = trim((string) $request->input('logo_url', ''));
        if ($url !== '') {
            $this->deleteUploadedLogo($existing?->logo);
            return $url;
        }

        return $existing?->logo;
    }

    /** Remove a stored logo, ignoring absolute URLs (not ours to delete). */
    private function deleteUploadedLogo(?string $logo): void
    {
        if ($logo && !preg_match('~^https?://~i', $logo)) {
            Storage::disk('public')->delete($logo);
        }
    }

    /**
     * TV posts are attributed to the shared anonymous system account, exactly
     * like admin-added books. Mirrors Admin\BookController::anonymousUserId().
     */
    private function anonymousUserId(): int
    {
        return User::firstOrCreate(
            ['username' => 'anonymous'],
            [
                'name'     => 'Anonymous',
                'age'      => 18,
                'gender'   => 'other',
                'country'  => 'US',
                'email'    => 'anonymous@tanbat.local',
                'password' => bcrypt(Str::random(40)),
                'role'     => 'user',
            ]
        )->id;
    }

    /** Slugified name, with a short random suffix only if it's already taken. */
    private function makeUniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'channel', 60, '');

        if (!TvChannel::where('slug', $base)->exists()) {
            return $base;
        }
        return $base . '-' . Str::lower(Str::random(4));
    }
}
