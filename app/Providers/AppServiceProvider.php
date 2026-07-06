<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Force the URL generator to use APP_URL when the app is served from
        // a sub-directory (e.g. http://localhost/tanbat-x/). Without this,
        // url('/foo') uses the request host+scheme and drops the directory
        // prefix, breaking redirects like redirect()->route('home').
        if ($base = config('app.url')) {
            URL::forceRootUrl($base);
            if (str_starts_with($base, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
