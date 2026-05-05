<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The original road_segments.geom was LINESTRING (intended for AADT-line
 * features), but the only public source we can ingest is NSLR's speed-limit
 * zones, which are POLYGON / MULTIPOLYGON. Switch the column type to generic
 * GEOMETRY (accepts any spatial type) and add a few zone-specific columns.
 *
 * No data has been ingested yet, so a clean drop+recreate is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('road_segments');

        Schema::create('road_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->enum('kind', ['speed_limit']);
            $table->string('road_name')->nullable();
            $table->string('rca')->nullable();
            $table->string('zone_name')->nullable();
            $table->unsignedSmallInteger('speed_limit_kmh')->nullable();
            $table->string('speed_limit_type')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['data_source_id', 'external_id', 'kind']);
            $table->index('kind');
            $table->index('speed_limit_kmh');
        });

        DB::statement('ALTER TABLE road_segments ADD COLUMN geom GEOMETRY NOT NULL SRID 4326');
        DB::statement('CREATE SPATIAL INDEX road_segments_geom_spatial ON road_segments (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('road_segments');
    }
};
