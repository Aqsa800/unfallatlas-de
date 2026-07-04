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
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('ags', 12)->unique();        // official 8-digit region key (AGS)
            $table->string('name');
            $table->string('level');                    // state, district, municipality
            $table->string('state_name')->nullable();   // denormalised state for fast filtering
            $table->string('state_ags', 2)->nullable(); // 2-digit state key
            $table->string('district_ags', 5)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('population')->nullable();
            $table->decimal('area_km2', 10, 2)->nullable();
            $table->decimal('lon', 10, 6)->nullable();
            $table->decimal('lat', 10, 6)->nullable();
            $table->timestamps();
            $table->index('level');
            $table->index('state_ags');
            $table->index('district_ags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
