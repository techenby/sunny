<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_days', function (Blueprint $table): void {
            $table->id();
            $table->string('server', 64)->index();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            $table->timestamp('fetched_at');
            $table->unique(['team_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_days');
    }
};
