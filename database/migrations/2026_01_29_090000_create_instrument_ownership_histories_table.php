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
        Schema::create('instrument_ownership_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->onDelete('cascade');
            $table->string('owner_id')->nullable();     // Human ID (Employee ID)
            $table->string('owner_name')->nullable();   // Human Name
            $table->text('remark')->nullable();         // Reason for change
            $table->timestamp('changed_at')->useCurrent();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete(); // Who made the change
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrument_ownership_histories');
    }
};
