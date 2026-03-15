<?php

use App\Actions\Inventory\GenerateItemQrCode;
use App\Livewire\Forms\Inventory\ImportItemsForm;
use App\Livewire\Forms\Inventory\ItemForm;
use App\Livewire\Traits\WithSearching;
use App\Livewire\Traits\WithSorting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
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

    public string $qrCodeSvg = '';
    public string $qrCodeItemName = '';

    #[Url]
    public ?int $parentId = null;

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
            ->where('parent_id', $this->parentId)
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
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
        $this->form->reset();
        $this->form->fill(['parent_id' => $this->parentId]);
        $this->modal('item-form')->show();
    }

    public function delete(int $id): void
    {
        Auth::user()->currentTeam->items()->findOrFail($id)->delete();

        unset($this->items, $this->parentItems);
    }

    public function edit(int $id): void
    {
        $this->form->load(
            Auth::user()->currentTeam->items()->findOrFail($id)
        );
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

        $this->qrCodeSvg = app(GenerateItemQrCode::class)->handle($item);
        $this->qrCodeItemName = $item->name;
        $this->modal('qr-code')->show();
    }

    public function removeMetadata(int $index): void
    {
        $this->form->removeMetadata($index);
    }

    public function removePhoto(): void
    {
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
        $this->form->save();
        $this->modal('item-form')->close();
        unset($this->items, $this->parentItems);
    }
};
