<?php

use App\Actions\Inventory\DuplicateItem;
use App\Actions\Inventory\GenerateItemQrCode;
use App\Actions\Inventory\MoveItemToTeam;
use App\Enums\ItemType;
use App\Livewire\Forms\Inventory\ItemForm;
use App\Models\Item;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public Item $item;

    public ItemForm $form;

    public ?int $moveToTeamId = null;

    public int $duplicateCount = 1;

    public ?array $qrCode = null;

    public ?int $moveParentId = null;

    #[Computed]
    public function breadcrumbs(): BaseCollection
    {
        $breadcrumbs = collect();
        $current = $this->item->parent_id
            ? Auth::user()->currentTeam->items()->find($this->item->parent_id)
            : null;

        while ($current) {
            $breadcrumbs->prepend($current);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    #[Computed]
    public function children(): LengthAwarePaginator
    {
        return $this->item->children()
            ->withCount('children')
            ->orderBy('name')
            ->paginate(10);
    }

    /** @return Collection<int, Item> */
    #[Computed]
    public function moveOptions(): Collection
    {
        return Auth::user()->currentTeam->items()
            ->with('parent')
            ->whereNotIn('id', [$this->item->id, ...$this->item->descendantIds()])
            ->orderBy('name')
            ->get();
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

    public function addMetadata(): void
    {
        $this->form->addMetadata();
    }

    public function create(): void
    {
        $this->authorize('create', Item::class);

        $this->form->reset();
        $this->form->fill(['parent_id' => $this->item->id, 'type' => ItemType::Item->value]);
        $this->modal('item-form')->show();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->item);

        $parentId = $this->item->parent_id;

        $this->item->purge();

        $this->redirectRoute('inventory.index', ['parentId' => $parentId]);
    }

    public function duplicate(): void
    {
        $this->validate([
            'duplicateCount' => ['required', 'integer', 'min:1', 'max:25'],
        ]);

        $this->authorize('create', Item::class);

        $copies = (new DuplicateItem)->handle($this->item, $this->duplicateCount);

        $this->modal('duplicate-item')->close();
        $this->reset('duplicateCount');

        Flux::toast(trans_choice(':count copy made.|:count copies made.', $copies->count()));
    }

    public function edit(): void
    {
        $this->authorize('update', $this->item);

        $this->form->load($this->item);
        $this->modal('item-form')->show();
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

    public function startMove(): void
    {
        $this->authorize('update', $this->item);

        $this->moveParentId = $this->item->parent_id;
        $this->modal('move-to-place')->show();
    }

    public function move(): void
    {
        $this->authorize('update', $this->item);

        $this->validate([
            'moveParentId' => ['nullable', 'integer', Rule::in($this->moveOptions->modelKeys())],
        ]);

        $this->item->update(['parent_id' => $this->moveParentId]);

        $this->modal('move-to-place')->close();
        $this->reset('moveParentId');
        unset($this->breadcrumbs, $this->moveOptions);

        Flux::toast(match (true) {
            $this->item->parent_id !== null => __('Moved to :place.', ['place' => $this->breadcrumbs->last()->name]),
            $this->item->type === ItemType::Location => __('Now one of the places in your home.'),
            default => __('Moved to “Not in a place yet”.'),
        });
    }

    public function moveToTeam(): void
    {
        $this->validate([
            'moveToTeamId' => ['required', 'integer', Rule::exists('team_members', 'team_id')->where('user_id', Auth::id())],
        ]);

        $this->authorize('move', $this->item);

        $team = $this->otherTeams->firstWhere('id', $this->moveToTeamId);

        (new MoveItemToTeam)->handle($this->item, $team);

        $this->redirectRoute('inventory.index');
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
        $this->item->refresh();
        unset($this->breadcrumbs, $this->children, $this->parentItems, $this->moveOptions);
    }

    public function showQrCode(): void
    {
        $this->authorize('view', $this->item);

        $this->qrCode = resolve(GenerateItemQrCode::class)->handle($this->item);

        $this->modal('qr-code')->show();
    }
};
