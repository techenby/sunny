<?php

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const LIMIT = 5;

    /** @return EloquentCollection<int, Recipe> */
    #[Computed]
    public function recipes(): EloquentCollection
    {
        return Auth::user()->currentTeam
            ->recipes()
            ->latest()
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();
    }
};
