<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            
            // --- Identity ---
            $table->string('code_no')->unique(); // CodeNo
            $table->foreignId('tool_type_id')->nullable()->constrained('tool_types'); // Type (nullable เผื่อหาไม่เจอ)
            
            $table->string('name')->nullable();       // Name
            $table->string('serial_no')->nullable();  // Serial
            $table->string('brand')->nullable();      // Brand
            $table->string('asset_no')->nullable();   // AssetNo
            $table->string('equip_type')->nullable(); // EquipType (เพิ่มใหม่)
            $table->string('maker')->nullable();      // NameMakerB (เพิ่มใหม่)
            
            // --- Ownership ---
            $table->string('owner_id')->nullable();     // IDPers
            $table->string('owner_name')->nullable();   // Personal (เพิ่มใหม่: เผื่อเก็บชื่อคนถือ)
            $table->string('department')->nullable();   // Department
            $table->string('machine_name')->nullable(); // Machine
            
            // --- Calibration ---
            $table->integer('cal_freq_months')->default(12); // FeqCAL
            $table->date('receive_date')->nullable();        // RecieveDate
            $table->date('next_cal_date')->nullable();       // ExpireDate
            $table->string('cal_place')->nullable();         // PlaceCAL
            
            // --- Specific Specs (ข้อมูลเฉพาะตัวที่อาจต่างจาก Type) ---
            $table->string('range_spec')->nullable();      // Range (เพิ่มใหม่)
            $table->string('percent_adj')->nullable();     // PercentAdj (เพิ่มใหม่)
            $table->text('criteria_specific')->nullable(); // Criteria_1 + Criteria1_1 (เพิ่มใหม่)
            $table->string('reference_doc')->nullable();   // Reference (เพิ่มใหม่)

            // --- Status & Price ---
            $table->string('status')->default('Active'); // Status
            $table->decimal('price', 15, 2)->nullable(); // Price
            $table->text('remark')->nullable();          // Remark
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('instruments');
    }
};