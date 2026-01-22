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
        // Step 1: Add the price column
        Schema::table('calibration_logs', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('remark');
        });

        // Step 2: Backfill existing data from calibration_data.price
        DB::statement("
            UPDATE calibration_logs 
            SET price = CAST(calibration_data->>'price' AS DECIMAL(10,2))
            WHERE calibration_data->>'price' IS NOT NULL 
            AND calibration_data->>'price' != ''
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
