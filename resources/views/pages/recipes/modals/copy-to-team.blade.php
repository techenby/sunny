@teleport('body')
<flux:modal name="copy-recipe" class="md:w-96">
    <form wire:submit="copyToTeam" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Copy to Team') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Copy this recipe to another team.') }}</flux:text>
        </div>
        <flux:select wire:model="copyToTeamId" :label="__('Team')" variant="listbox" :placeholder="__('Select a team...')">
            @foreach ($this->otherTeams as $team)
                <flux:select.option :value="$team->id">{{ $team->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <div class="flex">
            <flux:spacer />
            <flux:button type="submit" variant="primary">{{ __('Copy') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
