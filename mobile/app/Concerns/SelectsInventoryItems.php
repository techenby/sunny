<?php

namespace App\Concerns;

use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\NativeComponents\Inventory;
use App\Support\Toast;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\Layouts\Builders\TabBarOptions;
use Native\Mobile\Facades\Dialog;

trait SelectsInventoryItems
{
    public bool $selecting = false;

    /** @var list<int> */
    public array $selectedIds = [];

    public bool $movingSelected = false;

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, team_id: int, children_count: int}>
     */
    #[Computed]
    public function selectedItems(): array
    {
        return array_values(array_filter($this->children, fn (array $child): bool => in_array($child['id'], $this->selectedIds, true)));
    }

    public function startSelecting(): void
    {
        $this->selecting = true;
        $this->selectedIds = [];
        $this->forgetSelectedItems();
    }

    public function stopSelecting(): void
    {
        $this->selecting = false;
        $this->selectedIds = [];
        $this->movingSelected = false;
        $this->forgetSelectedItems();
    }

    public function toggleSelected(int $id): void
    {
        if (in_array($id, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
        } elseif (in_array($id, array_column($this->children, 'id'), true)) {
            $this->selectedIds = [...$this->selectedIds, $id];
        }

        $this->forgetSelectedItems();
    }

    public function toggleSelectAll(): void
    {
        $this->selectedIds = $this->selectedAll ? [] : array_column($this->children, 'id');
        $this->forgetSelectedItems();
    }

    #[Computed]
    public function selectedAll(): bool
    {
        return $this->children !== [] && count($this->selectedItems) === count($this->children);
    }

    public function startMoveSelected(): void
    {
        if ($this->selectedItems === []) {
            return;
        }

        $this->movingSelected = true;
        $this->parentId = $this->item['id'];
        unset($this->parentChoices, $this->parentPickerRows, $this->selectedParent, $this->browsedParent, $this->parentTrail);
        $this->openParentPicker();
    }

    public function moveSelected(): void
    {
        $items = array_values(array_filter($this->selectedItems, fn (array $item): bool => $item['parent_id'] !== $this->parentId));
        $this->movingSelected = false;

        if ($items === []) {
            return;
        }

        try {
            DB::transaction(function () use ($items): void {
                foreach ($items as $item) {
                    app(SunnyOutbox::class)->queue('items', $item['team_id'], [
                        'name' => $item['name'],
                        'type' => $item['type']->value,
                        'parent_id' => $this->parentId,
                        'metadata' => $item['metadata'],
                    ], $item['id']);
                }
            });
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to move them on this phone. Try again.';

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->error = '';
        $destination = $this->parentId === null ? null : Inventory::find($this->parentId);
        $this->stopSelecting();
        $this->refreshLocalSyncedData();

        $count = count($items);
        Toast::success(match (true) {
            $destination !== null => trans_choice('Moved :count thing to :place|Moved :count things to :place', $count, ['place' => $destination['name']]),
            $this->allLocations($items) => 'Now places in your home',
            default => trans_choice('Moved :count thing to “Not in a place yet”|Moved :count things to “Not in a place yet”', $count),
        });
    }

    public function confirmDeleteSelected(): void
    {
        $count = count($this->selectedItems);

        if ($count === 0) {
            return;
        }

        Dialog::alert(trans_choice('Delete :count thing?|Delete :count things?', $count), trans_choice(
            'The :count thing you selected will be deleted for everyone on your team. Anything inside it will move to “Not in a place yet”.|The :count things you selected will be deleted for everyone on your team. Anything inside them will move to “Not in a place yet”.',
            $count,
        ), [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Delete', 'style' => 'destructive'],
        ])->id('delete-selected')->show();
    }

    public function deleteSelected(): void
    {
        $items = $this->selectedItems;

        if ($items === []) {
            return;
        }

        try {
            DB::transaction(function () use ($items): void {
                foreach ($items as $item) {
                    app(SunnyOutbox::class)->delete('items', $item['team_id'], $item['id']);
                }
            });
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to delete them on this phone. Try again.';

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->error = '';
        $this->stopSelecting();
        $this->refreshLocalSyncedData();
        Toast::success(trans_choice('Deleted :count thing|Deleted :count things', count($items)));
    }

    public function tabBarOptions(): ?TabBarOptions
    {
        return $this->selecting ? TabBarOptions::make()->hidden() : null;
    }

    /**
     * @return array{title: string, subtitle: string, choosePrefix: string, topLevelLabel: string, hereId: int, hereLabel: string}
     */
    #[Computed]
    public function selectedMoveCopy(): array
    {
        $items = $this->selectedItems;
        $count = count($items);
        $names = array_column($items, 'name');

        return [
            'title' => $count === 1 ? 'Where does it go?' : 'Where do they go?',
            'subtitle' => 'Moving '.($count > 3 ? implode(', ', array_slice($names, 0, 2)).' and '.($count - 2).' more' : Arr::join($names, ', ', ' and ')),
            'choosePrefix' => $count === 1 ? 'Put it in' : 'Move '.$count.' to',
            'topLevelLabel' => match (true) {
                $this->allLocations($items) => $count === 1 ? 'Put it with the places in your home' : 'Put them with the places in your home',
                $count === 1 => 'Don’t put it in a place yet',
                default => 'Move '.$count.' to Not in a place yet',
            },
            'hereId' => $this->item['id'],
            'hereLabel' => $count === 1 ? 'It’s here now' : 'They’re here now',
        ];
    }

    protected function forgetSelectedItems(): void
    {
        unset($this->selectedItems, $this->selectedAll, $this->selectedMoveCopy);
    }

    /**
     * @return list<int>
     */
    protected function selectedWithContentsIds(): array
    {
        return array_values(array_unique(array_merge(
            ...array_map(fn (int $id): array => [$id, ...Inventory::descendantIdsOf($id)], array_column($this->selectedItems, 'id')),
        )));
    }

    /**
     * @param  list<array{type: ItemType}>  $items
     */
    protected function allLocations(array $items): bool
    {
        return $items !== [] && collect($items)->every(fn (array $item): bool => $item['type'] === ItemType::Location);
    }
}
