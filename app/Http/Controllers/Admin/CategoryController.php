<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = Category::withCount('posts');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like));
        }

        $categories = $query->orderBy('name')->paginate(30)->withQueryString();
        return view('admin.categories.index', compact('categories', 'q'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')],
            'slug' => ['nullable', 'string', 'max:80', Rule::unique('categories', 'slug')],
        ]);

        $data['slug'] = $data['slug'] ?: $this->uniqueSlug($data['name']);

        Category::create($data);

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')->ignore($category->id)],
            'slug' => ['nullable', 'string', 'max:80', Rule::unique('categories', 'slug')->ignore($category->id)],
        ]);

        $data['slug'] = $data['slug'] ?: $this->uniqueSlug($data['name'], $category->id);
        $category->update($data);

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'cat';
        $slug = $base;
        $i = 2;
        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
