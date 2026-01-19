<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchasing_records', function (Blueprint $table) {
            // เชื่อมกับผลสอบเทียบ
            $table->foreignId('calibration_log_id')->nullable()->constrained('calibration_logs')->onDelete('set null');
            
            // เก็บไฟล์ Certificate PDF
            $table->string('certificate_file')->nullable();
            
            // วันที่ส่งออกและคาดว่าจะได้คืน
            $table->date('send_date')->nullable();
            $table->date('expected_return_date')->nullable();
        });
    }

    public function down()
    {
        Schema::table('purchasing_records', function (Blueprint $table) {
            $table->dropForeign(['calibration_log_id']);
            $table->dropColumn([
                'calibration_log_id',
                'certificate_file',
                'send_date',
                'expected_return_date',
            ]);
        });
    }
};
