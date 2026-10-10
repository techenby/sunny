<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['recipes', 'items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('thumb_url')->nullable();
                $table->text('thumb_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['recipes', 'items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['thumb_url', 'thumb_path']));
        }
    }
};
