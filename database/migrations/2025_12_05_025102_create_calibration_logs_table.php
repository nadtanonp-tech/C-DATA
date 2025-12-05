<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('calibration_logs', function (Blueprint $table) {
            $table->id();

            // เชื่อมกับเครื่องมือ
            $table->foreignId('instrument_id')->constrained('instruments')->onDelete('cascade');

            $table->date('cal_date')->nullable();       // วันที่สอบเทียบ
            $table->date('next_cal_date')->nullable();  // วันครบกำหนด

            $table->string('cal_by')->nullable();       // ผู้สอบเทียบ/Section (เช่น MC, Cal.Lab)
            $table->string('cal_place')->nullable();    // สถานที่ (Internal/External)

            // *** พระเอกของเรา: เก็บค่า Major1-1, Pitch1-1... ทั้งหมดลงในนี้ ***
            $table->json('calibration_data')->nullable(); 

            $table->string('environment')->nullable();  // เก็บ Temp/Humidity (เช่น "25C / 60%")
            $table->string('result_status')->nullable(); // Pass / Fail
            $table->text('remark')->nullable();
            // 🔴 เพิ่มบรรทัดนี้ครับ 🔴
            $table->string('grade_result')->nullable();
            // เก็บชื่อ Table เดิมไว้ดูเล่น (เผื่อ Trace กลับ)
            $table->string('legacy_source_table')->nullable(); 

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('calibration_logs');
    }
};