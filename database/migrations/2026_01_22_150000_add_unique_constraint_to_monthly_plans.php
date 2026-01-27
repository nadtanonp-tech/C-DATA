<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, delete duplicates keeping only the first record
        DB::statement('
            DELETE FROM monthly_plans 
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) as id 
                    FROM monthly_plans 
                    GROUP BY plan_month, tool_type_id, department
                ) as keep_ids
            )
        ');

        // Then add unique constraint
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->unique(['plan_month', 'tool_type_id', 'department'], 'monthly_plans_unique');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_plans', function (Blueprint $table) {
            $table->dropUnique('monthly_plans_unique');
        });
    }
};
