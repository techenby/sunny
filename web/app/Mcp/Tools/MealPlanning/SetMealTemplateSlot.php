<?php

declare(strict_types=1);

namespace App\Mcp\Tools\MealPlanning;

use App\Mcp\Tools\MealPlanning\Concerns\InteractsWithMeals;
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
#[Description('Set the meals that repeat in one meal slot on one weekday of the weekly template, e.g. tacos for Dinner every Tuesday. Upcoming dates follow the template unless that slot was changed on that date; days that have ended are saved as history and do not change. Pass items: [] to clear it.')]
class SetMealTemplateSlot extends Tool
{
    use InteractsWithMeals;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'meal_slot_id' => ['required', 'integer'],
            ...$this->mealItemRules(),
        ], [
            'weekday.between' => 'The weekday must be an integer from 0 (Sunday) to 6 (Saturday).',
            ...$this->mealItemMessages(),
        ]);

        $team = $this->team($request);
        $slot = $this->findMealSlot($team, (int) $validated['meal_slot_id']);

        if ($slot === null) {
            return Response::error('Meal slot not found. Use get-meal-template to find meal_slot_id values.');
        }

        $weekday = (int) $validated['weekday'];
        $attributes = $this->mealItemAttributes($team, $validated['items']);

        DB::transaction(function () use ($slot, $weekday, $attributes): void {
            $slot->templateItems()->where('weekday', $weekday)->delete();

            foreach ($attributes as $position => $item) {
                $slot->templateItems()->create([
                    ...$item,
                    'team_id' => $slot->team_id,
                    'weekday' => $weekday,
                    'position' => $position + 1,
                ]);
            }
        });

        return Response::structured([
            'weekday' => $weekday,
            'meal_slot_id' => $slot->id,
            'slot_name' => $slot->name,
            'items' => $this->formatMealItems($slot->templateItems()->where('weekday', $weekday)->with('recipe')->get()),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'weekday' => $schema->integer()->min(0)->max(6)
                ->description('The weekday, 0 = Sunday through 6 = Saturday.')->required(),
            'meal_slot_id' => $schema->integer()->description('The meal slot to set.')->required(),
            ...$this->mealItemsInputSchema($schema),
        ];
    }

    /** @return array<string, Type> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'weekday' => $schema->integer()->required(),
            'meal_slot_id' => $schema->integer()->required(),
            'slot_name' => $schema->string()->required(),
            'items' => $schema->array()->items($this->mealItemOutputSchema($schema))->required(),
        ];
    }
}
