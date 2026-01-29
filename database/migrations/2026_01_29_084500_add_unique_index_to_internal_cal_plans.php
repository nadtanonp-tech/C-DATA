<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('internal_cal_plans', function (Blueprint $table) {
            // เพิ่ม Unique Index เพื่อรองรับ Upsert
            $table->unique(['plan_month', 'instrument_id'], 'internal_cal_plans_unique_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internal_cal_plans', function (Blueprint $table) {
            $table->dropUnique('internal_cal_plans_unique_key');
        });
    }
};
