<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dedupe record for Stripe webhook deliveries — see the migration and
 * App\Http\Controllers\Webhooks\StripeWebhookController. No payload
 * column, identifiers only.
 */
class StripeWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'stripe_event_id',
        'type',
    ];
}
