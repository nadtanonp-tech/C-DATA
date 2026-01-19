<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 🚀 สร้าง View สำหรับเก็บ record ล่าสุดของแต่ละ instrument
     * ใช้แทน whereNotExists ที่ช้ามาก
     */
    public function up(): void
    {
        // สร้าง View ที่เก็บเฉพาะ record ล่าสุดของแต่ละ instrument
        DB::statement("
            CREATE OR REPLACE VIEW latest_calibration_logs AS
            SELECT cl.*
            FROM calibration_logs cl
            INNER JOIN (
                SELECT instrument_id, MAX(cal_date) as max_cal_date
                FROM calibration_logs
                WHERE cal_date IS NOT NULL
                GROUP BY instrument_id
            ) latest ON cl.instrument_id = latest.instrument_id 
                     AND cl.cal_date = latest.max_cal_date
        ");
        
        // สร้าง index เพิ่มเติมเพื่อเพิ่มความเร็ว (ถ้ายังไม่มี)
        // Index นี้จะช่วยให้การหา MAX(cal_date) เร็วขึ้น
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS latest_calibration_logs");
    }
};
