<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->string('url', 2048)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropColumn('url');
        });
    }
};
