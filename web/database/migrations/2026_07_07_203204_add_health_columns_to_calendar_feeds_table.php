<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_feeds', function (Blueprint $table) {
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_feeds', function (Blueprint $table) {
            $table->dropColumn(['last_fetched_at', 'last_failed_at', 'last_error']);
        });
    }
};
