<?php

namespace App\Livewire\Forms;

use App\Models\LegoBin;
use Illuminate\Support\Arr;
use Livewire\Form;

class LegoBinForm extends Form
{
    public ?LegoBin $bin;

    public ?string $type;
    public array $parts = [];
    public array $colors = [];
    public ?string $notes;

    public function set(LegoBin $bin): void
    {
        $this->bin = $bin;
        $this->type = $bin->type;
        $this->parts = $bin->parts->pluck('id')->toArray();
        $this->colors = $bin->colors->pluck('id')->toArray();
        $this->notes = $bin->notes;
    }

    public function store(): void
    {
        $validated = $this->validate();

        $bin = LegoBin::create(Arr::only($validated, ['type', 'notes']));

        $bin->parts()->attach($this->parts);
        $bin->colors()->attach($this->colors);

        $this->reset();
    }

    public function update(): void
    {
        $validated = $this->validate();

        $this->bin->update(Arr::only($validated, ['type', 'notes']));

        $this->bin->parts()->sync($this->parts);
        $this->bin->colors()->sync($this->colors);

        $this->reset();
    }

    protected function rules()
    {
        return [
            'type' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'parts' => ['required', 'array'],
            'colors' => ['nullable', 'array'],
        ];
    }
}
