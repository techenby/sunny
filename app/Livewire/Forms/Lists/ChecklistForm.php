<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Lists;

use App\Enums\ChecklistType;
use App\Models\Checklist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ChecklistForm extends Form
{
    public ?Checklist $editingChecklist = null;

    public string $name = '';

    public string $type = ChecklistType::Todo->value;

    public ?int $user_id = null;

    public function load(Checklist $checklist): void
    {
        $this->fill([
            'editingChecklist' => $checklist,
            'name' => $checklist->name,
            'type' => $checklist->type->value,
            'user_id' => $checklist->user_id,
        ]);
    }

    public function save(): Checklist
    {
        $data = $this->validate();

        if ($this->editingChecklist) {
            $this->editingChecklist->update($data);
            $checklist = $this->editingChecklist;
        } else {
            $checklist = Auth::user()->currentTeam->checklists()->create($data);
        }

        $this->reset();

        return $checklist;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $memberIds = Auth::user()->currentTeam->members()->pluck('users.id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ChecklistType::class)],
            'user_id' => ['nullable', 'integer', Rule::in($memberIds)],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['user_id' => __('owner')];
    }
}
