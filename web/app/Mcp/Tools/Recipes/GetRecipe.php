<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Fetch the full details of a single recipe from the current team by id or slug, including its ingredients and instructions as HTML lists, source, notes, and nutrition information.')]
class GetRecipe extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required_without:slug', 'nullable', 'integer'],
            'slug' => ['required_without:id', 'nullable', 'string'],
        ], [
            'id.required_without' => 'You must provide either an id or a slug to identify the recipe.',
            'slug.required_without' => 'You must provide either an id or a slug to identify the recipe.',
        ]);

        $recipe = $request->user()->currentTeam->recipes()
            ->when(
                isset($validated['id']),
                fn ($builder) => $builder->whereKey($validated['id']),
                fn ($builder) => $builder->where('slug', $validated['slug']),
            )
            ->first();

        if (! $recipe) {
            return Response::error('Recipe not found.');
        }

        Gate::forUser($request->user())->authorize('view', $recipe);

        return Response::structured([
            ...$recipe->only([
                'id', 'name', 'slug', 'description', 'ingredients', 'instructions',
                'servings', 'prep_time', 'cook_time', 'total_time', 'notes', 'source',
                'nutrition', 'tags', 'parent_id',
            ]),
            'created_at' => $recipe->created_at->toIso8601String(),
            'updated_at' => $recipe->updated_at->toIso8601String(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the recipe to fetch. Provide either id or slug.'),
            'slug' => $schema->string()
                ->description('The slug of the recipe to fetch. Provide either id or slug.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'slug' => $schema->string()->required(),
            'description' => $schema->string()->nullable()->required(),
            'ingredients' => $schema->string()->nullable()->required()
                ->description('HTML <ul> list of ingredients.'),
            'instructions' => $schema->string()->nullable()->required()
                ->description('HTML <ol> list of steps.'),
            'servings' => $schema->string()->nullable()->required(),
            'prep_time' => $schema->string()->nullable()->required(),
            'cook_time' => $schema->string()->nullable()->required(),
            'total_time' => $schema->string()->nullable()->required(),
            'notes' => $schema->string()->nullable()->required(),
            'source' => $schema->string()->nullable()->required(),
            'nutrition' => $schema->string()->nullable()->required(),
            'tags' => $schema->array()->items($schema->string())->nullable()->required(),
            'parent_id' => $schema->integer()->nullable()->required(),
            'created_at' => $schema->string()->required(),
            'updated_at' => $schema->string()->required(),
        ];
    }
}
