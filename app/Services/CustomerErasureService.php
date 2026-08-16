<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

/**
 * Anonymizes a customer's data-subject-identifiable information in place.
 * The Customer row itself is never deleted — orders/appointments.customer_id
 * is a required, cascadeOnDelete FK, so deleting the row would destroy the
 * business owner's own operational records too. Instead:
 *  - Customer identifying fields are replaced/nulled.
 *  - CustomerIdentity rows are deleted (nothing else FKs to their id).
 *  - Private conversation message bodies are nulled (thread structure and
 *    counts are preserved, content is gone).
 *  - follow_ups.note is nulled.
 *  - orders/appointments.customer_notes are nulled, but the operational
 *    record itself (title, dates, amounts, status) is retained — deleting
 *    it would corrupt the business's own history for something that isn't
 *    strictly required to fulfil the erasure request.
 *
 * See docs/data-lifecycle.md Part 15-16.
 */
class CustomerErasureService
{
    public function erase(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $conversationIds = $customer->conversations()->pluck('id');

            Message::whereIn('conversation_id', $conversationIds)->update([
                'body' => null,
                'metadata' => null,
            ]);

            $customer->conversations()->update([
                'last_message_preview' => null,
                'customer_display_name' => 'Izbrisana stranka',
                'customer_username' => null,
            ]);

            $customer->identities()->delete();

            $customer->followUps()->update(['note' => null]);

            $customer->orders()->update(['customer_notes' => null]);
            $customer->appointments()->update(['customer_notes' => null]);

            $customer->update([
                'full_name' => 'Izbrisana stranka',
                'email' => null,
                'phone' => null,
                'notes' => null,
                'tags' => null,
            ]);
        });
    }
}
