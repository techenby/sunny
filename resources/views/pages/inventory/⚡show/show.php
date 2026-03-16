<?php

use App\Actions\Inventory\GenerateItemQrCode;
use App\Actions\Inventory\MoveItemToTeam;
use App\Livewire\Forms\Inventory\ItemForm;
use App\Models\Item;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Inventory: Item')] class extends Component
{
    use WithFileUploads;

    public Item $item;

    public ItemForm $form;

    public ?int $moveToTeamId = null;

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
            ->where('id', '!=', $this->item->id)
            ->orderBy('name')
            ->get();
    }

    public function moveToTeam(): void
    {
        $this->validate([
            'moveToTeamId' => ['required', 'integer', Rule::exists('team_user', 'team_id')->where('user_id', Auth::id())],
        ]);

        $this->authorize('move', $this->item);

        $team = $this->otherTeams->firstWhere('id', $this->moveToTeamId);

        (new MoveItemToTeam)->handle($this->item, $team);

        $this->redirectRoute('inventory.index');
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->item);

        $parentId = $this->item->parent_id;

        $this->item->purge();

        $this->redirectRoute('inventory.index', ['parentId' => $parentId]);
    }

    public function edit(): void
    {
        $this->authorize('update', $this->item);

        $this->form->load($this->item);
        $this->modal('item-form')->show();
    }

    public function showQrCode(): void
    {
        $this->authorize('view', $this->item);

        $this->qrCode = resolve(GenerateItemQrCode::class)->handle($this->item);

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
        unset($this->parentItems);
    }
};
