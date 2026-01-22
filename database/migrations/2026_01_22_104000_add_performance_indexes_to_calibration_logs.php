<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 🚀 เพิ่ม Indexes สำหรับ Dashboard Widgets โดยเฉพาะสำหรับ cal_place filter
     */
    public function up(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            // Index สำหรับการกรอง Cal Place ทั่วไป
            $table->index('cal_place', 'idx_cl_cal_place');

            // Composite Index สำหรับ OverdueWidget + Cal Place (next_cal_date + cal_place)
            $table->index(['next_cal_date', 'cal_place'], 'idx_cl_next_cal_date_place');

            // Composite Index สำหรับ CalibratedWidget + Cal Place (cal_date + cal_place)
            $table->index(['cal_date', 'cal_place'], 'idx_cl_cal_date_place');
            
             // Composite Index สำหรับ StatsWidget + Level + Cal Place
            $table->index(['next_cal_date', 'cal_level', 'cal_place'], 'idx_cl_next_cal_level_place');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            $table->dropIndex('idx_cl_cal_place');
            $table->dropIndex('idx_cl_next_cal_date_place');
            $table->dropIndex('idx_cl_cal_date_place');
            $table->dropIndex('idx_cl_next_cal_level_place');
        });
    }
};
