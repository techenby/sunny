@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<column class="w-full gap-2">
    <column ref="getting-started" class="{{ Card::classes() }} w-full">
        <column class="w-full gap-3 px-4 pt-4 pb-3">
            <row class="w-full items-center gap-3">
                <x-ui.heading size="xl" class="flex-1">Get started</x-ui.heading>
                <x-ui.text ref="getting-started-progress">{{ $checklist['done'] }} of {{ $checklist['total'] }} done</x-ui.text>
            </row>
            <row class="w-full gap-1">
                @foreach ($checklist['jobs'] as $job)
                    <column class="h-1.5 flex-1 rounded-full {{ $job['done'] ? 'bg-theme-primary' : 'bg-theme-surface-variant' }}" />
                @endforeach
            </row>
        </column>
        @foreach ($checklist['jobs'] as $job)
            <divider />
            <row class="w-full items-center gap-3 px-4 {{ $job['next'] ? 'bg-theme-primary/10' : '' }}">
                <pressable
                    ref="getting-started-{{ $job['key'] }}"
                    :a11y-label="$job['title'].($job['done'] ? ', done' : '')"
                    :a11y-hint="$job['detail']"
                    class="min-h-12 flex-1 justify-center py-2"
                    @tap="startJob('{{ $job['key'] }}')"
                >
                    <row class="w-full items-center gap-3">
                        @if ($job['done'])
                            <column class="h-6 w-6 items-center justify-center rounded-full bg-theme-primary">
                                <icon :ios="Ios::Checkmark" :android="Android::Check" :size="14" class="text-theme-on-primary" />
                            </column>
                        @else
                            <column class="h-6 w-6 rounded-full border-2 {{ $job['next'] ? 'border-theme-primary' : 'border-theme-outline' }}" />
                        @endif
                        <column class="flex-1 gap-0.5">
                            @if ($job['next'])
                                <text font="semibold" class="text-lg text-theme-on-surface">{{ $job['title'] }}</text>
                            @else
                                <text class="text-lg {{ $job['done'] ? 'text-theme-on-surface-variant line-through' : 'text-theme-on-surface' }}">{{ $job['title'] }}</text>
                            @endif
                            @if ($job['detail'] && ! $job['done'])
                                <x-ui.text size="sm">{{ $job['detail'] }}</x-ui.text>
                            @endif
                        </column>
                        @if ($job['next'])
                            <column class="min-h-10 items-center justify-center rounded-full bg-theme-primary px-4">
                                <text font="semibold" class="text-base text-theme-on-primary">Start</text>
                            </column>
                        @elseif (! $job['done'])
                            <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                        @endif
                    </row>
                </pressable>
                @if ($job['key'] === 'set-up' && $job['done'] && $canUndo)
                    <pressable
                        ref="getting-started-undo"
                        a11y-label="Undo set-up"
                        a11y-hint="Removes what set-up made"
                        class="min-h-12 justify-center"
                        @tap="undoSetUp"
                    >
                        <text font="semibold" class="text-base text-theme-on-surface-variant">Undo</text>
                    </pressable>
                @endif
            </row>
        @endforeach
    </column>
    <pressable
        ref="getting-started-hide"
        a11y-label="Hide this checklist"
        class="min-h-12 w-full items-center justify-center"
        @tap="hideGettingStarted"
    >
        <text font="semibold" class="text-base text-theme-on-surface-variant">Hide this checklist</text>
    </pressable>
</column>
