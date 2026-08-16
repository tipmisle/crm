<?php

namespace App\Console\Commands;

use App\Models\WorkspaceExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeExpiredExports extends Command
{
    protected $signature = 'exports:purge-expired';

    protected $description = 'Delete workspace-data export files and records past their expiry.';

    public function handle(): void
    {
        $expired = WorkspaceExport::where('expires_at', '<=', now())->get();

        foreach ($expired as $export) {
            try {
                Storage::disk('local')->delete($export->disk_path);
            } catch (Throwable $e) {
                Log::warning('exports.purge.file_delete_failed', ['export_id' => $export->id]);
            }

            $export->delete();
        }

        $this->info("Purged {$expired->count()} expired export(s).");
    }
}
