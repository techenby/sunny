<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\Requests\SaveSharedRecipeRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\PendingWrite;
use App\Models\Team;

class SaveSharedRecipe
{
    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
    ) {}

    /**
     * @return array{id: int, created: bool}|null
     */
    public function handle(string $shareToken, Team $team, string $clientUuid): ?array
    {
        $response = $this->auth->authenticatedConnector()->send(new SaveSharedRecipeRequest($shareToken, $team->id, $clientUuid));
        $record = $response->json('data');

        if (! is_array($record) || ! is_int($record['id'] ?? null) || $record['id'] <= 0 || ($record['team_id'] ?? null) !== $team->id) {
            return null;
        }

        if (! PendingWrite::query()->for('recipes', $record['id'])->exists()) {
            $this->store->saveRecord('recipes', $record);
        }

        return ['id' => $record['id'], 'created' => $response->status() === 201];
    }
}
