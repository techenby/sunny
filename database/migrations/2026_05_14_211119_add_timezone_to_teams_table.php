<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('timezone')->default('America/Chicago')->after('is_personal');
            $table->integer('week_start')->default(Carbon::SUNDAY)->after('timezone');
            $table->json('address')->nullable()->after('week_start');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumns(['timezone', 'week_start', 'address']);
        });
    }
};
