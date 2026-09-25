@teleport('body')
<flux:modal name="move-item" class="md:w-96">
    <form wire:submit="moveToTeam" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Move to Team') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Move this item to another team.') }}</flux:text>
        </div>
        <flux:select wire:model="moveToTeamId" :label="__('Team')" :placeholder="__('Select a team...')">
            @foreach ($this->otherTeams as $team)
                <flux:select.option :value="$team->id">{{ $team->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Move') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
