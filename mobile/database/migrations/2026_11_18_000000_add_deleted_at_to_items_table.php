<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', fn (Blueprint $table) => $table->timestamp('deleted_at')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropIndex(['deleted_at']);
            $table->dropColumn('deleted_at');
        });
    }
};
