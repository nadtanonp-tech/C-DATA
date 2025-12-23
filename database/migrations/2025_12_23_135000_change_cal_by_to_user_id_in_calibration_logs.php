<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * เปลี่ยน cal_by จาก string (ข้อมูลแผนก) เป็น foreignId ชี้ไป users table
     * เนื่องจากข้อมูลแผนกสามารถดึงจาก instrument->department ได้อยู่แล้ว
     */
    public function up(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            // ลบ column cal_by เดิม (ที่เป็น string เก็บข้อมูลแผนก)
            $table->dropColumn('cal_by');
        });

        Schema::table('calibration_logs', function (Blueprint $table) {
            // เพิ่ม cal_by ใหม่เป็น foreignId ชี้ไป users table (ผู้สอบเทียบ)
            $table->foreignId('cal_by')
                  ->nullable()
                  ->after('next_cal_date')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            $table->dropForeign(['cal_by']);
            $table->dropColumn('cal_by');
        });

        Schema::table('calibration_logs', function (Blueprint $table) {
            // กลับไปเป็น string เหมือนเดิม
            $table->string('cal_by')->nullable()->after('next_cal_date');
        });
    }
};
