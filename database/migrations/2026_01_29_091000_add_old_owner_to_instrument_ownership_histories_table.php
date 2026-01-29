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
        Schema::table('instrument_ownership_histories', function (Blueprint $table) {
            $table->string('old_owner_id')->nullable()->after('instrument_id');
            $table->string('old_owner_name')->nullable()->after('old_owner_id');
            
            // Rename existing owner columns to clarify they are NEW values (optional, but let's keep names simple)
            // Or just treat 'owner_id' as 'new_owner_id'. 
            // Let's stick to adding old_ columns for clarity.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instrument_ownership_histories', function (Blueprint $table) {
            $table->dropColumn(['old_owner_id', 'old_owner_name']);
        });
    }
};
