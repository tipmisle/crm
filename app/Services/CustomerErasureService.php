<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderNote;
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
 *  - orders/appointments.customer_notes, .description, and
 *    .internal_notes are nulled, along with OrderNote.body for this
 *    customer's orders — all owner-written free text that can end up
 *    containing personal/sensitive details about the customer. The
 *    operational record itself (title, dates, amounts, status) is
 *    retained — deleting it would corrupt the business's own history for
 *    something that isn't strictly required to fulfil the erasure
 *    request.
 *  - Immutable SalesDocument snapshots (issued invoices/proformas/storno)
 *    are never touched here — they're a separate, retained legal record,
 *    not live Customer data. See App\Models\SalesDocument.
 *  - ActivityLog.description is free text that can interpolate the
 *    customer's full_name/company_name (e.g. "Naročilo ... ustvarjeno za
 *    X") — every occurrence of the customer's own (pre-erasure) name is
 *    replaced with the same "Izbrisana stranka" placeholder used
 *    elsewhere, across log entries whose subject is this Customer or one
 *    of their Orders/Appointments/Conversations. The append-only security
 *    AuditLog is a separate model with its own retention design and is
 *    never touched here.
 *
 * See docs/data-lifecycle.md Part 15-16.
 */
class CustomerErasureService
{
    public function erase(Customer $customer): void
    {
        $conversationIds = $customer->conversations()->pluck('id');
        $orderIds = $customer->orders()->pluck('id');
        $appointmentIds = $customer->appointments()->pluck('id');
        $originalFullName = $customer->full_name;
        $originalCompanyName = $customer->company_name;

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

        DB::transaction(function () use (
            $customer, $conversationIds, $orderIds, $appointmentIds, $originalFullName, $originalCompanyName,
        ) {
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

            $customer->orders()->update([
                'customer_notes' => null,
                'description' => null,
                'internal_notes' => null,
            ]);
            OrderNote::whereIn('order_id', $orderIds)->update(['body' => null]);

            $customer->appointments()->update([
                'customer_notes' => null,
                'description' => null,
                'internal_notes' => null,
            ]);

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

            $names = array_values(array_filter([$originalFullName, $originalCompanyName]));

            if ($names) {
                $subjectQuery = ActivityLog::where(function ($q) use ($customer, $orderIds, $appointmentIds, $conversationIds) {
                    $q->where(fn ($q2) => $q2->where('subject_type', Customer::class)->where('subject_id', $customer->id))
                        ->orWhere(fn ($q2) => $q2->where('subject_type', Order::class)->whereIn('subject_id', $orderIds))
                        ->orWhere(fn ($q2) => $q2->where('subject_type', Appointment::class)->whereIn('subject_id', $appointmentIds))
                        ->orWhere(fn ($q2) => $q2->where('subject_type', Conversation::class)->whereIn('subject_id', $conversationIds));
                });

                foreach ($subjectQuery->get(['id', 'description']) as $log) {
                    $anonymized = str_replace($names, 'Izbrisana stranka', $log->description);

                    if ($anonymized !== $log->description) {
                        ActivityLog::where('id', $log->id)->update(['description' => $anonymized]);
                    }
                }
            }
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
