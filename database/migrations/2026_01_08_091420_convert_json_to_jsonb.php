<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 🔥 เปลี่ยน JSON เป็น JSONB สำหรับ PostgreSQL (เร็วกว่า, index ได้)
     */
    public function up(): void
    {
        // 1. calibration_logs table (ไม่ใช่ calibration_records!)
        if (Schema::hasTable('calibration_logs')) {
            if (Schema::hasColumn('calibration_logs', 'calibration_data')) {
                DB::statement('ALTER TABLE calibration_logs ALTER COLUMN calibration_data TYPE jsonb USING calibration_data::jsonb');
            }
            if (Schema::hasColumn('calibration_logs', 'environment')) {
                DB::statement('ALTER TABLE calibration_logs ALTER COLUMN environment TYPE jsonb USING environment::jsonb');
            }
            // สร้าง GIN Index
            DB::statement('CREATE INDEX IF NOT EXISTS idx_calibration_data ON calibration_logs USING GIN (calibration_data)');
        }
        
        // 2. tool_types table
        if (Schema::hasTable('tool_types')) {
            if (Schema::hasColumn('tool_types', 'dimension_specs')) {
                DB::statement('ALTER TABLE tool_types ALTER COLUMN dimension_specs TYPE jsonb USING dimension_specs::jsonb');
            }
            if (Schema::hasColumn('tool_types', 'ui_options')) {
                DB::statement('ALTER TABLE tool_types ALTER COLUMN ui_options TYPE jsonb USING ui_options::jsonb');
            }
            // สร้าง GIN Index
            DB::statement('CREATE INDEX IF NOT EXISTS idx_dimension_specs ON tool_types USING GIN (dimension_specs)');
        }
        
        // 3. instruments table (criteria_unit)
        if (Schema::hasTable('instruments') && Schema::hasColumn('instruments', 'criteria_unit')) {
            DB::statement('ALTER TABLE instruments ALTER COLUMN criteria_unit TYPE jsonb USING criteria_unit::jsonb');
        }
    }

    /**
     * Reverse the migrations.
     * 🔄 เปลี่ยนกลับเป็น JSON
     */
    public function down(): void
    {
        // Drop indexes
        DB::statement('DROP INDEX IF EXISTS idx_calibration_data');
        DB::statement('DROP INDEX IF EXISTS idx_dimension_specs');
        
        // 1. calibration_logs table
        if (Schema::hasTable('calibration_logs')) {
            if (Schema::hasColumn('calibration_logs', 'calibration_data')) {
                DB::statement('ALTER TABLE calibration_logs ALTER COLUMN calibration_data TYPE json USING calibration_data::json');
            }
            if (Schema::hasColumn('calibration_logs', 'environment')) {
                DB::statement('ALTER TABLE calibration_logs ALTER COLUMN environment TYPE json USING environment::json');
            }
        }
        
        // 2. tool_types table
        if (Schema::hasTable('tool_types')) {
            if (Schema::hasColumn('tool_types', 'dimension_specs')) {
                DB::statement('ALTER TABLE tool_types ALTER COLUMN dimension_specs TYPE json USING dimension_specs::json');
            }
            if (Schema::hasColumn('tool_types', 'ui_options')) {
                DB::statement('ALTER TABLE tool_types ALTER COLUMN ui_options TYPE json USING ui_options::json');
            }
        }
        
        // 3. instruments table
        if (Schema::hasTable('instruments') && Schema::hasColumn('instruments', 'criteria_unit')) {
            DB::statement('ALTER TABLE instruments ALTER COLUMN criteria_unit TYPE json USING criteria_unit::json');
        }
    }
};
