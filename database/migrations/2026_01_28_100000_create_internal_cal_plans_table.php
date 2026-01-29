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
        Schema::create('internal_cal_plans', function (Blueprint $table) {
            $table->id();
            $table->date('plan_month');                           // เดือนที่วางแผน
            $table->foreignId('instrument_id')->constrained()->onDelete('cascade'); // เครื่องมือ
            $table->foreignId('calibration_log_id')->nullable()->constrained('calibration_logs')->onDelete('set null'); // ประวัติสอบเทียบ (ถ้ามี)
            
            // ข้อมูลที่ Sync มา (เก็บเพื่อแสดงผลรวดเร็ว)
            $table->string('code_no')->nullable();                // Code No จาก instruments
            $table->string('tool_name')->nullable();              // Name จาก tool_types.name
            $table->string('tool_size')->nullable();              // Size จาก tool_types.size
            $table->string('serial_no')->nullable();              // Serial No จาก instruments.serial_no
            $table->date('cal_date')->nullable();                 // Cal Date จาก calibration_logs.cal_date
            $table->string('cal_level')->nullable();              // Lavel จาก calibration_logs.cal_level
            $table->text('remark')->nullable();                   // Remark จาก calibration_logs.remark
            $table->date('next_cal_date')->nullable();            // Next Cal จาก calibration_logs.next_cal_date
            
            $table->string('status')->default('Plan');            // สถานะ: Plan, Completed, Remain
            $table->string('department')->nullable();             // แผนก
            $table->string('calibration_type')->nullable();       // ประเภทการสอบเทียบ
            
            $table->timestamps();
            
            // Index สำหรับ Query เร็วขึ้น
            $table->index(['plan_month', 'department']);
            $table->index(['plan_month', 'calibration_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_cal_plans');
    }
};
