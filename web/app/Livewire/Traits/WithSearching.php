<?php

declare(strict_types=1);

namespace App\Livewire\Traits;

use Livewire\Attributes\Url;

trait WithSearching
{
    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
}
