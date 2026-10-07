<?php

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const LIMIT = 5;

    /** @return EloquentCollection<int, Item> */
    #[Computed]
    public function items(): EloquentCollection
    {
        return Auth::user()->currentTeam
            ->items()
            ->with('parent')
            ->latest()
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();
    }
};
