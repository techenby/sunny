<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->string('name');
        });

        Schema::create('recipes', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name');
            foreach (['source', 'servings', 'prep_time', 'cook_time', 'total_time'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['description', 'ingredients', 'instructions', 'notes', 'nutrition'] as $field) {
                $table->text($field)->nullable();
            }
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('type');
            $table->string('name');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('sunny_sync_states', function (Blueprint $table): void {
            $table->string('server', 64)->primary();
            $table->string('synced_at');
            $table->timestamp('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('sunny_sync_states');
    }
};
