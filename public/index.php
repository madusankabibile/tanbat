<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Sub-directory deployment fix (XAMPP, etc.)
|--------------------------------------------------------------------------
|
| When the app is served from a sub-directory via a parent .htaccess that
| internally rewrites `/foo` to `/foo/public/`, Apache sets:
|   REQUEST_URI = /foo/bar
|   SCRIPT_NAME = /foo/public/index.php
|
| Symfony's Request can't reconcile that mismatch and ends up exposing
| `foo/bar` as Request::path(), which makes routes never match. Strip the
| `/public` segment from SCRIPT_NAME so the base URL detection works.
|
*/
foreach (['SCRIPT_NAME', 'PHP_SELF'] as $k) {
    if (isset($_SERVER[$k]) && str_contains($_SERVER[$k], '/public/')) {
        $_SERVER[$k] = str_replace('/public/', '/', $_SERVER[$k]);
    }
}

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
