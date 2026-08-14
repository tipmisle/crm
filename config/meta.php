<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta (Facebook / Instagram) App Credentials
    |--------------------------------------------------------------------------
    |
    | Created in the Meta Developer Dashboard (developers.facebook.com).
    | Never commit real values — configure these via .env only.
    |
    */

    'app_id' => env('META_APP_ID'),

    'app_secret' => env('META_APP_SECRET'),

    'redirect_uri' => env('META_REDIRECT_URI'),

    'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),

    'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Scopes
    |--------------------------------------------------------------------------
    |
    | Requested via Facebook Login for Business. This single flow grants
    | access to both Facebook Pages and any Instagram Professional account
    | linked to those Pages.
    |
    */

    'scopes' => [
        'pages_show_list',
        'pages_messaging',
        'pages_manage_metadata',
        'pages_read_engagement',
        'instagram_basic',
        'instagram_manage_messages',
        'business_management',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook fields subscribed per surface
    |--------------------------------------------------------------------------
    */

    'webhook_fields' => [
        'page' => ['messages', 'messaging_postbacks'],
        'instagram' => ['messages'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Messaging window
    |--------------------------------------------------------------------------
    |
    | Meta only allows businesses to freely reply within this window after
    | the customer's last message (standard messaging policy). Outside of
    | it, the reply box is disabled in the UI.
    |
    */

    'messaging_window_hours' => 24,

];
