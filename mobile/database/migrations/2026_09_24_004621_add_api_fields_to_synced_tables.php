<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', fn (Blueprint $table) => $table->string('slug')->nullable());
        foreach (['recipes', 'items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->text('photo_url')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('teams', fn (Blueprint $table) => $table->dropColumn('slug'));
        foreach (['recipes', 'items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('photo_url'));
        }
    }
};
