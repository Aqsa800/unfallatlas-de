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
        Schema::create('indicator_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('region_id');
            $table->unsignedBigInteger('indicator_id');
            $table->smallInteger('year');
            $table->decimal('value', 18, 4);
            $table->timestamps();
            $table->unique(['region_id', 'indicator_id', 'year']);
            $table->foreign('region_id')->references('id')->on('regions');
            $table->foreign('indicator_id')->references('id')->on('indicators');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_values');
    }
};
