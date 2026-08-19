<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->string('respondent_name')->nullable();
            $table->string('respondent_role')->nullable();
            $table->timestamps();
        });

        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('scenario_id');
            $table->unsignedTinyInteger('outdoor_sun');
            $table->unsignedTinyInteger('outdoor_shade');
            $table->unsignedTinyInteger('indoor');
            $table->timestamps();

            $table->unique(['survey_response_id', 'scenario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
    }
};
