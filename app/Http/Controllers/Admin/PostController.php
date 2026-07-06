<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $q          = trim((string) $request->query('q', ''));
        $type       = $request->query('type');
        $categoryId = $request->query('category');
        $sort       = $request->query('sort', 'newest');

        $query = Post::with('user:id,name,username,profile_picture', 'category:id,name');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('title', 'like', $like)
                  ->orWhere('status_text', 'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhere('short_description', 'like', $like);
            });
        }
        if (in_array($type, ['status', 'image', 'video', 'article'], true)) {
            $query->where('type', $type);
        }
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $query = match ($sort) {
            'oldest'      => $query->oldest(),
            'most_liked'  => $query->orderByDesc('likes_count'),
            'most_viewed' => $query->orderByDesc('views_count'),
            default       => $query->latest(),
        };

        $posts      = $query->paginate(20)->withQueryString();
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('admin.posts.index', compact('posts', 'categories', 'q', 'type', 'categoryId', 'sort'));
    }

    public function show(Post $post)
    {
        $post->load('user:id,name,username,profile_picture', 'category:id,name', 'media', 'comments.user:id,name,username,profile_picture');
        return view('admin.posts.show', compact('post'));
    }

    public function edit(Post $post)
    {
        $post->load('category:id,name');
        $categories = Category::orderBy('name')->get(['id', 'name']);
        return view('admin.posts.edit', compact('post', 'categories'));
    }

    public function update(Request $request, Post $post)
    {
        $data = $request->validate([
            'title'             => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:800'],
            'body'              => ['nullable', 'string'],
            'status_text'       => ['nullable', 'string', 'max:5000'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'category_id'       => ['nullable', Rule::exists('categories', 'id')],
            'is_adult'          => ['nullable', 'boolean'],
        ]);

        $data['is_adult'] = (bool) ($data['is_adult'] ?? false);
        $post->update($data);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post updated.');
    }

    public function destroy(Post $post)
    {
        $post->delete();
        return redirect()->route('admin.posts.index')->with('status', 'Post deleted.');
    }
}
