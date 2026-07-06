<?php

namespace App\Http\Controllers;

use App\Models\SaveCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveCategoryController extends Controller
{
    /** GET /api/save-categories — list current user's folders with saved-post counts. */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $categories = SaveCategory::where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $countsByCategory = DB::table('post_saves')
            ->where('user_id', $userId)
            ->select('save_category_id', DB::raw('count(*) as c'))
            ->groupBy('save_category_id')
            ->pluck('c', 'save_category_id');

        $totalSaved = (int) $countsByCategory->sum();
        $uncategorized = (int) ($countsByCategory[''] ?? $countsByCategory[null] ?? 0);

        $folders = $categories->map(fn ($c) => [
            'id'    => $c->id,
            'name'  => $c->name,
            'slug'  => $c->slug,
            'count' => (int) ($countsByCategory[$c->id] ?? 0),
        ])->values();

        return response()->json([
            'data'          => $folders,
            'total'         => $totalSaved,
            'uncategorized' => $uncategorized,
        ]);
    }

    /** POST /api/save-categories  body: { name } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:60',
        ]);
        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['message' => 'Folder name is required.'], 422);
        }

        $userId = Auth::id();
        $slug = SaveCategory::uniqueSlugFor($userId, $name);

        $cat = SaveCategory::create([
            'user_id' => $userId,
            'name'    => $name,
            'slug'    => $slug,
        ]);

        return response()->json([
            'success'  => true,
            'category' => ['id' => $cat->id, 'name' => $cat->name, 'slug' => $cat->slug, 'count' => 0],
        ], 201);
    }

    /** PATCH /api/save-categories/{category}  body: { name } */
    public function update(Request $request, SaveCategory $category): JsonResponse
    {
        abort_unless($category->user_id === Auth::id(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:60',
        ]);
        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['message' => 'Folder name is required.'], 422);
        }

        $category->update([
            'name' => $name,
            'slug' => SaveCategory::uniqueSlugFor($category->user_id, $name, $category->id),
        ]);

        return response()->json([
            'success'  => true,
            'category' => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug],
        ]);
    }

    /** DELETE /api/save-categories/{category} — saves inside fall back to Uncategorized (null FK). */
    public function destroy(SaveCategory $category): JsonResponse
    {
        abort_unless($category->user_id === Auth::id(), 403);
        $category->delete(); // post_saves.save_category_id is set to NULL by FK rule
        return response()->json(['success' => true]);
    }
}
