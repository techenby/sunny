<?php

namespace App\Http\Integrations\Sunny;

use App\Models\Item;
use App\Models\PendingWrite;
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
            PendingWrite::query()->where(fn ($query) => $query->where('server', '!=', self::server())->orWhereNotIn('team_id', $teamIds))->delete();
            $pending = PendingWrite::query()->forCurrentServer()->get();

            foreach ($teams as $team) {
                Team::query()->updateOrCreate(['id' => $team['id']], ['server' => self::server(), 'name' => $team['name'], 'slug' => $team['slug'] ?? null]);
            }

            if (! Team::query()->where('server', self::server())->where('is_active', true)->exists()) {
                Team::query()->where('server', self::server())->orderBy('id')->first()?->update(['is_active' => true]);
            }

            foreach (['recipes' => Recipe::class, 'items' => Item::class] as $type => $model) {
                $pendingIds = $pending->where('resource', $type)->pluck('record_id');
                $pendingUuids = $pending->where('resource', $type)->pluck('client_uuid')->filter();
                $records = collect($snapshot[$type])->filter(fn (array $record): bool => empty($record['deleted_at']) && $teamIds->contains($record['team_id']));
                $model::query()->whereNotIn('id', $records->pluck('id')->merge($pendingIds))->delete();
                $records = $records->reject(fn (array $record): bool => $pendingIds->contains($record['id']) || $pendingUuids->contains($record['client_uuid'] ?? null));
                foreach ($records as $record) {
                    $this->saveRecord($type, $record);
                }
            }

            DB::table('sunny_sync_states')->updateOrInsert(['server' => self::server()], [
                'synced_at' => $snapshot['synced_at'],
                'fetched_at' => now(),
            ]);
        });
    }

    public function saveRecord(string $type, array $record): void
    {
        $fields = ['team_id', ...self::editableFields($type), 'photo_url', 'created_at', 'updated_at'];
        self::model($type)::query()->updateOrCreate(['id' => $record['id']], ['server' => self::server(), ...array_fill_keys($fields, null), ...Arr::only($record, $fields)]);
    }

    /**
     * Forget downloaded data, keeping changes that haven't reached Sunny yet. The next download drops any for teams the signed-in account can't reach.
     */
    public function clear(): void
    {
        DB::transaction(function (): void {
            $pending = PendingWrite::query()->get();
            Item::query()->whereNotIn('id', $pending->where('resource', 'items')->pluck('record_id'))->delete();
            Recipe::query()->whereNotIn('id', $pending->where('resource', 'recipes')->pluck('record_id'))->delete();
            Team::query()->whereNotIn('id', $pending->pluck('team_id'))->delete();
            DB::table('sunny_sync_states')->delete();
        });
        app(SunnyOutbox::class)->prunePhotos();
    }

    /**
     * The fields the forms edit, which a queued save writes straight onto the local record.
     *
     * @return list<string>
     */
    public static function editableFields(string $type): array
    {
        return $type === 'recipes'
            ? ['parent_id', 'name', 'source', 'servings', 'prep_time', 'cook_time', 'total_time', 'description', 'ingredients', 'instructions', 'notes', 'nutrition', 'tags']
            : ['parent_id', 'type', 'name', 'metadata'];
    }

    /** @return class-string<Recipe|Item> */
    public static function model(string $type): string
    {
        return $type === 'recipes' ? Recipe::class : Item::class;
    }

    public static function server(): string
    {
        return hash('sha256', rtrim((string) config('services.sunny.api_url'), '/'));
    }
}
