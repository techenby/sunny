<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\Requests\CopyRecipeRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Team;

class CopyRecipeToTeam
{
    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Team $from, int $recipeId, Team $to, string $clientUuid): ?array
    {
        $record = $this->auth->authenticatedConnector()
            ->send(new CopyRecipeRequest($from->slug, $recipeId, $to->id, $clientUuid))
            ->json('data');

        if (! is_array($record) || ! is_int($record['id'] ?? null) || $record['id'] <= 0 || ($record['team_id'] ?? null) !== $to->id) {
            return null;
        }

        $this->store->saveRecord('recipes', $record);

        return $record;
    }
}
