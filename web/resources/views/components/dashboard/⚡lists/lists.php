<?php

use App\Enums\ChecklistType;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const LISTS = 3;

    public const ITEMS_PER_LIST = 3;

    /** @return EloquentCollection<int, Checklist> */
    #[Computed]
    public function lists(): EloquentCollection
    {
        return $this->openLists()
            ->withCount(['items as open_items_count' => fn (Builder $query) => $query->incomplete()])
            ->with(['items' => fn ($query) => $query->incomplete()->limit(self::ITEMS_PER_LIST)])
            ->orderBy('name')
            ->limit(self::LISTS)
            ->get();
    }

    #[Computed]
    public function total(): int
    {
        return $this->openLists()->count();
    }

    public function toggle(int $itemId): void
    {
        $item = ChecklistItem::with('checklist')->findOrFail($itemId);

        $this->authorize('update', $item->checklist);

        $item->toggle(Auth::user());

        unset($this->lists, $this->total);
    }

    /** @return HasMany<Checklist, Team> */
    private function openLists(): HasMany
    {
        return Auth::user()->currentTeam
            ->checklists()
            ->whereIn('type', [ChecklistType::Todo, ChecklistType::Shopping])
            ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', Auth::id()))
            ->whereHas('items', fn (Builder $query) => $query->incomplete());
    }
};
