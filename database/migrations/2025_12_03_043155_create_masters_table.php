<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('masters', function (Blueprint $table) {
            $table->id();
            $table->string('master_code')->unique(); // Legacy: CodeNoMaster
            $table->string('name');
            $table->string('size')->nullable();
            $table->string('serial_no')->nullable();
            
            $table->date('last_cal_date')->nullable(); // Legacy: UpdateDate
            $table->date('due_date')->nullable();      // วันที่หมดอายุสอบเทียบ
            $table->string('cal_place')->nullable();   // Legacy: PlaceCALNow
            
            $table->string('certificate_no')->nullable(); // Legacy: CerNo
            $table->string('trace_file')->nullable();     // Legacy: Tracability (เก็บ path ไฟล์)
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('masters');
    }
};