<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use App\Models\Recipe;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("Search the current team's recipes by name, ingredient, or tag. Returns a compact list; use the get-recipe tool for full details of a specific recipe.")]
class SearchRecipes extends Tool
{
    public function handle(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', Recipe::class);

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $recipes = $request->user()->currentTeam->recipes()
            ->when($validated['query'] ?? null, function ($builder, string $query) {
                $builder->where(function ($builder) use ($query) {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('ingredients', 'like', "%{$query}%");
                });
            })
            ->when($validated['tag'] ?? null, fn ($builder, string $tag) => $builder->whereJsonContains('tags', $tag))
            ->latest()
            ->limit((int) ($validated['limit'] ?? 20))
            ->get(['id', 'name', 'slug', 'tags', 'servings', 'total_time']);

        return Response::text(json_encode([
            'count' => $recipes->count(),
            'recipes' => $recipes->map(fn (Recipe $recipe) => $recipe->only([
                'id', 'name', 'slug', 'tags', 'servings', 'total_time',
            ]))->all(),
            'note' => 'Use the get-recipe tool with an id or slug to fetch full recipe details.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Text to match against recipe names and ingredients (partial matches allowed).'),
            'tag' => $schema->string()
                ->description('Only return recipes with this exact tag, e.g. "Dinner" or "Vegetarian".'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(100)
                ->default(20)
                ->description('Maximum number of recipes to return (default 20, max 100).'),
        ];
    }
}
