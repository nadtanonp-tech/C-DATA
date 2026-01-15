<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 🚀 เพิ่ม indexes เพื่อเพิ่มความเร็วในการ query
     */
    public function up(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            // Index สำหรับ filter ตาม cal_date (ใช้บ่อยใน CalibratedThisMonthWidget)
            $table->index('cal_date', 'idx_calibration_logs_cal_date');
            
            // Index สำหรับ filter ตาม next_cal_date (ใช้บ่อยใน DueThisMonthWidget, OverdueInstrumentsWidget)
            $table->index('next_cal_date', 'idx_calibration_logs_next_cal_date');
            
            // Composite index สำหรับการหา record ล่าสุดของแต่ละ instrument
            $table->index(['instrument_id', 'cal_date'], 'idx_calibration_logs_instrument_cal_date');
            
            // Index สำหรับ calibration_type filter
            $table->index('calibration_type', 'idx_calibration_logs_calibration_type');
            
            // Index สำหรับ cal_level filter
            $table->index('cal_level', 'idx_calibration_logs_cal_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            $table->dropIndex('idx_calibration_logs_cal_date');
            $table->dropIndex('idx_calibration_logs_next_cal_date');
            $table->dropIndex('idx_calibration_logs_instrument_cal_date');
            $table->dropIndex('idx_calibration_logs_calibration_type');
            $table->dropIndex('idx_calibration_logs_cal_level');
        });
    }
};
