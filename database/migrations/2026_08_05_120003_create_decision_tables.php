<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('hash', 64);
            $table->json('payload');
            $table->timestamp('fetched_at');
            $table->timestamps();
        });

        Schema::create('decision_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('weather_snapshot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('rules'); // rules | grok | hybrid
            $table->timestamp('ran_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('decision_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_run_id')->constrained()->cascadeOnDelete();
            $table->date('target_date');
            $table->unsignedTinyInteger('matched_scenario_id')->nullable();
            $table->unsignedTinyInteger('outdoor_sun');
            $table->unsignedTinyInteger('outdoor_shade');
            $table->unsignedTinyInteger('indoor');
            $table->json('weather_day')->nullable();
            $table->text('reasoning')->nullable();
            $table->boolean('grok_adjusted')->default(false);
            $table->timestamps();

            $table->index(['decision_run_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_entries');
        Schema::dropIfExists('decision_runs');
        Schema::dropIfExists('weather_snapshots');
    }
};
