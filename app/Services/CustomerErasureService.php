<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Anonymizes a customer's data-subject-identifiable information in place.
 * The Customer row itself is never deleted — orders/appointments.customer_id
 * is a required, cascadeOnDelete FK, so deleting the row would destroy the
 * business owner's own operational records too. Instead:
 *  - Customer identifying + billing fields are replaced/nulled.
 *  - CustomerIdentity rows are deleted (nothing else FKs to their id).
 *  - Private conversation message bodies are nulled (thread structure and
 *    counts are preserved, content is gone); local attachment files on
 *    those messages are deleted after the DB commit succeeds.
 *  - follow_ups.note is nulled.
 *  - orders/appointments.customer_notes are nulled, but the operational
 *    record itself (title, dates, amounts, status) is retained — deleting
 *    it would corrupt the business's own history for something that isn't
 *    strictly required to fulfil the erasure request.
 *  - Immutable SalesDocument snapshots (issued invoices/proformas/storno)
 *    are never touched here — they're a separate, retained legal record,
 *    not live Customer data. See App\Models\SalesDocument.
 *
 * See docs/data-lifecycle.md Part 15-16.
 */
class CustomerErasureService
{
    public function erase(Customer $customer): void
    {
        $conversationIds = $customer->conversations()->pluck('id');

        // Collected before the transaction — file deletion isn't
        // transactional, so we only act on it once the DB rows are
        // confirmed gone.
        $attachmentPaths = Message::whereIn('conversation_id', $conversationIds)
            ->whereNotNull('metadata')
            ->get(['metadata'])
            ->flatMap(fn (Message $message) => $message->metadata['attachments'] ?? [])
            ->filter(fn (array $attachment) => ($attachment['source'] ?? null) === 'local' && ! empty($attachment['path']))
            ->pluck('path')
            ->all();

        DB::transaction(function () use ($customer, $conversationIds) {
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
                'address_line' => null,
                'postal_code' => null,
                'city' => null,
                'country' => null,
                'tax_number' => null,
                'company_name' => null,
                'is_business' => false,
                'vat_registered' => false,
            ]);
        });

        foreach ($attachmentPaths as $path) {
            try {
                Storage::disk('local')->delete($path);
            } catch (Throwable $e) {
                Log::warning('customer.erase.attachment_delete_failed', ['path_hash' => md5($path)]);
            }
        }
    }
}
