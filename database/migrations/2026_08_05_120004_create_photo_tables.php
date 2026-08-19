<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->date('capture_date');
            $table->string('status')->default('pending'); // pending | processing | ready | failed
            $table->text('error_message')->nullable();
            $table->json('analysis_raw')->nullable();
            $table->timestamps();
        });

        Schema::create('table_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_session_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->time('captured_at');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('bearing', 6, 2); // 0-360
            $table->boolean('umbrella_hint')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('detected_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_photo_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->decimal('bbox_x', 6, 3); // % 0-100
            $table->decimal('bbox_y', 6, 3);
            $table->decimal('bbox_w', 6, 3);
            $table->decimal('bbox_h', 6, 3);
            $table->boolean('has_umbrella')->default(false);
            $table->decimal('umbrella_height_m', 5, 2)->nullable();
            $table->decimal('umbrella_radius_m', 5, 2)->nullable();
            $table->string('observed_condition')->nullable(); // sun | shade | mixed | unknown
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('sun_shade_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detected_table_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->json('hourly'); // [{hour, sun: bool, elevation, azimuth, reason}]
            $table->unsignedTinyInteger('sun_hours')->default(0);
            $table->unsignedTinyInteger('shade_hours')->default(0);
            $table->timestamps();

            $table->unique(['detected_table_id', 'forecast_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sun_shade_forecasts');
        Schema::dropIfExists('detected_tables');
        Schema::dropIfExists('table_photos');
        Schema::dropIfExists('photo_sessions');
    }
};
