<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crashes', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('road_name')->nullable();
            $table->enum('severity', ['fatal', 'serious', 'minor', 'non_injury'])->nullable();
            $table->unsignedSmallInteger('speed_limit_kmh')->nullable();
            $table->unsignedSmallInteger('crash_year')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('crash_year');
            $table->index('severity');
        });

        DB::statement('ALTER TABLE crashes ADD COLUMN location POINT NOT NULL SRID 4326');
        DB::statement('CREATE SPATIAL INDEX crashes_location_spatial ON crashes (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('crashes');
    }
};
