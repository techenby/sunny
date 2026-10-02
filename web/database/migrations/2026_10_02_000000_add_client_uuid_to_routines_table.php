<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable();
            $table->unique(['team_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
