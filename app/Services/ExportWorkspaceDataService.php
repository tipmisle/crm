<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\Message;
use App\Models\SalesDocument;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceExport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Builds a downloadable ZIP of everything a workspace owner is entitled
 * to for their own workspace's data — read through Eloquent so encrypted
 * fields (messages.body, customers.notes, orders/appointments notes, ...)
 * decrypt automatically. Never touches integration/channel access tokens,
 * password hashes, or session data. Written directly to the private
 * 'local' disk under a random filename — never public storage. Runs
 * synchronously (matches the app's existing synchronous cleanup-command
 * pattern; there is no queue worker infra relied on elsewhere yet).
 *
 * See docs/data-lifecycle.md Part 9-12.
 */
class ExportWorkspaceDataService
{
    public function build(Workspace $workspace, User $requestedBy): WorkspaceExport
    {
        $tmpDir = sys_get_temp_dir().'/workspace-export-'.Str::uuid();
        mkdir($tmpDir, 0700, true);

        try {
            $this->writeJson($tmpDir.'/workspace.json', [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'email' => $workspace->email,
                'timezone' => $workspace->timezone,
                'currency' => $workspace->currency,
                'created_at' => $workspace->created_at?->toIso8601String(),
            ]);

            $this->writeCsv($tmpDir.'/customers.csv', $workspace->customers()->get(), function ($c) {
                return [
                    'id' => $c->id,
                    'full_name' => $c->full_name,
                    'email' => $c->email,
                    'phone' => $c->phone,
                    'address_line' => $c->address_line,
                    'postal_code' => $c->postal_code,
                    'city' => $c->city,
                    'country' => $c->country,
                    'tax_number' => $c->tax_number,
                    'notes' => $c->notes,
                    'tags' => $c->tags ? implode(', ', $c->tags) : null,
                    'first_contacted_at' => $c->first_contacted_at?->toIso8601String(),
                    'last_interaction_at' => $c->last_interaction_at?->toIso8601String(),
                ];
            });

            $this->writeJson($tmpDir.'/conversations.json', $workspace->conversations()->get()->map(fn ($c) => [
                'id' => $c->id,
                'customer_id' => $c->customer_id,
                'channel_id' => $c->channel_id,
                'customer_display_name' => $c->customer_display_name,
                'status' => $c->status?->value,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ])->all());

            $conversationIds = $workspace->conversations()->pluck('id');

            $this->writeJson($tmpDir.'/messages.json', Message::whereIn('conversation_id', $conversationIds)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'conversation_id' => $m->conversation_id,
                    'sender_type' => $m->sender_type?->value,
                    'body' => $m->body,
                    // Attachment metadata (type/filename) is included for
                    // portability; the underlying file bytes are not
                    // bundled into this export.
                    'attachments' => collect($m->metadata['attachments'] ?? [])
                        ->map(fn ($a) => ['type' => $a['type'] ?? null, 'source' => $a['source'] ?? null])
                        ->all(),
                    'sent_at' => $m->sent_at?->toIso8601String(),
                ])->all());

            $this->writeCsv($tmpDir.'/orders.csv', $workspace->orders()->get(), function ($o) {
                return [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'customer_id' => $o->customer_id,
                    'title' => $o->title,
                    'description' => $o->description,
                    'due_date' => $o->due_date,
                    'price' => $o->price,
                    'deposit_amount' => $o->deposit_amount,
                    'amount_paid' => $o->amount_paid,
                    'payment_status' => $o->payment_status,
                    // Order.status is a plain string (per-workspace-
                    // configurable status key), not an enum — no ?->value.
                    'status' => $o->status,
                    'internal_notes' => $o->internal_notes,
                    'customer_notes' => $o->customer_notes,
                ];
            });

            $this->writeCsv($tmpDir.'/appointments.csv', $workspace->appointments()->get(), function ($a) {
                return [
                    'id' => $a->id,
                    'customer_id' => $a->customer_id,
                    'appointment_date' => $a->appointment_date,
                    'start_time' => $a->start_time,
                    'description' => $a->description,
                    'price' => $a->price,
                    'deposit_amount' => $a->deposit_amount,
                    'amount_paid' => $a->amount_paid,
                    'payment_status' => $a->payment_status,
                    'status' => $a->status?->value,
                    'internal_notes' => $a->internal_notes,
                    'customer_notes' => $a->customer_notes,
                ];
            });

            $this->writeCsv($tmpDir.'/follow-ups.csv', $workspace->followUps()->get(), function ($f) {
                return [
                    'id' => $f->id,
                    'followable_type' => $f->followable_type,
                    'followable_id' => $f->followable_id,
                    'note' => $f->note,
                    'due_at' => $f->due_at?->toIso8601String(),
                    'completed_at' => $f->completed_at?->toIso8601String(),
                ];
            });

            $this->writeCsv($tmpDir.'/catalog.csv', CatalogItem::withoutGlobalScopes()->where('workspace_id', $workspace->id)->get(), function ($c) {
                return [
                    'id' => $c->id,
                    'type' => $c->type,
                    'name' => $c->name,
                    'description' => $c->description,
                    'default_price' => $c->default_price,
                    'active' => $c->active ? 1 : 0,
                ];
            });

            // Issued financial documents belong to the workspace owner's
            // own data, not a secret/credential — the immutable snapshot
            // fields are read here, never altered.
            $this->writeCsv($tmpDir.'/sales-documents.csv', SalesDocument::where('workspace_id', $workspace->id)->get(), function ($d) {
                return [
                    'id' => $d->id,
                    'document_number' => $d->document_number,
                    'type' => $d->type,
                    'status' => $d->status,
                    'customer_id' => $d->customer_id,
                    'order_id' => $d->order_id,
                    'appointment_id' => $d->appointment_id,
                    'issued_at' => $d->issued_at?->toIso8601String(),
                    'due_date' => $d->due_date?->toIso8601String(),
                    'currency' => $d->currency,
                    'subtotal' => $d->subtotal,
                    'vat_total' => $d->vat_total,
                    'total' => $d->total,
                ];
            });

            $tmpZipPath = sys_get_temp_dir().'/'.Str::random(40).'.zip';

            $zip = new ZipArchive;
            $zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach (glob($tmpDir.'/*') as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            // Persisted through the Storage facade (private 'local' disk,
            // never public) rather than writing storage_path() directly, so
            // this is testable with Storage::fake('local') like the rest of
            // the app's file-handling code.
            $zipRelativePath = 'exports/'.Str::random(40).'.zip';
            Storage::disk('local')->put($zipRelativePath, file_get_contents($tmpZipPath));
            unlink($tmpZipPath);

            return WorkspaceExport::create([
                'workspace_id' => $workspace->id,
                'requested_by_user_id' => $requestedBy->id,
                'disk_path' => $zipRelativePath,
                'status' => 'ready',
                'expires_at' => now()->addHours((int) config('retention.export_hours')),
            ]);
        } finally {
            $this->rmdirRecursive($tmpDir);
        }
    }

    private function writeJson(string $path, mixed $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  iterable<mixed>  $items
     * @param  callable(mixed): array<string, mixed>  $mapper
     */
    private function writeCsv(string $path, iterable $items, callable $mapper): void
    {
        $handle = fopen($path, 'w');
        $headerWritten = false;

        foreach ($items as $item) {
            $row = $mapper($item);

            if (! $headerWritten) {
                fputcsv($handle, array_keys($row));
                $headerWritten = true;
            }

            fputcsv($handle, array_values($row));
        }

        // If there were no rows, the file is simply empty — no header to
        // derive without a sample row.
        fclose($handle);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') as $file) {
            unlink($file);
        }

        rmdir($dir);
    }
}
