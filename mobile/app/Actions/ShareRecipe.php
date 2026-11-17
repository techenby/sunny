<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\Requests\ShareRecipeRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Team;

class ShareRecipe
{
    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Team $team, int $recipeId, bool $shared): ?array
    {
        $record = $this->auth->authenticatedConnector()
            ->send(new ShareRecipeRequest($team->slug, $recipeId, $shared))
            ->json('data');

        if (! is_array($record) || ($record['id'] ?? null) !== $recipeId || ($record['team_id'] ?? null) !== $team->id
            || ($shared && ! is_string($record['share_url'] ?? null))) {
            return null;
        }

        $this->store->saveRecord('recipes', $record);

        return $record;
    }
}
