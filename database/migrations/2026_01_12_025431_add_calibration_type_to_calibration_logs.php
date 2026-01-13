<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            $table->string('calibration_type')->nullable()->after('cal_by')->index();
        });

        // Backfill existing data from JSON field
        DB::statement("
            UPDATE calibration_logs 
            SET calibration_type = calibration_data->>'calibration_type'
            WHERE calibration_data->>'calibration_type' IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            $table->dropColumn('calibration_type');
        });
    }
};
