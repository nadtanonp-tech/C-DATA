<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::create('monthly_plans', function (Blueprint $table) {
            $table->id();
            
            $table->date('plan_month'); 
            
            // 🔴 เปลี่ยนจากเก็บ String ธรรมดา เป็น Foreign Key เชื่อมกับ tool_types
            $table->foreignId('tool_type_id')
                  ->nullable()
                  ->constrained('tool_types')
                  ->onDelete('set null'); // ถ้า Type ถูกลบ ให้ข้อมูลนี้ยังอยู่แต่เป็น Null
            
            // เก็บ code_type เดิมไว้กันเหนียว (เผื่อหา ID ไม่เจอ)
            $table->string('code_type_legacy')->nullable(); 
            
            $table->string('department')->nullable(); 
            $table->string('status')->nullable(); 
            
            // ยอดรวม
            $table->integer('plan_count')->default(0); 
            $table->integer('cal_count')->default(0);  
            $table->integer('remain_count')->default(0); 
            
            // Level
            $table->integer('level_a')->default(0); 
            $table->integer('level_b')->default(0); 
            $table->integer('level_c')->default(0); 
            
            $table->text('remark')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('monthly_plans');
    }
};