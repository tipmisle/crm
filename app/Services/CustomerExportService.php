<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Builds a small, on-the-fly ZIP scoped strictly to one Customer's own
 * data — for a workspace member responding to that specific customer's
 * data-subject request. Unlike ExportWorkspaceDataService this is not
 * persisted/trackable; it's a small, synchronous, request-then-download
 * action. See docs/data-lifecycle.md Part 13-16.
 */
class CustomerExportService
{
    public function build(Customer $customer): StreamedResponse
    {
        $customer->loadMissing([
            'identities',
            'conversations.messages',
            'orders.notes',
            'appointments',
            'followUps',
            'salesDocuments',
        ]);

        $data = [
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address_line' => $customer->address_line,
                'postal_code' => $customer->postal_code,
                'city' => $customer->city,
                'country' => $customer->country,
                'tax_number' => $customer->tax_number,
                'notes' => $customer->notes,
                'tags' => $customer->tags,
                'first_contacted_at' => $customer->first_contacted_at?->toIso8601String(),
                'last_interaction_at' => $customer->last_interaction_at?->toIso8601String(),
            ],
            'identities' => $customer->identities->map(fn ($i) => [
                'channel_type' => $i->channel_type?->value,
                'external_id' => $i->external_id,
                'username' => $i->username,
                'display_name' => $i->display_name,
            ])->all(),
            'conversations' => $customer->conversations->map(fn ($c) => [
                'id' => $c->id,
                'status' => $c->status?->value,
                'messages' => $c->messages->map(fn ($m) => [
                    'sender_type' => $m->sender_type?->value,
                    'body' => $m->body,
                    'sent_at' => $m->sent_at?->toIso8601String(),
                ])->all(),
            ])->all(),
            'orders' => $customer->orders->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'title' => $o->title,
                'description' => $o->description,
                'price' => $o->price,
                'deposit_amount' => $o->deposit_amount,
                'amount_paid' => $o->amount_paid,
                'payment_status' => $o->payment_status,
                // Order.status is a plain string (per-workspace-configurable
                // status key), not an enum — no ?->value here.
                'status' => $o->status,
                'customer_notes' => $o->customer_notes,
                'notes' => $o->notes->map(fn ($n) => ['body' => $n->body, 'created_at' => $n->created_at?->toIso8601String()])->all(),
            ])->all(),
            'appointments' => $customer->appointments->map(fn ($a) => [
                'id' => $a->id,
                'appointment_date' => $a->appointment_date,
                'description' => $a->description,
                'price' => $a->price,
                'deposit_amount' => $a->deposit_amount,
                'amount_paid' => $a->amount_paid,
                'payment_status' => $a->payment_status,
                'status' => $a->status?->value,
                'customer_notes' => $a->customer_notes,
            ])->all(),
            'follow_ups' => $customer->followUps->map(fn ($f) => [
                'note' => $f->note,
                'due_at' => $f->due_at?->toIso8601String(),
                'completed_at' => $f->completed_at?->toIso8601String(),
            ])->all(),
            // Issued financial documents this customer appears on — the
            // immutable snapshot fields belong to the transaction record,
            // not the live Customer, so they're read here, never altered.
            'sales_documents' => $customer->salesDocuments->map(fn ($d) => [
                'document_number' => $d->document_number,
                'type' => $d->type,
                'status' => $d->status,
                'issued_at' => $d->issued_at?->toIso8601String(),
                'due_date' => $d->due_date?->toIso8601String(),
                'currency' => $d->currency,
                'subtotal' => $d->subtotal,
                'vat_total' => $d->vat_total,
                'total' => $d->total,
                'customer_snapshot' => $d->customer_snapshot,
                'line_items_snapshot' => $d->line_items_snapshot,
            ])->all(),
        ];

        $tmpZip = tempnam(sys_get_temp_dir(), 'customer-export-').'.zip';

        $zip = new ZipArchive;
        $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('customer.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $filename = 'stranka-'.Str::slug($customer->full_name ?: 'izvoz').'.zip';

        return response()->streamDownload(function () use ($tmpZip) {
            echo file_get_contents($tmpZip);
            unlink($tmpZip);
        }, $filename);
    }
}
