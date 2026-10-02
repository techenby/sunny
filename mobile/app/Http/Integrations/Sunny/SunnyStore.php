<?php

namespace App\Http\Integrations\Sunny;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Item;
use App\Models\PendingWrite;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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

    /** @param array{teams: array, recipes: array, items: array, checklists: array, checklist_items: array, routines: array, routine_occurrences: array, synced_at: string} $snapshot */
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
                Team::query()->updateOrCreate(['id' => $team['id']], ['server' => self::server(), 'name' => $team['name'], 'slug' => $team['slug'] ?? null, 'timezone' => $team['timezone'] ?? null]);
            }

            if (! Team::query()->where('server', self::server())->where('is_active', true)->exists()) {
                Team::query()->where('server', self::server())->orderBy('id')->first()?->update(['is_active' => true]);
            }

            foreach (['recipes', 'items', 'checklists', 'checklist_items', 'routines'] as $type) {
                $model = self::model($type);
                $pendingIds = $pending->where('resource', $type)->pluck('record_id');
                $pendingUuids = $pending->where('resource', $type)->pluck('client_uuid')->filter();
                [$ownerKey, $ownerIds] = $type === 'checklist_items' ? ['checklist_id', Checklist::query()->pluck('id')] : ['team_id', $teamIds];
                $records = collect($snapshot[$type])->filter(fn (array $record): bool => empty($record['deleted_at']) && $ownerIds->contains($record[$ownerKey]));
                $model::query()->whereNotIn('id', $records->pluck('id')->merge($pendingIds))->delete();
                $records = $records->reject(fn (array $record): bool => $pendingIds->contains($record['id']) || $pendingUuids->contains($record['client_uuid'] ?? null));
                foreach ($records as $record) {
                    $this->saveRecord($type, $record);
                }
            }

            $orphans = ChecklistItem::query()->whereNotIn('checklist_id', Checklist::query()->select('id'))->pluck('id');
            PendingWrite::query()->where('resource', 'checklist_items')->whereIn('record_id', $orphans)->delete();
            ChecklistItem::query()->whereKey($orphans)->delete();

            $this->saveRoutineOccurrences(
                collect($snapshot['routine_occurrences'])->filter(fn (array $occurrence): bool => $teamIds->contains($occurrence['routine']['team_id'])),
                $pending->where('resource', 'routine_occurrence_steps')->pluck('record_id'),
            );

            DB::table('sunny_sync_states')->updateOrInsert(['server' => self::server()], [
                'synced_at' => $snapshot['synced_at'],
                'fetched_at' => now(),
            ]);
        });
    }

    public function saveRecord(string $type, array $record): void
    {
        if ($type === 'routines') {
            $record['assignee'] = $record['user']['name'] ?? null;
            $record['steps'] = array_map(fn (array $step): array => Arr::only($step, ['id', 'name']), $record['steps'] ?? []);
        }

        $fields = self::storedFields($type);
        self::model($type)::query()->updateOrCreate(['id' => $record['id']], ['server' => self::server(), ...array_fill_keys($fields, null), ...Arr::only($record, $fields)]);
    }

    /**
     * Clear local data for a new sign-in. Changes kept from an earlier session only survive when the same account signs back in.
     */
    public function startSession(?int $userId): void
    {
        $previous = DB::table('sunny_accounts')->where('server', self::server())->value('user_id');

        if ($userId === null || $previous === null || (int) $previous !== $userId) {
            PendingWrite::query()->forCurrentServer()->delete();
        }

        $this->clear();

        if ($userId !== null) {
            $this->rememberAccount($userId);
        }
    }

    public function accountId(): ?int
    {
        $userId = DB::table('sunny_accounts')->where('server', self::server())->value('user_id');

        return $userId === null ? null : (int) $userId;
    }

    public function rememberAccount(int $userId): void
    {
        DB::table('sunny_accounts')->updateOrInsert(['server' => self::server()], ['user_id' => $userId]);
    }

    /**
     * Forget downloaded data, keeping changes that haven't reached Sunny yet. The next download drops any for teams the signed-in account can't reach.
     */
    public function clear(): void
    {
        DB::transaction(function (): void {
            $pending = PendingWrite::query()->get();
            RoutineOccurrenceStep::query()->delete();
            RoutineOccurrence::query()->delete();
            Item::query()->whereNotIn('id', $pending->where('resource', 'items')->pluck('record_id'))->delete();
            Recipe::query()->whereNotIn('id', $pending->where('resource', 'recipes')->pluck('record_id'))->delete();
            Routine::query()->whereNotIn('id', $pending->where('resource', 'routines')->pluck('record_id'))->delete();
            ChecklistItem::query()->whereNotIn('id', $pending->where('resource', 'checklist_items')->pluck('record_id'))->delete();
            Checklist::query()->whereNotIn('id', $pending->where('resource', 'checklists')->pluck('record_id')
                ->merge($pending->where('resource', 'checklist_items')->pluck('payload.checklist_id')))->delete();
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
        return match ($type) {
            'recipes' => ['parent_id', 'name', 'source', 'servings', 'prep_time', 'cook_time', 'total_time', 'description', 'ingredients', 'instructions', 'notes', 'nutrition', 'tags'],
            'items' => ['parent_id', 'type', 'name', 'metadata'],
            'checklists' => ['type', 'name'],
            'checklist_items' => ['checklist_id', 'name'],
            'routines' => ['user_id', 'name', 'time_of_day', 'frequency', 'weekdays', 'day_of_month', 'is_active', 'steps'],
        };
    }

    /** @return list<string> */
    public static function storedFields(string $type): array
    {
        return match ($type) {
            'recipes', 'items' => ['team_id', ...self::editableFields($type), 'photo_url', 'created_at', 'updated_at'],
            'checklists' => ['team_id', 'user_id', ...self::editableFields($type), 'created_at', 'updated_at'],
            'checklist_items' => [...self::editableFields($type), 'position', 'completed_at', 'completed_by', 'created_at', 'updated_at'],
            'routines' => ['team_id', ...self::editableFields($type), 'assignee', 'starts_on', 'created_at', 'updated_at'],
        };
    }

    /** @return class-string<Recipe|Item|Checklist|ChecklistItem|Routine> */
    public static function model(string $type): string
    {
        return match ($type) {
            'recipes' => Recipe::class,
            'items' => Item::class,
            'checklists' => Checklist::class,
            'checklist_items' => ChecklistItem::class,
            'routines' => Routine::class,
        };
    }

    public static function inTeam(string $type, int $teamId): Builder
    {
        $query = self::model($type)::query()->where('server', self::server());

        return $type === 'checklist_items'
            ? $query->whereIn('checklist_id', self::inTeam('checklists', $teamId)->select('id'))
            : $query->where('team_id', $teamId);
    }

    public static function server(): string
    {
        return hash('sha256', rtrim((string) config('services.sunny.api_url'), '/'));
    }

    /**
     * @param  Collection<int, array>  $occurrences
     * @param  Collection<int, int>  $pendingStepIds
     */
    private function saveRoutineOccurrences(Collection $occurrences, Collection $pendingStepIds): void
    {
        $pendingTicks = RoutineOccurrenceStep::query()->whereIn('id', $pendingStepIds)->pluck('completed_at', 'id');
        RoutineOccurrenceStep::query()->delete();
        RoutineOccurrence::query()->delete();

        foreach ($occurrences as $occurrence) {
            RoutineOccurrence::query()->create([
                'id' => $occurrence['id'],
                'server' => self::server(),
                'team_id' => $occurrence['routine']['team_id'],
                'routine_id' => $occurrence['routine_id'],
                'due_on' => $occurrence['due_on'],
                'name' => $occurrence['routine']['name'],
                'time_of_day' => $occurrence['routine']['time_of_day'],
                'assignee' => $occurrence['routine']['user']['name'] ?? null,
            ]);

            foreach ($occurrence['steps'] as $step) {
                RoutineOccurrenceStep::query()->create([
                    'id' => $step['id'],
                    'server' => self::server(),
                    'routine_occurrence_id' => $occurrence['id'],
                    'name' => $step['name'],
                    'position' => $step['position'] ?? null,
                    'completed_at' => $pendingTicks->has($step['id']) ? $pendingTicks[$step['id']] : $step['completed_at'],
                ]);
            }
        }
    }
}
