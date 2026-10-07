<?php

use App\Enums\ChecklistType;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const ITEMS_PER_LIST = 5;

    /** @return EloquentCollection<int, Checklist> */
    #[Computed]
    public function lists(): EloquentCollection
    {
        return Auth::user()->currentTeam
            ->checklists()
            ->whereIn('type', [ChecklistType::Todo, ChecklistType::Shopping])
            ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', Auth::id()))
            ->whereHas('items', fn (Builder $query) => $query->incomplete())
            ->with(['items' => fn ($query) => $query->incomplete()])
            ->orderBy('name')
            ->get();
    }

    public function toggle(int $itemId): void
    {
        $item = ChecklistItem::with('checklist')->findOrFail($itemId);

        $this->authorize('update', $item->checklist);

        $item->toggle(Auth::user());

        unset($this->lists);
    }
};
