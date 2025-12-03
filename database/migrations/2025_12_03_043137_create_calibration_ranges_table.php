<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('calibration_ranges', function (Blueprint $table) {
            $table->id();
            
            // เชื่อมกับ tool_types (ถ้าลบ Type, Range หายด้วย)
            $table->foreignId('tool_type_id')->constrained('tool_types')->onDelete('cascade');
            
            $table->integer('sequence'); // ลำดับข้อ (1-15)
            $table->string('range_value')->nullable(); // Legacy: Range1..15
            $table->string('criteria_main')->nullable(); // Legacy: Criteria1..15
            $table->string('criteria_sub')->nullable();  // Legacy: Criteria1-1..15-15
            $table->string('unit')->nullable();          // Legacy: Unit1..15
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('calibration_ranges');
    }
};