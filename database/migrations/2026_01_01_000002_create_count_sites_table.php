<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('count_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('road_name')->nullable();
            $table->string('region')->nullable();
            $table->string('rca')->nullable();
            $table->unsignedInteger('aadt')->nullable();
            $table->decimal('heavy_vehicle_pct', 5, 2)->nullable();
            $table->unsignedInteger('peak_hour_volume')->nullable();
            $table->time('peak_hour_start')->nullable();
            $table->date('count_date')->nullable();
            $table->unsignedSmallInteger('speed_limit_kmh')->nullable();
            $table->enum('nzgttm_level', ['LV', '1', '2', '3'])->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['data_source_id', 'external_id']);
            $table->index('road_name');
            $table->index('aadt');
            $table->index('nzgttm_level');
        });

        // Spatial column + index require raw SQL for SRID + NOT NULL.
        DB::statement('ALTER TABLE count_sites ADD COLUMN location POINT NOT NULL SRID 4326');
        DB::statement('CREATE SPATIAL INDEX count_sites_location_spatial ON count_sites (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('count_sites');
    }
};
