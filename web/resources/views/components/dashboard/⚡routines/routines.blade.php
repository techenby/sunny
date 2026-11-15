<x-dashboard.card :heading="__('Today\'s routines')" :action="__('All routines')" :href="route('routines.index')">
    @if ($this->occurrences->isEmpty())
        <x-dashboard.empty
            icon="party-popper"
            :heading="__('No routines today')"
            :text="__('Nothing scheduled for you today.')"
            :action="__('Add a routine')"
            :href="route('routines.index')"
        />
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

            @if ($this->total > $this->occurrences->count())
                <x-dashboard.more
                    :href="route('routines.index')"
                    :label="trans_choice(':count more routine|:count more routines', $this->total - $this->occurrences->count(), ['count' => $this->total - $this->occurrences->count()])"
                    :note="__('Showing :shown of :total routines', ['shown' => $this->occurrences->count(), 'total' => $this->total])"
                />
            @endif
        </div>
    @endif
</x-dashboard.card>
