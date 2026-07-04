<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // road_type = UTYP1 = Unfalltyp (crash scenario 1-7), rename to crash_type
        Schema::table('accidents', function (Blueprint $table) {
            $table->renameColumn('road_type', 'crash_type');
        });

        // road_condition stays: IstStrassenzustand (0=dry, 1=wet, 2=icy)
        // Available in Unfallatlas from 2022 onward — populate from raw ZIP data via re-import
        // Add index for filtering
        Schema::table('accidents', function (Blueprint $table) {
            $table->index('crash_type');
        });
    }

    public function down(): void
    {
        Schema::table('accidents', function (Blueprint $table) {
            $table->dropIndex(['crash_type']);
            $table->renameColumn('crash_type', 'road_type');
        });
    }
};
