<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
    {
         public function up(): void
             {
                Schema::create('borrows', function (Blueprint $table) {
                $table->id();
            
                 // เชื่อมกับตารางเครื่องมือ
                 $table->foreignId('instrument_id')->constrained('instruments')->onDelete('cascade');
            
                 // ข้อมูลผู้ยืม (ใส่ nullable เพื่อรองรับข้อมูลเก่าที่ไม่มีรหัส)
                 $table->string('emp_id')->nullable();        
                 $table->string('emp_name')->nullable(); 
                 $table->string('emp_dept')->nullable(); 
            
                 // วันที่
                 $table->date('borrow_date')->nullable();     
                 $table->date('due_date')->nullable();        
                 $table->date('returned_date')->nullable(); 
            
                 // อื่นๆ
                 $table->string('doc_file')->nullable(); 
                 $table->string('status')->default('Borrowed');
                 $table->text('remark')->nullable();
            
                 $table->timestamps();
                });
           }

    /**
     * Reverse the migrations.
     */
        public function down(): void
            {
                Schema::dropIfExists('borrows');
            }
    };