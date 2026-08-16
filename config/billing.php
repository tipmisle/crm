<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription name
    |--------------------------------------------------------------------------
    |
    | Cashier's subscription "type" string — one workspace has at most one
    | subscription under this name. Not user-facing.
    */
    'subscription_name' => env('BILLING_SUBSCRIPTION_NAME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Monthly price
    |--------------------------------------------------------------------------
    |
    | The single Stripe Price ID this app subscribes a workspace to. Never
    | hardcoded in a controller or Vue component, never read from a client
    | request — see App\Http\Controllers\Billing\ActivationController.
    */
    'monthly_price_id' => env('STRIPE_BELEZKA_MONTHLY_PRICE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('BILLING_CURRENCY', 'eur'),

    /*
    |--------------------------------------------------------------------------
    | Display price / billing period
    |--------------------------------------------------------------------------
    |
    | Purely cosmetic — shown on the activation and billing settings pages.
    | Null means the UI omits the price line entirely rather than showing a
    | placeholder or invented figure. See NEEDS OWNER INPUT in docs/billing.md.
    */
    'display_price' => env('BILLING_DISPLAY_PRICE'),
    'billing_period_label' => env('BILLING_PERIOD_LABEL', 'mesečno'),

    /*
    |--------------------------------------------------------------------------
    | Access policies
    |--------------------------------------------------------------------------
    |
    | Neither policy is finalized product/commercial decisions — see NEEDS
    | OWNER INPUT in docs/billing.md. Do not invent a grace period; 'blocked'
    | is the conservative default until an explicit decision is made.
    |
    | past_due_access_policy: 'blocked' | 'grace'
    |   Whether a workspace with a past-due invoice keeps app access.
    |
    | post_subscription_access_policy: 'blocked'
    |   Access once a subscription has fully ended/been canceled. Kept
    |   configurable for future policy changes, but 'blocked' is the only
    |   supported value today — this is NOT a route back to data deletion,
    |   see docs/data-lifecycle.md and Part 9 of docs/billing.md.
    */
    'past_due_access_policy' => env('BILLING_PAST_DUE_ACCESS_POLICY', 'blocked'),
    'post_subscription_access_policy' => env('BILLING_POST_SUBSCRIPTION_ACCESS_POLICY', 'blocked'),

];
