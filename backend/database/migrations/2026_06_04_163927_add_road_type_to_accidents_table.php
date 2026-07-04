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
        Schema::table('accidents', function (Blueprint $table) {
            $table->tinyInteger('road_type')->nullable()->after('road_condition');
        });
    }

    public function down(): void
    {
        Schema::table('accidents', function (Blueprint $table) {
            $table->dropColumn('road_type');
        });
    }
};
