<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Team;
use Illuminate\Support\Collection;

new class extends Component
{
    public ?int $currentTeamId = null;

    public string $teamName = '';

    public function mount(): void
    {
        $this->currentTeamId = auth()->user()->current_team_id;
    }

    #[Computed]
    public function currentTeam(): Team
    {
        return auth()->user()->currentTeam;
    }

    #[Computed]
    public function teams(): Collection
    {
        return auth()->user()->teams;
    }

    public function create(): void
    {
        $team = auth()->user()->addTeam($this->teamName);
        $this->currentTeamId = $team->id;

        unset($this->currentTeam);
        unset($this->teams);

        $this->modal('create-team')->close();
        $this->reset('teamName');
    }

    public function updatedCurrentTeamId($value): void
    {
        auth()->user()->switchTeam($this->teams->firstWhere('id', $value));
        $this->currentTeamId = $value;
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

            <flux:modal.trigger name="create-team">
                <flux:menu.item icon="plus">Create Team</flux:menu.item>
            </flux:modal.trigger>
        </flux:menu>
    </flux:dropdown>

    <flux:modal name="create-team" class="md:w-96">
        <form wire:submit="create" class="space-y-6">
            <flux:heading size="lg">Create new team</flux:heading>

            <flux:input wire:model="teamName" label="Team Name" />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
