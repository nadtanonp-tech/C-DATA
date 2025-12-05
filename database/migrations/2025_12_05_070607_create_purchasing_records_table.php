<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchasing_records', function (Blueprint $table) {
            $table->id();
            
            // เชื่อมกับเครื่องมือ (เครื่องไหนที่ส่งไปทำเรื่องนี้)
            $table->foreignId('instrument_id')->nullable()->constrained('instruments')->onDelete('set null');
            
            // ข้อมูลเอกสาร
            $table->string('pr_no')->nullable();      // PR No
            $table->date('pr_date')->nullable();      // PR Date
            $table->string('po_no')->nullable();      // PO No
            
            // ข้อมูลการเงิน/ผู้ขาย
            $table->string('vendor_name')->nullable(); // Place Cal (บริษัทที่ส่งไป)
            $table->string('requester')->nullable();   // Place Request (แผนกที่ขอ)
            
            $table->integer('quantity')->default(1);   // Amount
            $table->decimal('estimated_price', 15, 2)->nullable(); // Price Request (ราคากลาง/ขอ)
            $table->decimal('net_price', 15, 2)->nullable();       // Price (ราคาจริง)
            
            // สถานะ (แปลงจาก Bool เป็น String ให้เข้าใจง่าย)
            $table->string('status')->default('Pending'); // Status
            
            $table->date('receive_date')->nullable(); // Recieve Date (วันที่ของกลับมา/รับของ)
            $table->text('remark')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchasing_records');
    }
};