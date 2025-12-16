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

            $table->json('environment')->nullable();  // 🔥 เก็บ Temp/Humidity เป็น JSON: {"temperature":"25","humidity":"60"}
            $table->string('result_status')->nullable(); // Pass / Fail
            $table->string('cal_level')->nullable();     // 🔥 ระดับ A / B / C
            $table->text('remark')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('calibration_logs');
    }
};