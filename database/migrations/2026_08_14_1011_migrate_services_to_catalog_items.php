<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve existing Service records (and their ids, so the
        // appointments.service_id foreign key keeps pointing at the right
        // row once it's repointed at catalog_items) by copying them into
        // the new generalized catalog rather than dropping the data.
        DB::table('services')->orderBy('id')->each(function ($service) {
            DB::table('catalog_items')->insert([
                'id' => $service->id,
                'workspace_id' => $service->workspace_id,
                'type' => 'service',
                'name' => $service->name,
                'description' => $service->description,
                'default_duration_minutes' => $service->default_duration_minutes,
                'default_price' => $service->default_price,
                'default_deposit_amount' => $service->default_deposit_amount,
                'active' => $service->active,
                'created_at' => $service->created_at,
                'updated_at' => $service->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('catalog_items')->where('type', 'service')->delete();
    }
};
