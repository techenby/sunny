@use('App\Enums\TimeOfDay')

@php
    $columns = [
        ['name' => 'Morning routine', 'assignee' => 'Riley', 'isHousehold' => false, 'timeOfDay' => TimeOfDay::Morning, 'steps' => ['Make the bed' => 'Riley', 'Brush teeth' => 'Riley', 'Get dressed' => null, 'Pack backpack' => null, 'Feed the cat' => null]],
        ['name' => 'Morning routine', 'assignee' => 'Sam', 'isHousehold' => false, 'timeOfDay' => TimeOfDay::Morning, 'steps' => ['Make the bed' => 'Sam', 'Brush teeth' => null, 'Get dressed' => null, 'Practice piano' => null]],
        ['name' => 'Weekend chores', 'assignee' => __('Household'), 'isHousehold' => true, 'timeOfDay' => TimeOfDay::Anytime, 'steps' => ['Water the plants' => 'Jordan', 'Take out recycling' => null, 'Vacuum the living room' => null, 'Wipe down the kitchen' => null]],
    ];
@endphp

<div {{ $attributes->class('[--padding:--spacing(3)] [--radius:var(--radius-2xl)] rounded-(--radius) bg-zinc-900 p-(--padding) shadow-2xl ring-1 ring-zinc-950/10 sm:[--padding:--spacing(4)] dark:bg-zinc-950 dark:shadow-none dark:ring-white/10') }}>
    <div class="overflow-hidden rounded-[calc(var(--radius)-var(--padding))]">
        <x-welcome.screen width="1280" height="760">
            <div class="flex h-full bg-white text-zinc-950 dark:bg-zinc-800 dark:text-white">
                <x-kiosk.sidebar>
                    <div class="p-2">
                        <x-kiosk.weather location="Madison" temp="64" high="71" low="52" icon="02d" description="few clouds" />
                    </div>
                    <x-kiosk.sidebar.item icon="calendar" href="#">{{ __('Calendar') }}</x-kiosk.sidebar.item>
                    <x-kiosk.sidebar.item icon="arrow-path-rounded-square" href="#" data-current>{{ __('Routines') }}</x-kiosk.sidebar.item>
                    <x-kiosk.sidebar.item icon="queue-list" href="#">{{ __('Lists') }}</x-kiosk.sidebar.item>
                    <x-kiosk.sidebar.item icon="cooking-pot" href="#">{{ __('Meals') }}</x-kiosk.sidebar.item>

                    <div class="mt-auto flex h-20 flex-col items-center justify-center p-2 text-zinc-700 dark:text-zinc-300">
                        <flux:icon icon="arrow-path" />
                        <flux:text>{{ __('Refresh') }}</flux:text>
                    </div>
                </x-kiosk.sidebar>

                <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                    <div class="flex shrink-0 items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <div class="flex items-baseline gap-3">
                            <flux:heading size="xl">Sat, Oct 10 7:42 AM</flux:heading>
                            <flux:heading size="lg">{{ __('Today') }}</flux:heading>
                        </div>

                        <flux:button.group>
                            <flux:button icon="chevron-left" />
                            <flux:button disabled>{{ __('Today') }}</flux:button>
                            <flux:button icon="chevron-right" />
                        </flux:button.group>
                    </div>

                    <div class="flex min-h-0 flex-1 gap-4 p-4">
                        @foreach ($columns as $column)
                            @php
                                $completed = count(array_filter($column['steps']));
                                $total = count($column['steps']);
                            @endphp

                            <section class="flex h-full w-80 shrink-0 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                                <header class="shrink-0 border-b border-zinc-200 p-4 dark:border-zinc-700">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <div class="flex min-w-0 items-baseline gap-2">
                                            <flux:icon :name="$column['timeOfDay']->getIcon()" class="size-4 shrink-0 translate-y-0.5 text-zinc-400" />
                                            <flux:heading size="lg" class="truncate">{{ $column['name'] }}</flux:heading>
                                        </div>

                                        <flux:text class="tabular-nums">{{ $completed }}/{{ $total }}</flux:text>
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <flux:badge size="sm" :color="$column['isHousehold'] ? 'zinc' : 'blue'">{{ $column['assignee'] }}</flux:badge>
                                        <flux:badge size="sm" color="zinc">{{ $column['timeOfDay']->getLabel() }}</flux:badge>
                                    </div>

                                    <flux:progress :value="$completed" :max="$total" class="mt-2 h-2" />
                                </header>

                                <div class="min-h-0 flex-1 overflow-hidden p-4">
                                    <flux:checkbox.group variant="cards" class="flex-col">
                                        @foreach ($column['steps'] as $step => $completedBy)
                                            <flux:checkbox :value="$step" :checked="$completedBy !== null" class="items-center">
                                                <flux:checkbox.indicator />

                                                <span class="flex-1 text-zinc-800 dark:text-zinc-100 [ui-checkbox[data-checked]_&]:text-zinc-400 [ui-checkbox[data-checked]_&]:line-through dark:[ui-checkbox[data-checked]_&]:text-zinc-500">
                                                    {{ $step }}
                                                </span>

                                                @if ($completedBy)
                                                    <flux:text size="sm" variant="subtle" class="shrink-0">{{ $completedBy }}</flux:text>
                                                @endif
                                            </flux:checkbox>
                                        @endforeach
                                    </flux:checkbox.group>
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-welcome.screen>
    </div>
</div>
