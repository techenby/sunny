<?php

declare(strict_types=1);

namespace App\Actions\MealPlanning;

use App\Models\Recipe;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The recipes planned between two dates with their ingredient lines, for the
 * shopping list review. A recipe planned more than once is listed once with
 * a count; free-text meals and recipes without ingredients are left out.
 */
class CollectMealIngredients
{
    /**
     * @return Collection<int, array{recipe: Recipe, count: int, lines: non-empty-list<string>}>
     */
    public function handle(Team $team, CarbonInterface $from, CarbonInterface $through): Collection
    {
        $recipes = [];
        $counts = [];

        foreach ((new ResolveMealPlan)->handle($team, $from, $through) as $day) {
            foreach ($day['slots'] as $cell) {
                foreach ($cell['items'] as $item) {
                    if ($item->recipe instanceof Recipe) {
                        $recipes[$item->recipe->id] = $item->recipe;
                        $counts[$item->recipe->id] = ($counts[$item->recipe->id] ?? 0) + 1;
                    }
                }
            }
        }

        return collect($recipes)
            ->map(fn (Recipe $recipe, int $id): array => [
                'recipe' => $recipe,
                'count' => $counts[$id],
                'lines' => $recipe->ingredientLines(),
            ])
            ->filter(fn (array $group): bool => $group['lines'] !== [])
            ->sortBy(fn (array $group): string => $group['recipe']->name)
            ->values();
    }
}
