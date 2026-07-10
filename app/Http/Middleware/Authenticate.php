<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * There is no route named `login` — signing in happens through a form on the
     * home page (the only named login route is the POST `auth.login` handler), so
     * route('login') threw and turned every guest hit on an `auth` route into a
     * 500. Home is also where EnsureAdmin sends unauthenticated visitors.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('home');
    }
}
