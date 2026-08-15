<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // appointments.service_id used to reference the (now retired)
        // services table. The column name and Appointment::service()
        // relation stay the same — only the FK target changes to the
        // generalized catalog_items table.
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('catalog_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });
    }
};
