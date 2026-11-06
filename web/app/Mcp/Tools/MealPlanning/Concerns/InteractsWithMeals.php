<?php

declare(strict_types=1);

namespace App\Mcp\Tools\MealPlanning\Concerns;

use App\Models\MealPlanItem;
use App\Models\MealSlot;
use App\Models\MealTemplateItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

trait InteractsWithMeals
{
    protected function team(Request $request): Team
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->currentTeam instanceof Team, 403);

        return $user->currentTeam;
    }

    /** @return array<string, mixed> */
    protected function mealItemRules(): array
    {
        return [
            'items' => ['present', 'array', 'max:20'],
            'items.*' => ['array'],
            'items.*.recipe_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:255', 'required_without:items.*.recipe_id'],
        ];
    }

    /** @return array<string, string> */
    protected function mealItemMessages(): array
    {
        return [
            'items.present' => 'Provide the meals as an array, or an empty array to clear the slot.',
            'items.*.name.required_without' => 'Each meal needs a recipe_id or a name.',
            'items.*.name.max' => 'Each meal name may not be longer than 255 characters.',
        ];
    }

    /**
     * Turn the validated meals into attributes for new items, linking recipes
     * from the team and storing their current name.
     *
     * @param  list<array{recipe_id?: int|null, name?: string|null}>  $items
     * @return list<array{recipe_id: int|null, name: string}>
     */
    protected function mealItemAttributes(Team $team, array $items): array
    {
        $recipeIds = array_values(array_filter(array_map(fn (array $item): mixed => $item['recipe_id'] ?? null, $items)));
        $recipes = $team->recipes()->whereKey($recipeIds)->get()->keyBy('id');

        return array_map(function (array $item) use ($recipes): array {
            $recipeId = isset($item['recipe_id']) ? (int) $item['recipe_id'] : null;

            if ($recipeId !== null && ! $recipes->has($recipeId)) {
                throw ValidationException::withMessages([
                    'items' => "Recipe {$recipeId} was not found on the current team. Use the search-recipes tool to find ids.",
                ]);
            }

            return [
                'recipe_id' => $recipeId,
                'name' => $recipeId !== null ? $recipes[$recipeId]->name : trim((string) ($item['name'] ?? '')),
            ];
        }, $items);
    }

    protected function findMealSlot(Team $team, int $slotId): ?MealSlot
    {
        return $team->mealSlots()->find($slotId);
    }

    /**
     * @param  Collection<int, MealPlanItem>|Collection<int, MealTemplateItem>  $items
     * @return list<array{recipe_id: int|null, name: string}>
     */
    protected function formatMealItems(Collection $items): array
    {
        return array_values($items
            ->map(fn (MealPlanItem|MealTemplateItem $item): array => [
                'recipe_id' => $item->recipe_id,
                'name' => $item->label(),
            ])
            ->all());
    }

    /** @return array<string, Type> */
    protected function mealItemsInputSchema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object([
                    'recipe_id' => $schema->integer()
                        ->description('The id of one of the team\'s recipes. Use the search-recipes tool to find ids.'),
                    'name' => $schema->string()->max(255)
                        ->description('Free text for a meal that is not a recipe, e.g. "Leftovers" or "Eat out". Ignored when recipe_id is given.'),
                ]))
                ->max(20)
                ->description('Every meal for the slot, in order, such as a main and its sides. These replace what is there now; pass [] to skip the meal.')
                ->required(),
        ];
    }

    protected function mealItemOutputSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'recipe_id' => $schema->integer()->nullable()->required()
                ->description('The recipe this meal links to, or null for a free-text meal.'),
            'name' => $schema->string()->required(),
        ]);
    }
}
