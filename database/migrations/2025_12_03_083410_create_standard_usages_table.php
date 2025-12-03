<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('standard_usages', function (Blueprint $table) {
            $table->id();
            
            // เชื่อมกับตาราง Type
            $table->foreignId('tool_type_id')->constrained('tool_types')->onDelete('cascade');
            
            // เชื่อมกับตาราง Master
            $table->foreignId('master_id')->constrained('masters')->onDelete('cascade');
            
            // จุดที่วัด (Point A, B, C...)
            $table->string('check_point')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('standard_usages');
    }
};