<?php

namespace App\Http\Integrations\Sunny;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SunnyStore
{
    public function lastSyncedAt(): ?string
    {
        return DB::table('sunny_sync_states')->where('server', self::server())->value('synced_at');
    }

    public function isStale(): bool
    {
        $fetchedAt = DB::table('sunny_sync_states')->where('server', self::server())->value('fetched_at');

        return $fetchedAt === null || CarbonImmutable::parse($fetchedAt)->lessThan(now()->subMinutes(5));
    }

    /** @param array{teams: array, recipes: array, items: array, synced_at: string} $snapshot */
    public function applySnapshot(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot): void {
            $teams = collect($snapshot['teams'])->filter(fn (array $team): bool => empty($team['deleted_at']));
            $teamIds = $teams->pluck('id');
            Team::query()->where('server', '!=', self::server())->delete();
            Team::query()->whereNotIn('id', $teamIds)->delete();

            foreach ($teams as $team) {
                Team::query()->updateOrCreate(['id' => $team['id']], ['server' => self::server(), 'name' => $team['name']]);
            }

            foreach (['recipes' => Recipe::class, 'items' => Item::class] as $type => $model) {
                $records = collect($snapshot[$type])->filter(fn (array $record): bool => empty($record['deleted_at']) && $teamIds->contains($record['team_id']));
                $model::query()->whereNotIn('id', $records->pluck('id'))->delete();
                $fields = $type === 'recipes'
                    ? ['team_id', 'parent_id', 'name', 'source', 'servings', 'prep_time', 'cook_time', 'total_time', 'description', 'ingredients', 'instructions', 'notes', 'nutrition', 'tags', 'created_at', 'updated_at']
                    : ['team_id', 'parent_id', 'type', 'name', 'metadata', 'created_at', 'updated_at'];

                foreach ($records as $record) {
                    $model::query()->updateOrCreate(['id' => $record['id']], ['server' => self::server(), ...Arr::only($record, $fields)]);
                }
            }

            DB::table('sunny_sync_states')->updateOrInsert(['server' => self::server()], [
                'synced_at' => $snapshot['synced_at'],
                'fetched_at' => now(),
            ]);
        });
    }

    public function clear(): void
    {
        DB::transaction(function (): void {
            Item::query()->delete();
            Recipe::query()->delete();
            Team::query()->delete();
            DB::table('sunny_sync_states')->delete();
        });
    }

    public static function server(): string
    {
        return hash('sha256', rtrim((string) config('services.sunny.api_url'), '/'));
    }
}
