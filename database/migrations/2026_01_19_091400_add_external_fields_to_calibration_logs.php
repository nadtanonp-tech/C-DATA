<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            // เก็บไฟล์ Certificate PDF
            $table->string('certificate_file')->nullable();
            
            // เชื่อมกับ Purchasing Record (ถ้ามา External)
            $table->foreignId('purchasing_record_id')->nullable()->constrained('purchasing_records')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('calibration_logs', function (Blueprint $table) {
            $table->dropForeign(['purchasing_record_id']);
            $table->dropColumn([
                'certificate_file',
                'purchasing_record_id',
            ]);
        });
    }
};
