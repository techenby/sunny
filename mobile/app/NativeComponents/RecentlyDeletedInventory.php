<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Icons\Android;
use App\Icons\Ios;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Support\Toast;
use App\Support\ToastAction;
use App\Ui\Menu;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Elements\TopBarAction;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

class RecentlyDeletedInventory extends Component
{
    use ChecksSunnySync;

    public string $error = '';

    /**
     * @return list<array{id: int, name: string, type: ItemType, photo: string|null, supporting: string, deleted: string, emptied: bool}>
     */
    #[Computed]
    public function items(): array
    {
        $items = Item::onlyTrashed()->forActiveTeam()->orderByDesc('deleted_at')->orderBy('name')->orderBy('id')->get();
        $places = Item::withTrashed()->forActiveTeam()->whereKey($items->pluck('parent_id')->filter()->unique())->pluck('name', 'id');
        $today = app(SunnyTeam::class)->current()?->today() ?? CarbonImmutable::today();

        return $items->map(function (Item $item) use ($places, $today): array {
            $deleted = $this->deletedOn($item->deleted_at->setTimezone($today->getTimezone()), $today);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'photo' => $item->thumb_path ?? $item->photo_url,
                'supporting' => $places->has($item->parent_id) ? 'Was in '.$places[$item->parent_id].' · '.$deleted : $deleted,
                'deleted' => $deleted,
                'emptied' => $item->type !== ItemType::Item,
            ];
        })->all();
    }

    /**
     * @param  array{id: int, name: string}  $item
     * @return list<TopBarAction>
     */
    public function itemMenu(array $item): array
    {
        return [
            Menu::item('deleted-restore-'.$item['id'], 'Restore', Ios::ArrowUturnBackward, Android::Undo, 'restore('.$item['id'].')', 'Restore '.$item['name']),
            Menu::delete('deleted-forever-'.$item['id'], 'Delete forever', 'confirmDeleteForever('.$item['id'].')', 'Delete '.$item['name'].' forever'),
        ];
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
        $this->refreshLocalSyncedData();

        $place = match (true) {
            $parent !== null => $parent['name'],
            $item->type === ItemType::Location => 'your home',
            default => '“Not in a place yet”',
        };

        Toast::success("Restored {$item->name} to {$place}", action: new ToastAction('Go there', 'go-to-restored', [
            'url' => $parent === null ? '/inventory' : '/inventory/'.$parent['id'],
        ]));
    }

    #[On(Toast::ACTION_PRESSED, when: ['actionId' => 'go-to-restored'])]
    public function goToRestored(array $data): void
    {
        if ($data['url'] === '/inventory') {
            $this->back();

            return;
        }

        $this->navigate($data['url']);
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
        unset($this->items);
    }

    protected function deletedOn(CarbonImmutable $deletedAt, CarbonImmutable $today): string
    {
        return match (true) {
            $deletedAt->isSameDay($today) => 'Deleted today',
            $deletedAt->isSameDay($today->subDay()) => 'Deleted yesterday',
            $deletedAt->isSameYear($today) => 'Deleted '.$deletedAt->format('j M'),
            default => 'Deleted '.$deletedAt->format('j M Y'),
        };
    }

    public function render(): View
    {
        return view('native.recently-deleted-inventory');
    }
}
