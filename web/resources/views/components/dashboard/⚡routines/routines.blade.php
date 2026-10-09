<x-dashboard.card :heading="__('Today\'s routines')" :action="__('All routines')" :href="route('routines.index')">
    @if ($this->occurrences->isEmpty())
        <div class="py-6 text-center">
            <flux:icon name="party-popper" class="mx-auto mb-3 size-10 text-zinc-400" />
            <flux:text>{{ __('Nothing scheduled for you today.') }}</flux:text>
        </div>
    @else
        <div class="space-y-5">
            @foreach ($this->occurrences as $occurrence)
                <x-dashboard.routine
                    wire:key="dashboard-occurrence-{{ $occurrence->id }}"
                    :name="$occurrence->routine->name"
                    :icon="$occurrence->routine->time_of_day->getIcon()"
                    :household="$occurrence->routine->user === null"
                    :completed="$occurrence->steps->filter(fn ($step) => $step->isCompleted())->count()"
                    :total="$occurrence->steps->count()"
                >
                    @foreach ($occurrence->steps as $step)
                        <li wire:key="dashboard-occurrence-step-{{ $step->id }}">
                            <x-dashboard.check-item :completed="$step->isCompleted()" wire:click="toggle({{ $step->id }})">
                                {{ $step->step?->name }}
                            </x-dashboard.check-item>
                        </li>
                    @endforeach
                </x-dashboard.routine>
            @endforeach
        </div>
    @endif
</x-dashboard.card>
