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
        Schema::table('monthly_plans', function (Blueprint $table) {
            // Drop old unique constraint
            $table->dropUnique('monthly_plans_unique');
            
            // Add new unique constraint including calibration_type
            $table->unique(['plan_month', 'tool_type_id', 'department', 'calibration_type'], 'monthly_plans_unique_v2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->dropUnique('monthly_plans_unique_v2');
            $table->unique(['plan_month', 'tool_type_id', 'department'], 'monthly_plans_unique');
        });
    }
};
