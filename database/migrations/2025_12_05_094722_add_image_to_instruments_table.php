<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('instruments', function (Blueprint $table) {
            // เพิ่มคอลัมน์ instrument_image ต่อจากชื่อเครื่องมือ
            $table->string('instrument_image')->nullable()->after('name');
        });
    }

    public function down()
    {
        Schema::table('instruments', function (Blueprint $table) {
        $table->dropColumn('instrument_image');
    });
}
};
