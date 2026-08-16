<div class="space-y-6">
    <div class="flex items-center gap-4">
        <flux:button :href="route('routines.index')" icon="arrow-left" variant="ghost" wire:navigate />

        <div>
            <flux:heading size="xl">{{ $routine->name }}</flux:heading>
            <flux:text>
                {{ $routine->time_of_day->getLabel() }} &middot; {{ $routine->scheduleSummary() }}
                &middot; {{ $routine->user?->name ?? __('Household') }}
            </flux:text>
        </div>
    </div>

    <div class="max-w-2xl space-y-4">
        <flux:heading>{{ __('Steps') }}</flux:heading>

        @if ($this->steps->isEmpty())
            <flux:text>
                {{ __('This routine has no steps yet, so it will not show anything on the kiosk.') }}
            </flux:text>
        @else
            <ul class="space-y-2">
                @foreach ($this->steps as $index => $step)
                    <li wire:key="step-{{ $step->id }}" class="flex items-center gap-2">
                        <flux:text variant="subtle" class="w-6 shrink-0 text-right tabular-nums">{{ $index + 1 }}</flux:text>

                        <flux:input
                            wire:model="names.{{ $step->id }}"
                            wire:blur="rename({{ $step->id }})"
                            wire:keydown.enter="rename({{ $step->id }})"
                            :aria-label="__('Step name')"
                        />

                        <flux:button
                            icon="chevron-up"
                            variant="ghost"
                            wire:click="moveUp({{ $step->id }})"
                            :disabled="$loop->first"
                            :aria-label="__('Move up')"
                        />
                        <flux:button
                            icon="chevron-down"
                            variant="ghost"
                            wire:click="moveDown({{ $step->id }})"
                            :disabled="$loop->last"
                            :aria-label="__('Move down')"
                        />
                        <flux:button
                            icon="trash"
                            variant="ghost"
                            wire:click="removeStep({{ $step->id }})"
                            :aria-label="__('Remove step')"
                        />
                    </li>
                @endforeach
            </ul>
        @endif

        <form wire:submit="addStep" class="flex gap-2">
            <flux:input wire:model="newStep" :placeholder="__('Add a step')" />
            <flux:button type="submit" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
        </form>
    </div>
</div>
