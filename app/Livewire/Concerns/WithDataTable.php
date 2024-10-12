<?php

namespace App\Livewire\Concerns;

trait WithDataTable {
    public $perPage = 10;
    public $search = '';
    public $sortBy = '';
    public $sortDirection = 'desc';

    public function sort($column)
    {
        if ($this->sortBy === $column && $this->sortDirection === 'asc') {
            $this->reset('sortBy', 'sortDirection');
        } elseif ($this->sortBy === $column) {
            $this->sortDirection = 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }
    }
}
