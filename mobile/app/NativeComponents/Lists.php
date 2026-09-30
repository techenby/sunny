<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Enums\ChecklistType;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;

class Lists extends NativeComponent
{
    use ChecksSunnySync;

    public string $search = '';

    /**
     * @return array{id: int, team_id: int, user_id: int|null, type: ChecklistType, name: string, created_at: string, updated_at: string}|null
     */
    public static function find(int $id): ?array
    {
        $checklist = Checklist::forActiveTeam()->where(fn ($query) => $query->whereKey($id)->orWhere('local_id', $id))->first();

        return $checklist === null ? null : [...$checklist->toArray(), 'type' => $checklist->type];
    }

    /**
     * @return list<array{id: int, checklist_id: int, name: string, position: int, completed_at: string|null, completed_by: int|null, created_at: string, updated_at: string}>
     */
    public static function itemsOf(int $checklistId): array
    {
        return ChecklistItem::forCurrentServer()->where('checklist_id', $checklistId)->orderBy('position')->orderBy('id')->get()->toArray();
    }

    /**
     * @return list<array{id: int, name: string, type: ChecklistType, summary: string}>
     */
    #[Computed]
    public function lists(): array
    {
        return Checklist::forActiveTeam()
            ->withCount(['items', 'items as completed_items_count' => fn ($query) => $query->whereNotNull('completed_at')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Checklist $checklist): bool => $this->search === '' || Str::contains($checklist->name, $this->search, ignoreCase: true))
            ->map(fn (Checklist $checklist): array => [
                'id' => $checklist->id,
                'name' => $checklist->name,
                'type' => $checklist->type,
                'summary' => $checklist->type->label().' · '.($checklist->items_count === 0
                    ? 'No items'
                    : "{$checklist->completed_items_count} of {$checklist->items_count} done"),
            ])
            ->values()
            ->all();
    }

    public function updateSearch(string $query): void
    {
        $this->search = trim($query);
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status): void
    {
        if ($status === 'finished') {
            $this->refreshLocalSyncedData();
        }
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->lists);
    }

    public function render(): View
    {
        return view('native.lists');
    }
}
