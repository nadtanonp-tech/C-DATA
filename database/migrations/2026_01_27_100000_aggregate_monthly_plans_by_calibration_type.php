<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_plans', function (Blueprint $table) {
            // Make tool_type_id nullable
            $table->foreignId('tool_type_id')->nullable()->change();

            // Drop previous unique constraint
            $table->dropUnique('monthly_plans_unique_v2');

            // 🧹 Clean up duplicates before adding new unique constraint
            // Keep the row with the max ID for each (plan_month, department, calibration_type) group
            DB::statement('
                DELETE FROM monthly_plans 
                WHERE id NOT IN (
                    SELECT MAX(id)
                    FROM monthly_plans 
                    GROUP BY plan_month, department, calibration_type
                )
            ');

            // Add new unique constraint for aggregation
            $table->unique(['plan_month', 'department', 'calibration_type'], 'monthly_plans_unique_aggregated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->dropUnique('monthly_plans_unique_aggregated');
            $table->unique(['plan_month', 'tool_type_id', 'department', 'calibration_type'], 'monthly_plans_unique_v2');
            $table->foreignId('tool_type_id')->nullable(false)->change();
        });
    }
};
