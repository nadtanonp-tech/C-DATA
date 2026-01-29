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
        Schema::table('calibration_logs', function (Blueprint $table) {
            // Index เพื่อเพิ่มความเร็วในการหา Latest Log (MAX(cal_date) GROUP BY instrument_id)
            $table->index(['instrument_id', 'cal_date'], 'calibration_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            $table->dropIndex('calibration_date_index');
        });
    }
};
