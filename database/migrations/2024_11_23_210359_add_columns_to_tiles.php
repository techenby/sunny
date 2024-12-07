<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_tiles', function (Blueprint $table) {
            $table->string('type')->after('name')->nullable();
            $table->json('settings')->after('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tiles', function (Blueprint $table) {
            $table->dropColumn(['type', 'settings']);
        });
    }
};
