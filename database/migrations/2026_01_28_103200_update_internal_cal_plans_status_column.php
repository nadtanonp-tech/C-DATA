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
        Schema::table('internal_cal_plans', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->string('result_status')->nullable()->after('next_cal_date'); // ผลการ CAL จาก calibration_logs.result_status
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internal_cal_plans', function (Blueprint $table) {
            $table->dropColumn('result_status');
            $table->string('status')->default('Plan');
        });
    }
};
