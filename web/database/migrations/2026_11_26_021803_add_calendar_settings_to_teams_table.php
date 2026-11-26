<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('calendar_view', 16)->default('automatic')->after('rotation');
            $table->string('calendar_event_style', 16)->default('stripe')->after('calendar_view');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['calendar_view', 'calendar_event_style']);
        });
    }
};
