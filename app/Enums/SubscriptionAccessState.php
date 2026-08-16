<?php

namespace App\Enums;

/**
 * Beležka's access state for a workspace's subscription — a richer model
 * than a single boolean. Server logic (App\Services\Billing\WorkspaceSubscriptionStateService)
 * always reasons in these terms; the UI's Slovenian copy comes from
 * label(), never a raw Stripe status string. See docs/billing.md.
 */
enum SubscriptionAccessState: string
{
    case Active = 'active';
    case Incomplete = 'incomplete';
    case PastDue = 'past_due';
    case Canceling = 'canceling';
    case Canceled = 'canceled';
    case NoSubscription = 'no_subscription';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Beležka je aktivna.',
            self::Canceling => 'Naročnina bo potekla.',
            self::PastDue => 'Plačila ni bilo mogoče izvesti. Posodobi način plačila.',
            self::Incomplete => 'Naročnina ni bila dokončana.',
            self::Canceled, self::NoSubscription => 'Za uporabo Beležke aktiviraj naročnino.',
        };
    }
}
