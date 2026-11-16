@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<column class="w-full gap-2">
    <column ref="getting-started" class="{{ Card::classes() }} w-full">
        <column class="w-full gap-1 px-4 pt-4 pb-3">
            <x-ui.heading size="xl">Get started</x-ui.heading>
            <x-ui.text ref="getting-started-progress">{{ $checklist['done'] }} of {{ $checklist['total'] }} done</x-ui.text>
            <row class="mt-2 w-full gap-1">
                @foreach ($checklist['jobs'] as $job)
                    <column class="h-1.5 flex-1 rounded-full {{ $job['done'] ? 'bg-theme-primary' : 'bg-theme-surface-variant' }}" />
                @endforeach
            </row>
        </column>
        @foreach ($checklist['jobs'] as $job)
            <divider />
            @if ($job['done'])
                <row ref="getting-started-{{ $job['key'] }}" class="min-h-12 w-full items-center gap-3 px-4 py-2">
                    <column class="h-6 w-6 items-center justify-center rounded-full bg-theme-primary">
                        <icon :ios="Ios::Checkmark" :android="Android::Check" :size="14" class="text-theme-on-primary" />
                    </column>
                    <text :a11y-label="$job['title'].', done'" class="flex-1 text-lg text-theme-on-surface-variant line-through">{{ $job['title'] }}</text>
                </row>
            @else
                <pressable
                    ref="getting-started-{{ $job['key'] }}"
                    :a11y-label="$job['title']"
                    :a11y-hint="$job['detail']"
                    class="min-h-12 w-full justify-center px-4 py-2 {{ $job['next'] ? 'bg-theme-primary/10' : '' }}"
                    @tap="startJob('{{ $job['key'] }}')"
                >
                    <row class="w-full items-center gap-3">
                        <column class="h-6 w-6 rounded-full border-2 {{ $job['next'] ? 'border-theme-primary' : 'border-theme-outline' }}" />
                        <column class="flex-1 items-start gap-0.5">
                            @if ($job['next'])
                                <text font="semibold" class="text-lg text-theme-on-surface">{{ $job['title'] }}</text>
                            @else
                                <text class="text-lg text-theme-on-surface">{{ $job['title'] }}</text>
                            @endif
                            @if ($job['detail'])
                                <x-ui.text size="sm">{{ $job['detail'] }}</x-ui.text>
                            @endif
                            @if ($job['next'])
                                <column class="mt-2 min-h-10 items-center justify-center rounded-full bg-theme-primary px-5">
                                    <text font="semibold" class="text-base text-theme-on-primary">Start</text>
                                </column>
                            @endif
                        </column>
                        <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                    </row>
                </pressable>
            @endif
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
