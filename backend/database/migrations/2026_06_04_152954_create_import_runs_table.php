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
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('data_source_id');
            $table->string('status');           // started, completed, failed
            $table->integer('records_inserted')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->string('source_url')->nullable();
            $table->string('source_snapshot')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('data_source_id')->references('id')->on('data_sources');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
