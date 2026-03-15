<?php

use App\Actions\Inventory\GenerateItemQrCode;
use App\Livewire\Forms\Inventory\ItemForm;
use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

new #[Title('Inventory: Item')] class extends Component
{
    use WithFileUploads;

    public Item $item;

    public ItemForm $form;

    public ?array $qrCode = null;

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
    public function parentItems(): Collection
    {
        return Auth::user()->currentTeam->items()
            ->where('id', '!=', $this->item->id)
            ->orderBy('name')
            ->get();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->item);

        $this->item->delete();

        unset($this->items, $this->parentItems);
    }

    public function edit(): void
    {
        $this->authorize('update', $this->item);

        $this->form->load($this->item);
        $this->modal('item-form')->show();
    }

    public function showQrCode(): void
    {
        $this->qrCode = app(GenerateItemQrCode::class)->handle($this->item);

        $this->modal('qr-code')->show();
    }

    public function removeMetadata(int $index): void
    {
        $this->authorize('update', $this->item);

        $this->form->removeMetadata($index);
    }

    public function removePhoto(): void
    {
        $this->authorize('update', $this->item);

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
        $this->authorize('update', $this->item);

        $this->form->save();
        $this->modal('item-form')->close();
        unset($this->items, $this->parentItems);
    }
};
