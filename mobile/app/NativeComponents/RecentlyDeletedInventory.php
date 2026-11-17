<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use App\Models\ItemPhoto;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

class RecentlyDeletedInventory extends Component
{
    use ChecksSunnySync;

    protected const TOAST_SECONDS = 5;

    public string $error = '';

    /** @var array{name: string, place: string, place_id: int|null, at: int}|null */
    public ?array $restored = null;

    /**
     * @return list<array{id: int, name: string, type: ItemType, photo: string|null, supporting: string, deleted: string, emptied: bool}>
     */
    #[Computed]
    public function items(): array
    {
        $items = Item::onlyTrashed()->forActiveTeam()->orderByDesc('deleted_at')->orderBy('name')->orderBy('id')->get();
        $places = Item::withTrashed()->forActiveTeam()->whereKey($items->pluck('parent_id')->filter()->unique())->pluck('name', 'id');
        $today = app(SunnyTeam::class)->current()?->today() ?? CarbonImmutable::today();

        return $items->map(fn (Item $item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type,
            'photo' => $item->thumb_path ?? $item->photo_url,
            'supporting' => $item->type->label().match (true) {
                $places->has($item->parent_id) => ' · was in '.$places[$item->parent_id],
                $item->type === ItemType::Location => '',
                default => ' · wasn’t in a place',
            },
            'deleted' => $this->deletedOn($item->deleted_at->setTimezone($today->getTimezone()), $today),
            'emptied' => $item->type !== ItemType::Item,
        ])->all();
    }

    /** @return array{name: string, place: string, place_id: int|null}|null */
    #[Computed]
    public function toast(): ?array
    {
        return $this->restored !== null && now()->getTimestamp() - $this->restored['at'] < self::TOAST_SECONDS ? $this->restored : null;
    }

    public function restore(int $id): void
    {
        $teamId = app(SunnyTeam::class)->current()?->id;

        try {
            $item = app(SunnyOutbox::class)->restore((int) $teamId, $id);
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to restore on this phone. Try again.';

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $parent = $item->parent_id === null ? null : Inventory::find($item->parent_id);

        $this->error = '';
        $this->restored = [
            'name' => $item->name,
            'place' => match (true) {
                $parent !== null => $parent['name'],
                $item->type === ItemType::Location => 'your home',
                default => '“Not in a place yet”',
            },
            'place_id' => $parent['id'] ?? null,
            'at' => now()->getTimestamp(),
        ];
        $this->refreshLocalSyncedData();
    }

    public function goThere(): void
    {
        if ($this->toast === null) {
            return;
        }

        if ($this->toast['place_id'] === null) {
            $this->back();

            return;
        }

        $this->navigate('/inventory/'.$this->toast['place_id']);
    }

    public function confirmDeleteForever(int $id): void
    {
        $item = collect($this->items)->firstWhere('id', $id);

        if ($item === null) {
            return;
        }

        $hasPhotos = $item['photo'] !== null || ItemPhoto::query()->forCurrentServer()->where('item_id', $id)->exists();

        Dialog::alert('Delete '.$item['name'].' forever?', '“'.$item['name'].'”'.($hasPhotos ? ' and its photos' : '').' will be deleted for everyone on your team. You can’t undo this.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Delete', 'style' => 'destructive'],
        ])->id('delete-forever-'.$id)->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($label === 'Delete' && $id !== null && preg_match('/^delete-forever-(-?\d+)$/', $id, $matches) === 1) {
            $this->deleteForever((int) $matches[1]);
        }
    }

    public function deleteForever(int $id): void
    {
        $teamId = app(SunnyTeam::class)->current()?->id;

        try {
            app(SunnyOutbox::class)->forceDelete((int) $teamId, $id);
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to delete on this phone. Try again.';

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->error = '';
        $this->refreshLocalSyncedData();
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
        unset($this->items, $this->toast);
    }

    protected function deletedOn(CarbonImmutable $deletedAt, CarbonImmutable $today): string
    {
        return match (true) {
            $deletedAt->isSameDay($today) => 'Today',
            $deletedAt->isSameDay($today->subDay()) => 'Yesterday',
            $deletedAt->isSameYear($today) => $deletedAt->format('j M'),
            default => $deletedAt->format('j M Y'),
        };
    }

    public function render(): View
    {
        return view('native.recently-deleted-inventory');
    }
}
