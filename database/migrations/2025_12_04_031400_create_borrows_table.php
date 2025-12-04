<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('borrows', function (Blueprint $table) {
            $table->id();
            
            // เชื่อมกับตารางเครื่องมือ (Instruments)
            // ถ้าเครื่องมือถูกลบ ประวัติการยืมจะยังอยู่ (onDelete set null) หรือจะลบตามก็ได้
            $table->foreignId('instrument_id')->constrained('instruments')->onDelete('cascade');
            
            // ข้อมูลผู้ยืม
            $table->string('emp_id')->nullable(); // IDEmp
            $table->string('emp_name')->nullable();  // Name (Snapshot)
            $table->string('emp_dept')->nullable();  // Section (Snapshot)
            
            // วันที่
            $table->date('borrow_date');     // DateBorrow
            $table->date('due_date');        // DueDate
            $table->date('returned_date')->nullable(); // DateSent (ถ้ายังไม่คืน = null)
            
            // ไฟล์แนบ (ที่คุณบอกว่าเป็น PDF)
            $table->string('doc_file')->nullable(); 
            
            // สถานะ (Borrowed = กำลังยืม, Returned = คืนแล้ว)
            $table->string('status')->default('Borrowed');
            
            $table->text('remark')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('borrows');
    }
};