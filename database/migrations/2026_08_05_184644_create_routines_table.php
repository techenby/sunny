<?php

use App\Models\Routine;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cadence')->default('daily');
            $table->tinyInteger('reset_weekday')->nullable();
            $table->timestamps();
        });

        Schema::create('routine_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Routine::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_tasks');
        Schema::dropIfExists('routines');
    }
};
