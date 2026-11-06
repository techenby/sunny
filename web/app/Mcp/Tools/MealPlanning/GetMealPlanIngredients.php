<?php

declare(strict_types=1);

namespace App\Mcp\Tools\MealPlanning;

use App\Actions\MealPlanning\CollectMealIngredients;
use App\Mcp\Tools\MealPlanning\Concerns\InteractsWithMeals;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the ingredients of the recipes on the meal plan for a run of days, grouped by recipe, to build a shopping list. A recipe planned more than once is listed once with times_planned. Free-text meals have no ingredients and are left out. Show the lines to the user, let them drop what they already have, then add the rest with add-checklist-items to a shopping list from list-checklists. Defaults to 7 days from today.')]
class GetMealPlanIngredients extends Tool
{
    use InteractsWithMeals;

    public function handle(Request $request, CollectMealIngredients $collect): Response|ResponseFactory
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:31'],
        ], [
            'from.date_format' => 'The from date must be in Y-m-d format, for example "2026-07-08".',
            'days.max' => 'Ingredients can be collected for at most 31 days at a time.',
        ]);

        $team = $this->team($request);
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'], $team->timezone) : $team->today();
        $through = $from->addDays((int) ($validated['days'] ?? 7) - 1);

        return Response::structured([
            'from' => $from->toDateString(),
            'through' => $through->toDateString(),
            'recipes' => $collect->handle($team, $from, $through)
                ->map(fn (array $group): array => [
                    'recipe_id' => $group['recipe']->id,
                    'recipe_name' => $group['recipe']->name,
                    'times_planned' => $group['count'],
                    'ingredients' => $group['lines'],
                ])
                ->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()
                ->description('The first day, as Y-m-d. Defaults to today in the team\'s timezone.'),
            'days' => $schema->integer()->min(1)->max(31)
                ->description('How many days to include, from 1 to 31. Defaults to 7.'),
        ];
    }

    /** @return array<string, Type> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->required(),
            'through' => $schema->string()->required(),
            'recipes' => $schema->array()->items($schema->object([
                'recipe_id' => $schema->integer()->required(),
                'recipe_name' => $schema->string()->required(),
                'times_planned' => $schema->integer()->required(),
                'ingredients' => $schema->array()->items($schema->string())->required()
                    ->description('Ingredient lines exactly as written in the recipe.'),
            ]))->required(),
        ];
    }
}
