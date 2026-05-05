<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('road_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->enum('kind', ['aadt_line', 'speed_limit']);
            $table->string('road_name')->nullable();
            $table->string('rca')->nullable();
            $table->unsignedInteger('aadt')->nullable();
            $table->decimal('heavy_vehicle_pct', 5, 2)->nullable();
            $table->unsignedSmallInteger('speed_limit_kmh')->nullable();
            $table->string('speed_limit_type')->nullable();
            $table->enum('nzgttm_level', ['LV', '1', '2', '3'])->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['data_source_id', 'external_id', 'kind']);
            $table->index('kind');
            $table->index('road_name');
            $table->index('aadt');
        });

        DB::statement('ALTER TABLE road_segments ADD COLUMN geom LINESTRING NOT NULL SRID 4326');
        DB::statement('CREATE SPATIAL INDEX road_segments_geom_spatial ON road_segments (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('road_segments');
    }
};
