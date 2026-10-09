<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedSmallInteger('screensaver_after')->default(5)->after('rotation');
            $table->unsignedSmallInteger('return_home_after')->default(10)->after('screensaver_after');
            $table->string('night_starts_at', 5)->nullable()->after('return_home_after');
            $table->string('night_ends_at', 5)->nullable()->after('night_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumns(['screensaver_after', 'return_home_after', 'night_starts_at', 'night_ends_at']);
        });
    }
};
