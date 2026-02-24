<?php

use Livewire\Component;

new class extends Component
{
    public ?int $currentTeamId = null;

    public function mount()
    {
        $this->currentTeamId = auth()->user()->current_team_id;
    }
};
?>

<div>
    <flux:dropdown>
        <flux:profile :name="auth()->user()->currentTeam->name" />

        <flux:menu>
            <flux:menu.radio.group wire:model.live="currentTeamId">
                @foreach (auth()->user()->teams as $team)
                    <flux:menu.radio :value="$team->id">{{ $team->name }}</flux:menu.radio>
                @endforeach
            </flux:menu.radio.group>

            <flux:menu.separator />

            <flux:menu.item icon="plus">Create Team</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
