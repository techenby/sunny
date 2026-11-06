<?php

declare(strict_types=1);

namespace App\Mcp\Tools\MealPlanning;

use App\Actions\MealPlanning\OverrideMealSlot;
use App\Mcp\Tools\MealPlanning\Concerns\InteractsWithMeals;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Set the meals in one meal slot on one date, e.g. tacos and rice for Dinner on 2026-07-08. This changes only that slot on that date; the weekly template and other dates stay the same. Pass items: [] to skip the meal. Use get-meal-plan to find meal_slot_id values, and reset-meal-plan-slot to go back to the template.')]
class SetMealPlanSlot extends Tool
{
    use InteractsWithMeals;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'meal_slot_id' => ['required', 'integer'],
            ...$this->mealItemRules(),
        ], [
            'date.date_format' => 'The date must be in Y-m-d format, for example "2026-07-08".',
            ...$this->mealItemMessages(),
        ]);

        $team = $this->team($request);
        $slot = $this->findMealSlot($team, (int) $validated['meal_slot_id']);

        if ($slot === null) {
            return Response::error('Meal slot not found. Use get-meal-plan or get-meal-template to find meal_slot_id values.');
        }

        $attributes = $this->mealItemAttributes($team, $validated['items']);
        $date = CarbonImmutable::parse($validated['date'], $team->timezone);

        $planSlot = DB::transaction(function () use ($slot, $date, $attributes) {
            $planSlot = (new OverrideMealSlot)->handle($slot, $date);
            $planSlot->items()->delete();

            foreach ($attributes as $position => $item) {
                $planSlot->items()->create([...$item, 'position' => $position + 1]);
            }

            return $planSlot;
        });

        return Response::structured([
            'date' => $date->toDateString(),
            'meal_slot_id' => $slot->id,
            'slot_name' => $slot->name,
            'items' => $this->formatMealItems($planSlot->items()->with('recipe')->get()),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('The date, as Y-m-d.')->required(),
            'meal_slot_id' => $schema->integer()->description('The meal slot to set, such as the team\'s Dinner slot.')->required(),
            ...$this->mealItemsInputSchema($schema),
        ];
    }

    /** @return array<string, Type> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->required(),
            'meal_slot_id' => $schema->integer()->required(),
            'slot_name' => $schema->string()->required(),
            'items' => $schema->array()->items($this->mealItemOutputSchema($schema))->required(),
        ];
    }
}
