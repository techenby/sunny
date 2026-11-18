<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\Requests\DuplicateItemRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class DuplicateItem
{
    public const MAX_COPIES = 25;

    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
    ) {}

    public function handle(Team $team, int $itemId, int $count): int
    {
        $records = $this->auth->authenticatedConnector()
            ->send(new DuplicateItemRequest($team->slug, $itemId, $count))
            ->json('data');

        $copies = collect(is_array($records) ? $records : [])
            ->filter(fn (mixed $record): bool => is_array($record) && is_int($record['id'] ?? null) && $record['id'] > 0 && ($record['team_id'] ?? null) === $team->id)
            ->values();

        DB::transaction(fn () => $copies->each(fn (array $record) => $this->store->saveRecord('items', $record)));

        return $copies->count();
    }
}
