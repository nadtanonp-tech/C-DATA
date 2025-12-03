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
            $table->string('code_no')->unique(); // Legacy: CodeNo (รหัสทรัพย์สิน)
            
            // เชื่อมกับสเปค (Type)
            $table->foreignId('tool_type_id')->constrained('tool_types');
            
            $table->string('serial_no')->nullable();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('asset_no')->nullable();
            
            // เจ้าของ / สถานที่
            $table->string('owner_id')->nullable(); // Legacy: IDPers
            $table->string('department')->nullable();
            $table->string('machine_name')->nullable();
            
            // สถานะการสอบเทียบ
            $table->integer('cal_freq_months')->default(12); // Legacy: FeqCAL
            $table->date('receive_date')->nullable();
            $table->date('last_cal_date')->nullable();
            $table->date('next_cal_date')->nullable(); // ควรคำนวณ: last + freq
            $table->enum('cal_place', ['Internal', 'External'])->default('Internal');
            
            // สถานะเครื่องมือ
            $table->enum('status', ['Active', 'Inactive', 'Repair', 'Lost'])->default('Active');
            
            $table->decimal('price', 10, 2)->nullable();
            $table->text('remark')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('instruments');
    }
};