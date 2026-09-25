<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('source')->nullable();
            $table->string('servings')->nullable();
            $table->string('prep_time')->nullable();
            $table->string('cook_time')->nullable();
            $table->string('total_time')->nullable();
            $table->text('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();
            $table->text('nutrition')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'slug']);
            $table->index(['team_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
