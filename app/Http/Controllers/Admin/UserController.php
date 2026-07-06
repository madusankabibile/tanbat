<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q     = trim((string) $request->query('q', ''));
        $role  = $request->query('role'); // null | 'user' | 'admin'
        $sort  = $request->query('sort', 'newest');

        $query = User::withCount(['posts', 'comments']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $like = '%' . $q . '%';
                $w->where('name', 'like', $like)
                  ->orWhere('username', 'like', $like)
                  ->orWhere('email', 'like', $like);
            });
        }
        if (in_array($role, ['user', 'admin'], true)) {
            $query->where('role', $role);
        }

        $query = match ($sort) {
            'oldest'    => $query->oldest(),
            'most_posts'=> $query->orderByDesc('posts_count'),
            'az'        => $query->orderBy('name'),
            default     => $query->latest(),
        };

        $users = $query->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users', 'q', 'role', 'sort'));
    }

    public function edit(User $user)
    {
        $user->loadCount(['posts', 'comments']);
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'email'    => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'role'     => ['required', Rule::in(['user', 'admin'])],
            'country'  => ['nullable', 'string', 'max:5'],
            'gender'   => ['nullable', 'string', 'max:20'],
            'age'      => ['nullable', 'integer', 'min:0', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'max:120'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('status', 'You cannot delete your own account.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }
}
