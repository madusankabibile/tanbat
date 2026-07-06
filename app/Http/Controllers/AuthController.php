<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /** POST /auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = $request->input('identifier');
        $password   = $request->input('password');

        // Try email first, then username
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (!Auth::attempt([$field => $identifier, 'password' => $password])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email/username or password.',
            ], 401);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'user'    => $this->userPayload(Auth::user()),
        ]);
    }

    /** POST /auth/register */
    public function register(RegisterRequest $request): JsonResponse
    {
        $picPath = $request->file('profile_picture')
            ->store('avatars', 'public');

        $user = User::create([
            'name'            => $request->input('name'),
            'age'             => $request->input('age'),
            'gender'          => $request->input('gender'),
            'country'         => $request->input('country'),
            'email'           => $request->input('email'),
            'username'        => $request->input('username'),
            'password'        => $request->input('password'),
            'profile_picture' => $picPath,
            'role'            => 'user',
        ]);

        // Every new user auto-follows the bots so their feed isn't empty on day one.
        $botIds = User::whereIn('username', ['james_caldwell', 'lucas_carlisle', 'robert_sheffield', 'daniel_whitmore'])->pluck('id');
        if ($botIds->isNotEmpty()) {
            $user->following()->syncWithoutDetaching($botIds);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'user'    => $this->userPayload($user),
        ], 201);
    }

    /** POST /auth/logout */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    /** GET /auth/user */
    public function user(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['logged_in' => false]);
        }

        return response()->json([
            'logged_in' => true,
            'user'      => $this->userPayload(Auth::user()),
        ]);
    }

    /** Consistent user shape returned to the SPA */
    private function userPayload(User $user): array
    {
        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'username'        => $user->username,
            'email'           => $user->email,
            'role'            => $user->role,
            'profile_picture' => $user->avatarUrl(),
        ];
    }
}
