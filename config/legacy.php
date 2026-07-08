<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy uploads base URL
    |--------------------------------------------------------------------------
    | Old Sngine article covers / inline images are stored as relative paths
    | such as "photos/2023/11/tanbat_xxx.jpg". They are served from the old
    | /content/uploads/ location on the live domain. LegacyArticle::coverUrl()
    | prefixes stored paths with this value.
    */
    'uploads_base' => rtrim(env('LEGACY_UPLOADS_BASE', 'https://tanbat.com/content/uploads'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Default author
    |--------------------------------------------------------------------------
    | When an old article's author cannot be matched to a migrated user
    | account (by username, then email), the import falls back to this user id.
    | Leave null to import such articles with no linked account (byline still
    | shows the old author_name text).
    */
    'default_author_id' => filled(env('LEGACY_DEFAULT_AUTHOR_ID'))
        ? (int) env('LEGACY_DEFAULT_AUTHOR_ID')
        : null,

];
