<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | The key is the locale segment used in public URLs (/ar/..., /en/...) and
    | the value describes how pages built in that locale must be rendered.
    |
    */

    'locales' => [
        'ar' => [
            'name' => 'العربية',
            'direction' => 'rtl',
            'html_lang' => 'ar',
        ],
        'en' => [
            'name' => 'English',
            'direction' => 'ltr',
            'html_lang' => 'en',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storefront listing sizes
    |--------------------------------------------------------------------------
    */

    'per_page' => [
        'category_products' => 12,
        'search_results' => 12,
    ],

    'home' => [
        'featured_limit' => 8,
        'latest_limit' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image uploads
    |--------------------------------------------------------------------------
    |
    | SVG is deliberately absent: it can carry scripts and is served from the
    | same origin as the site. Sizes are in kilobytes.
    |
    */

    'images' => [
        'disk' => 'public',
        'max_kilobytes' => 4096,
        'min_dimension' => 120,
        'max_dimension' => 4000,
        'accepted_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'accepted_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'directories' => [
            'categories' => 'categories',
            'products' => 'products',
            'site' => 'site',
        ],
    ],

];
