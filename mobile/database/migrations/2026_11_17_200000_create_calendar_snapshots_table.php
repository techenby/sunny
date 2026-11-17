<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('server', 64);
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('range', 32);
            $table->json('payload');
            $table->timestamp('fetched_at');
            $table->unique(['server', 'team_id', 'range']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_snapshots');
    }
};
