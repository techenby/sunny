<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_occurrence_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('routine_occurrence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_step_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The unique index already serves lookups by occurrence.
            $table->unique(['routine_occurrence_id', 'routine_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_occurrence_steps');
    }
};
