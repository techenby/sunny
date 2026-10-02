<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('time_of_day');
            $table->string('frequency');
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->string('starts_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->bigInteger('local_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('routine_steps', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->bigInteger('routine_id')->index();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->bigInteger('local_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_steps');
        Schema::dropIfExists('routines');
    }
};
