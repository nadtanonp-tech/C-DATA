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
        Schema::table('instrument_ownership_histories', function (Blueprint $table) {
            // Department
            $table->string('old_department_name')->nullable()->after('old_owner_name'); // เก็บชื่อเลยง่ายกว่า join
            $table->string('department_name')->nullable()->after('owner_name'); 
            
            // Machine
            $table->string('old_machine_name')->nullable()->after('old_department_name');
            $table->string('machine_name')->nullable()->after('department_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instrument_ownership_histories', function (Blueprint $table) {
            $table->dropColumn(['old_department_name', 'department_name', 'old_machine_name', 'machine_name']);
        });
    }
};
