<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_feeds', function (Blueprint $table) {
            $table->timestamp('ics_fetched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_feeds', function (Blueprint $table) {
            $table->dropColumn('ics_fetched_at');
        });
    }
};
