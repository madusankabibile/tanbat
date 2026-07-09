<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'babyboss' => [
        'username'         => env('BABYBOSS_USERNAME', 'babyboss'),
        'search_urls_path' => env('BABYBOSS_URLS_PATH', base_path('urls.txt')),
        'shoutout_handle'  => env('BABYBOSS_SHOUTOUT_HANDLE', 'tanbat'),
    ],

    // Groq — OpenAI-compatible LLM API. Used to synthesise blog articles for old
    // WoWonder /read-blog/{id}_{slug}.html URLs (see App\Services\ArticleGenerator).
    'groq' => [
        'key'      => env('GROQ_API_KEY'),
        'base_url' => rtrim(env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),
        'model'    => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'timeout'  => (int) env('GROQ_TIMEOUT', 25),
    ],

];
