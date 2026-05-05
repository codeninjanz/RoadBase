<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rcas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('code')->nullable(); // TA code from Stats NZ (e.g. 076)
            $table->string('name');
            $table->enum('kind', ['territorial_authority', 'state_highway_network'])->default('territorial_authority');
            $table->json('contact')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['data_source_id', 'external_id']);
            $table->index('name');
            $table->index('kind');
        });

        DB::statement('ALTER TABLE rcas ADD COLUMN geom MULTIPOLYGON NOT NULL SRID 4326');
        DB::statement('CREATE SPATIAL INDEX rcas_geom_spatial ON rcas (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rcas');
    }
};
