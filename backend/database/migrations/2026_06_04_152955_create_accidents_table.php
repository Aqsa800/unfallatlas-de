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
        Schema::create('accidents', function (Blueprint $table) {
            $table->id();
            // Time
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->tinyInteger('hour')->nullable();
            $table->tinyInteger('weekday')->nullable();  // 1=Mon … 7=Sun
            // Classification
            $table->tinyInteger('category');             // 1=fatal, 2=serious, 3=slight
            $table->tinyInteger('accident_type')->nullable();
            $table->tinyInteger('light_condition')->nullable(); // 0=daylight, 1=dusk, 2=night
            $table->tinyInteger('road_condition')->nullable();
            // Participant flags (1=yes, 0=no)
            $table->boolean('involves_pedestrian')->default(false);
            $table->boolean('involves_bicycle')->default(false);
            $table->boolean('involves_motorcycle')->default(false);
            $table->boolean('involves_car')->default(false);
            $table->boolean('involves_truck')->default(false);
            // Location
            $table->decimal('lon', 10, 6)->nullable();
            $table->decimal('lat', 10, 6)->nullable();
            $table->string('district_ags', 5)->nullable();   // 5-digit district key
            $table->string('municipality_ags', 8)->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            // Provenance
            $table->string('source_system')->default('unfallatlas');
            $table->unsignedBigInteger('import_run_id')->nullable();
            $table->timestamps();
            $table->index('year');
            $table->index('district_ags');
            $table->index(['year', 'district_ags']);
            $table->index('involves_bicycle');
            $table->index('involves_pedestrian');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accidents');
    }
};
