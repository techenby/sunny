<?php

declare(strict_types=1);

namespace App\Mcp\Tools\MealPlanning;

use App\Mcp\Tools\MealPlanning\Concerns\InteractsWithMeals;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Undo changes to one meal slot on one date so it follows the weekly template again. Days that have already ended are saved as history and cannot be reset; change them with set-meal-plan-slot instead.')]
class ResetMealPlanSlot extends Tool
{
    use InteractsWithMeals;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'meal_slot_id' => ['required', 'integer'],
        ], [
            'date.date_format' => 'The date must be in Y-m-d format, for example "2026-07-08".',
        ]);

        $team = $this->team($request);
        $slot = $this->findMealSlot($team, (int) $validated['meal_slot_id']);

        if ($slot === null) {
            return Response::error('Meal slot not found. Use get-meal-plan or get-meal-template to find meal_slot_id values.');
        }

        $date = CarbonImmutable::parse($validated['date'], $team->timezone);

        if ($date->lte($team->mealsSavedThrough())) {
            return Response::error("{$date->toDateString()} has ended and is saved as history, so it cannot be reset to the template.");
        }

        $slot->planSlots()->where('date', $date->toDateString())->delete();

        $template = $slot->templateItems()->where('weekday', $date->dayOfWeek)->with('recipe')->get();

        return Response::structured([
            'date' => $date->toDateString(),
            'meal_slot_id' => $slot->id,
            'slot_name' => $slot->name,
            'items' => $this->formatMealItems($template),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('The date, as Y-m-d.')->required(),
            'meal_slot_id' => $schema->integer()->description('The meal slot to reset.')->required(),
        ];
    }

    /** @return array<string, Type> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->required(),
            'meal_slot_id' => $schema->integer()->required(),
            'slot_name' => $schema->string()->required(),
            'items' => $schema->array()->items($this->mealItemOutputSchema($schema))->required()
                ->description('The weekly template\'s meals, which the slot now shows.'),
        ];
    }
}
