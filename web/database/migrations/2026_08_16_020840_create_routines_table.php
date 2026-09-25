<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('time_of_day');
            $table->string('frequency');
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('starts_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routines');
    }
};
