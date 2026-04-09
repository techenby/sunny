<?php

use App\Actions\Inventory\GenerateItemQrCode;
use App\Actions\Inventory\MoveItemToTeam;
use App\Livewire\Forms\Inventory\ImportItemsForm;
use App\Livewire\Forms\Inventory\ItemForm;
use App\Livewire\Traits\WithSearching;
use App\Livewire\Traits\WithSorting;
use App\Models\Item;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Inventory')] class extends Component
{
    use WithFileUploads;
    use WithPagination;
    use WithSearching;
    use WithSorting;

    public ItemForm $form;
    public ImportItemsForm $importForm;

    public ?int $moveItemId = null;

    public ?int $moveToTeamId = null;

    public ?array $qrCode = null;

    /** @var array<int, int> */
    public array $selected = [];

    public ?int $bulkParentId = null;

    #[Url]
    public array $filters = [
        'types' => [],
        'showTrashed' => false,
    ];

    #[Url]
    public ?int $parentId = null;

    #[Computed]
    public function areFiltersActive(): bool
    {
        return collect($this->filters)->contains(true);
    }

    #[Computed]
    public function breadcrumbs(): BaseCollection
    {
        $breadcrumbs = collect();
        $current = $this->parentId
            ? Auth::user()->currentTeam->items()->find($this->parentId)
            : null;

        while ($current) {
            $breadcrumbs->prepend($current);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam
            ->items()
            ->withCount('children')
            ->when($this->filters['showTrashed'] ?? false, fn ($query) => $query->onlyTrashed())
            ->when(filled($this->filters['types']), fn ($query) => $query->whereIn('type', $this->filters['types']))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->where('parent_id', $this->parentId)
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    /** @return Collection<int, Team> */
    #[Computed]
    public function otherTeams(): Collection
    {
        return Auth::user()->teams->where('id', '!=', Auth::user()->current_team_id)->values();
    }

    #[Computed]
    public function parentItems(): Collection
    {
        return Auth::user()->currentTeam->items()
            ->when($this->form->editingItem, fn ($query) => $query->where('id', '!=', $this->form->editingItem->id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function bulkParentOptions(): Collection
    {
        $excludedIds = collect($this->selected);

        $selectedItems = Auth::user()->currentTeam->items()
            ->whereIn('id', $this->selected)
            ->get();

        foreach ($selectedItems as $item) {
            $excludedIds = $excludedIds->merge($item->descendantIds());
        }

        return Auth::user()->currentTeam->items()
            ->whereNotIn('id', $excludedIds->unique()->all())
            ->orderBy('name')
            ->get();
    }

    public function openBulkUpdateParentModal(): void
    {
        $this->bulkParentId = $this->parentId;
        $this->modal('bulk-update-parent')->show();
    }

    public function updateParent(): void
    {
        $this->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer'],
            'bulkParentId' => ['nullable', 'integer', 'exists:items,id'],
        ]);

        $items = Auth::user()->currentTeam->items()
            ->whereIn('id', $this->selected)
            ->get();

        foreach ($items as $item) {
            $this->authorize('update', $item);
            $item->update(['parent_id' => $this->bulkParentId]);
        }

        $this->modal('bulk-update-parent')->close();
        $this->reset('selected', 'bulkParentId');

        unset($this->items, $this->parentItems, $this->bulkParentOptions);

        Flux::toast(__(':count item(s) updated.', ['count' => $items->count()]));
    }

    public function openMoveModal(int $itemId): void
    {
        $this->moveItemId = $itemId;
        $this->moveToTeamId = null;
        $this->modal('move-item')->show();
    }

    public function moveToTeam(): void
    {
        $this->validate([
            'moveItemId' => ['required', 'integer'],
            'moveToTeamId' => ['required', 'integer', Rule::exists('team_members', 'team_id')->where('user_id', Auth::id())],
        ]);

        $item = Auth::user()->currentTeam->items()->findOrFail($this->moveItemId);
        $this->authorize('move', $item);

        $team = $this->otherTeams->firstWhere('id', $this->moveToTeamId);

        (new MoveItemToTeam)->handle($item, $team);

        $this->modal('move-item')->close();
        $this->reset('moveItemId', 'moveToTeamId');

        unset($this->items);

        Flux::toast(__('Item moved successfully.'));
    }

    public function addMetadata(): void
    {
        $this->form->addMetadata();
    }

    public function create(): void
    {
        $this->form->reset();
        $this->form->fill(['parent_id' => $this->parentId]);
        $this->modal('item-form')->show();
    }

    public function delete(int $id): void
    {
        $item = Auth::user()->currentTeam->items()->findOrFail($id);

        $this->authorize('delete', $item);

        $item->purge();

        unset($this->items, $this->parentItems);
    }

    public function bulkDelete(): void
    {
        $items = Auth::user()->currentTeam->items()
            ->whereIn('id', $this->selected)
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        foreach ($items as $item) {
            $this->authorize('delete', $item);
            $item->purge();
        }

        $this->reset('selected');

        unset($this->items, $this->parentItems);

        Flux::toast(__(':count item(s) deleted.', ['count' => $items->count()]));
    }

    public function bulkRestore(): void
    {
        $items = Auth::user()->currentTeam->items()
            ->onlyTrashed()
            ->whereIn('id', $this->selected)
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        foreach ($items as $item) {
            $this->authorize('restore', $item);
            $item->restore();
        }

        $this->reset('selected');

        unset($this->items, $this->parentItems);

        Flux::toast(__(':count item(s) restored.', ['count' => $items->count()]));
    }

    public function restore(int $id): void
    {
        $item = Auth::user()->currentTeam->items()->onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $item);

        $item->restore();

        unset($this->items, $this->parentItems);
    }

    public function forceDelete(int $id): void
    {
        $item = Auth::user()->currentTeam->items()->onlyTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $item);

        $item->forceDelete();

        unset($this->items, $this->parentItems);
    }

    public function edit(int $id): void
    {
        $item = Auth::user()->currentTeam->items()->findOrFail($id);

        $this->authorize('update', $item);

        $this->form->load($item);
        $this->modal('item-form')->show();
    }

    public function import(): void
    {
        $this->importForm->process($this->parentId);

        unset($this->items, $this->parentItems);
        $this->modal('import-items')->close();
    }

    public function navigateDown(int $id): void
    {
        $item = $this->items->firstWhere('id', $id);
        if ($item !== null && $item->children_count === 0) {
            $this->redirectRoute('inventory.show', ['item' => $item]);

            return;
        }

        $this->parentId = $id;
        unset($this->items, $this->parentItems, $this->breadcrumbs);
    }

    public function navigateUp(): void
    {
        if ($this->parentId) {
            $parent = Auth::user()->currentTeam->items()->find($this->parentId);
            $this->parentId = $parent?->parent_id;
            unset($this->items, $this->parentItems, $this->breadcrumbs);
        }
    }

    public function showQrCode(int $id): void
    {
        $item = Auth::user()->currentTeam->items()->findOrFail($id);

        $this->authorize('view', $item);

        $this->qrCode = resolve(GenerateItemQrCode::class)->handle($item);

        $this->modal('qr-code')->show();
    }

    public function removeMetadata(int $index): void
    {
        if ($this->form->editingItem) {
            $this->authorize('update', $this->form->editingItem);
        }

        $this->form->removeMetadata($index);
    }

    public function removePhoto(): void
    {
        if ($this->form->editingItem) {
            $this->authorize('update', $this->form->editingItem);
        }

        if ($this->form->photo) {
            $this->form->photo->delete();
            $this->form->photo = null;
        } elseif ($this->form->existingPhotoUrl) {
            $this->form->existingPhotoUrl = null;
            $this->form->removePhoto = true;
        }
    }

    public function save(): void
    {
        if ($this->form->editingItem) {
            $this->authorize('update', $this->form->editingItem);
        } else {
            $this->authorize('create', Item::class);
        }

        $this->form->save();
        $this->modal('item-form')->close();
        unset($this->items, $this->parentItems);
    }
};
