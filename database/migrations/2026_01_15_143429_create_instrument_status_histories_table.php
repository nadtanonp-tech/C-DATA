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
        Schema::create('instrument_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('old_status')->nullable(); // สถานะเดิม
            $table->string('new_status'); // สถานะใหม่
            $table->text('reason')->nullable(); // เหตุผลในการเปลี่ยน
            $table->timestamp('changed_at'); // วันที่เปลี่ยนสถานะ
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete(); // ผู้เปลี่ยน
            $table->timestamps();
            
            // Indexes เพื่อความเร็ว
            $table->index('instrument_id');
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrument_status_histories');
    }
};
