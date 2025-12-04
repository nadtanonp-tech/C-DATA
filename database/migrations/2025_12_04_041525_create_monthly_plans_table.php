<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monthly_plans', function (Blueprint $table) {
            $table->id();
            $table->date('plan_month'); // Month
            $table->string('code_type')->nullable(); // Type (เก็บเป็น Code ไปก่อนกันหา ID ไม่เจอ)
            $table->string('department')->nullable(); // Department
            $table->string('status')->nullable(); // Status
            
            // ยอดรวม
            $table->integer('plan_count')->default(0); // Plan
            $table->integer('cal_count')->default(0);  // Cal
            $table->integer('remain_count')->default(0); // Remain
            
            // Level
            $table->integer('level_a')->default(0); // A
            $table->integer('level_b')->default(0); // B
            $table->integer('level_c')->default(0); // C
            
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_plans');
    }
};
