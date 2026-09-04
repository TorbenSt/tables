<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedTinyInteger('zoom')->default(19);
            $table->string('imagery_source')->default('esri');
            $table->string('status')->default('draft');
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('map_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_site_id')->constrained()->cascadeOnDelete();
            $table->string('stable_key');
            $table->string('color_hex', 7);
            $table->string('label')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('has_umbrella')->default(false);
            $table->decimal('umbrella_height_m', 5, 2)->nullable();
            $table->decimal('umbrella_radius_m', 5, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['map_site_id', 'stable_key']);
        });

        Schema::create('map_occluders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_site_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // building | tree | umbrella
            $table->string('source')->default('osm'); // osm | grok | user
            $table->string('osm_id')->nullable();
            $table->string('name')->nullable();
            $table->decimal('height_m', 6, 2);
            $table->decimal('radius_m', 6, 2)->nullable();
            $table->json('polygon')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('map_sun_shade_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_table_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->json('hourly');
            $table->unsignedTinyInteger('sun_hours')->default(0);
            $table->unsignedTinyInteger('shade_hours')->default(0);
            $table->timestamps();

            $table->unique(['map_table_id', 'forecast_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_sun_shade_forecasts');
        Schema::dropIfExists('map_occluders');
        Schema::dropIfExists('map_tables');
        Schema::dropIfExists('map_sites');
    }
};
