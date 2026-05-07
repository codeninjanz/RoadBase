<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'centreline' to road_segments.kind so the table can also hold
 * national NZ Roads centreline geometry alongside NSLR speed-limit zones.
 *
 * Centrelines come tagged with a road-hierarchy class (Motorway / Arterial /
 * Collector / Local / etc.) which we use both to style the map layer and to
 * filter by zoom — at zoom 8 we only ship motorways/arterials, at zoom 14 we
 * ship every local street. Stored as a free-form string so we don't have to
 * predict the exact vocabulary of the upstream feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE road_segments MODIFY COLUMN kind ENUM('speed_limit', 'centreline') NOT NULL");
        DB::statement('ALTER TABLE road_segments ADD COLUMN hierarchy VARCHAR(64) NULL AFTER speed_limit_type');
        DB::statement('ALTER TABLE road_segments ADD COLUMN rca_code VARCHAR(32) NULL AFTER rca');
        DB::statement('CREATE INDEX road_segments_hierarchy_idx ON road_segments (hierarchy)');
        DB::statement('CREATE INDEX road_segments_rca_code_idx ON road_segments (rca_code)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX road_segments_hierarchy_idx ON road_segments');
        DB::statement('DROP INDEX road_segments_rca_code_idx ON road_segments');
        DB::statement('ALTER TABLE road_segments DROP COLUMN hierarchy');
        DB::statement('ALTER TABLE road_segments DROP COLUMN rca_code');
        DB::statement("ALTER TABLE road_segments MODIFY COLUMN kind ENUM('speed_limit') NOT NULL");
    }
};
