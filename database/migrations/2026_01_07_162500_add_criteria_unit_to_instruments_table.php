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
        Schema::table('instruments', function (Blueprint $table) {
            // ลบ columns เดิมทั้งหมด (รวม criteria_unit ที่ไม่ใช่ JSON)
            if (Schema::hasColumn('instruments', 'criteria_unit')) {
                $table->dropColumn('criteria_unit');
            }
            if (Schema::hasColumn('instruments', 'criteria_1')) {
                $table->dropColumn('criteria_1');
            }
            if (Schema::hasColumn('instruments', 'criteria_2')) {
                $table->dropColumn('criteria_2');
            }
        });

        // สร้าง criteria_unit ใหม่เป็น JSON
        Schema::table('instruments', function (Blueprint $table) {
            $table->json('criteria_unit')->nullable()->after('percent_adj');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instruments', function (Blueprint $table) {
            // เพิ่ม columns กลับ
            $table->decimal('criteria_1', 10, 4)->nullable();
            $table->decimal('criteria_2', 10, 4)->nullable();
            
            // ลบ criteria_unit
            $table->dropColumn('criteria_unit');
        });
    }
};
