<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunny_pending_writes', function (Blueprint $table): void {
            $table->id();
            $table->string('server', 64);
            $table->string('resource');
            $table->bigInteger('record_id');
            $table->unsignedBigInteger('team_id');
            $table->uuid('client_uuid')->nullable();
            $table->json('payload');
            $table->text('photo_path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->text('error')->nullable();
            $table->unique(['server', 'resource', 'record_id']);
        });

        foreach (['recipes', 'items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->bigInteger('local_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sunny_pending_writes');

        foreach (['recipes', 'items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('local_id'));
        }
    }
};
