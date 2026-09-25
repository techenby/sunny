<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['routine_id', 'due_on']);
            $table->index('due_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_occurrences');
    }
};
