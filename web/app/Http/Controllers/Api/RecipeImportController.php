<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\ImportRecipeFromUrl;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecipeImportController extends Controller
{
    public function __invoke(ImportRecipeRequest $request, Team $team, ImportRecipeFromUrl $import, CreateRecipe $create): JsonResponse
    {
        $uuid = $request->validated('client_uuid');
        $existing = $uuid === null ? null : $team->recipes()->withTrashed()->firstWhere('client_uuid', $uuid);

        if ($existing !== null) {
            return RecipeResource::make($existing)->response();
        }

        try {
            $data = $import->handle($request->validated('url'));
        } catch (Throwable) {
            throw ValidationException::withMessages(['url' => 'Sunny couldn’t find a recipe at that address.']);
        }

        throw_if(blank($data['name'] ?? null), ValidationException::withMessages(['url' => 'Sunny couldn’t find a recipe at that address.']));

        return RecipeResource::make($create->handle($team, [...array_filter($data, fn (mixed $value): bool => $value !== null), 'client_uuid' => $uuid]))
            ->response()
            ->setStatusCode(201);
    }
}
