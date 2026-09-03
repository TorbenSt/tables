<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detected_tables', function (Blueprint $table) {
            $table->string('stable_key', 32)->nullable()->after('table_photo_id');
            $table->string('color_hex', 7)->nullable()->after('stable_key');
            $table->unique(['photo_session_id', 'stable_key']);
        });

        Schema::create('table_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detected_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_photo_id')->constrained()->cascadeOnDelete();
            $table->decimal('bbox_x', 6, 3);
            $table->decimal('bbox_y', 6, 3);
            $table->decimal('bbox_w', 6, 3);
            $table->decimal('bbox_h', 6, 3);
            $table->string('observed_condition')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['detected_table_id', 'table_photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_observations');

        Schema::table('detected_tables', function (Blueprint $table) {
            $table->dropUnique(['photo_session_id', 'stable_key']);
            $table->dropColumn(['stable_key', 'color_hex']);
        });
    }
};
