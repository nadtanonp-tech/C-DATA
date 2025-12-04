<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('traceability_chains', function (Blueprint $table) {
            $table->id();

            // 1. เครื่องมือหลัก (ตัวลูก)
            $table->foreignId('master_id')
                  ->constrained('masters')
                  ->onDelete('cascade');

            // 2. เครื่องมืออ้างอิง (ตัวแม่)
            // (เราต้องระบุ table เอง เพราะชื่อ field ไม่ใช่ master_id)
            $table->foreignId('ref_master_id')
                  ->constrained('masters')
                  ->onDelete('cascade');

            // 3. (Optional) ระดับชั้นของการสอบเทียบ (ถ้าอยากเก็บ)
            // เช่น Level 1, Level 2 หรือลำดับที่ 1, 2
            $table->integer('level')->default(1);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('traceability_chains');
    }
};