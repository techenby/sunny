<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\Requests\MoveItemRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Item;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class MoveItemToTeam
{
    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
    ) {}

    /**
     * @return list<int>|null
     */
    public function handle(Team $from, int $itemId, Team $to): ?array
    {
        $response = $this->auth->authenticatedConnector()->send(new MoveItemRequest($from->slug, $itemId, $to->id));
        $record = $response->json('data');
        $movedIds = $response->json('moved_item_ids');

        if (! is_array($record) || ($record['id'] ?? null) !== $itemId || ($record['team_id'] ?? null) !== $to->id || ! is_array($movedIds)) {
            return null;
        }

        $ids = collect($movedIds)->filter(fn (mixed $id): bool => is_int($id))->push($itemId)->unique()->values()->all();

        DB::transaction(function () use ($record, $ids, $to): void {
            Item::query()->forCurrentServer()->whereIn('id', $ids)->update(['team_id' => $to->id]);
            $this->store->saveRecord('items', $record);
        });

        return $ids;
    }
}
