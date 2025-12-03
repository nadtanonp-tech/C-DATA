<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
// database/migrations/xxxx_xx_xx_create_tool_types_table.php

public function up()
{
    Schema::create('tool_types', function (Blueprint $table) {
        $table->id();
        
        // --- หมวด Basic Info ---
        $table->string('code_type')->unique(); // CodeType
        $table->string('name');                // Name
        $table->string('size')->nullable();    // Size
        $table->text('picture_path')->nullable(); // Picture
        $table->float('pr_rate')->nullable();       // PRRate
        $table->string('reference_doc')->nullable(); // Reference
        $table->string('drawing_no')->nullable(); // DrawingNo
        $table->text('remark')->nullable();       // Remark

        // --- เพิ่มฟิลด์ที่ตกหล่น ---
        $table->string('pre')->nullable();        // Pre
        $table->string('cal_flag')->nullable();   // CAL (เปลี่ยนชื่อเป็น cal_flag เลี่ยงคำสงวน)
        $table->string('input_data')->nullable(); // InputData
        
        // --- หมวด A-Q (JSON) ---
        // เก็บ: A_Max...Q_Max, STDPart, SmallBig, BPlug_Max, STDCheckingFit ฯลฯ
        $table->json('dimension_specs')->nullable(); 
        
        // --- หมวด S & Cs (JSON) ---
        // เก็บ: S1-S15, Cs1-Cs15
        $table->json('ui_options')->nullable();      

        $table->timestamps();
    });
}

    public function down()
    {
        Schema::dropIfExists('tool_types');
    }
};