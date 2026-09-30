<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', fn (Blueprint $table) => $table->string('timezone')->nullable());

        Schema::create('routine_occurrences', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('routine_id');
            $table->date('due_on')->index();
            $table->string('name');
            $table->string('time_of_day');
            $table->string('assignee')->nullable();
        });

        Schema::create('routine_occurrence_steps', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->foreignId('routine_occurrence_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_occurrence_steps');
        Schema::dropIfExists('routine_occurrences');
        Schema::table('teams', fn (Blueprint $table) => $table->dropColumn('timezone'));
    }
};
