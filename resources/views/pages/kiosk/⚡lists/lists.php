<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::kiosk')] class extends Component
{
    #[Url]
    public ?int $selected = null;

    public string $newItem = '';

    public function mount(): void
    {
        $this->selected ??= $this->lists->first()?->id;
    }

    /** @return EloquentCollection<int, Checklist> */
    #[Computed]
    public function lists(): EloquentCollection
    {
        return $this->team()
            ->checklists()
            ->with(['user', 'items.completedBy'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function list(): ?Checklist
    {
        return $this->lists->firstWhere('id', $this->selected) ?? $this->lists->first();
    }

    public function select(int $listId): void
    {
        $this->selected = $listId;
        $this->newItem = '';

        unset($this->list);
    }

    public function addItem(): void
    {
        $list = $this->list;

        if (! $list instanceof Checklist) {
            return;
        }

        $this->authorize('update', $list);

        $this->newItem = trim($this->newItem);

        if ($this->newItem === '') {
            return;
        }

        $this->validate(['newItem' => ['string', 'max:255']]);

        $list->items()->create(['name' => $this->newItem]);

        $this->newItem = '';

        unset($this->lists, $this->list);
    }

    public function toggle(int $itemId): void
    {
        $item = ChecklistItem::with('checklist')->findOrFail($itemId);

        $this->authorize('update', $item->checklist);

        $item->toggle(Auth::user());

        unset($this->lists, $this->list);
    }

    public function removeItem(int $itemId): void
    {
        $item = ChecklistItem::with('checklist')->findOrFail($itemId);

        $this->authorize('update', $item->checklist);

        $item->delete();

        unset($this->lists, $this->list);
    }

    public function clearCompleted(): void
    {
        $list = $this->list;

        if (! $list instanceof Checklist) {
            return;
        }

        $this->authorize('update', $list);

        $list->items()->completed()->delete();

        unset($this->lists, $this->list);
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }
};
