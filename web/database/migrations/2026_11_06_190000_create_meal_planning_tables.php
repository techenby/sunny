<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'position']);
        });

        Schema::create('meal_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_slot_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['team_id', 'weekday']);
        });

        Schema::create('meal_plan_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_slot_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['meal_slot_id', 'date']);
            $table->index(['team_id', 'date']);
        });

        Schema::create('meal_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meal_plan_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['meal_plan_slot_id', 'position']);
            $table->index('recipe_id');
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->date('meals_saved_through')->nullable()->after('week_start');
        });

        $this->backfillExistingTeams();
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('meals_saved_through');
        });

        Schema::dropIfExists('meal_plan_items');
        Schema::dropIfExists('meal_plan_slots');
        Schema::dropIfExists('meal_template_items');
        Schema::dropIfExists('meal_slots');
    }

    /**
     * Existing teams get the same default slots as new ones, and their history
     * starts yesterday so the template isn't projected onto days before the
     * feature existed.
     */
    private function backfillExistingTeams(): void
    {
        DB::table('teams')->orderBy('id')->each(function (object $team): void {
            $now = now();

            DB::table('meal_slots')->insert(collect(['Breakfast', 'Lunch', 'Dinner'])
                ->map(fn (string $name, int $index): array => [
                    'team_id' => $team->id,
                    'name' => $name,
                    'position' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all());

            DB::table('teams')->where('id', $team->id)->update([
                'meals_saved_through' => CarbonImmutable::now($team->timezone)->subDay()->toDateString(),
            ]);
        });
    }
};
