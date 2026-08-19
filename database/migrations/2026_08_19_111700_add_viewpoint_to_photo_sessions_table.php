<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->decimal('viewpoint_latitude', 10, 7)->nullable()->after('capture_date');
            $table->decimal('viewpoint_longitude', 10, 7)->nullable()->after('viewpoint_latitude');
            $table->decimal('viewpoint_bearing', 6, 2)->nullable()->after('viewpoint_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->dropColumn(['viewpoint_latitude', 'viewpoint_longitude', 'viewpoint_bearing']);
        });
    }
};
