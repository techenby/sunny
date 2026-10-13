@use('App\Enums\ChecklistType')
@use('App\Enums\TimeOfDay')

@php
    $tabs = [
        'calendar' => ['icon' => 'calendar', 'label' => __('Calendar')],
        'routines' => ['icon' => 'arrow-path-rounded-square', 'label' => __('Routines')],
        'lists' => ['icon' => 'queue-list', 'label' => __('Lists')],
        'meals' => ['icon' => 'cooking-pot', 'label' => __('Meals')],
    ];

    $week = [
        ['day' => 'Mon', 'date' => 'Oct 5', 'isToday' => false, 'events' => [
            ['time' => '7:30 - 8:00 AM', 'title' => 'Dentist', 'location' => null, 'color' => '#a78bfa'],
        ]],
        ['day' => 'Tue', 'date' => 'Oct 6', 'isToday' => false, 'events' => [
            ['time' => '3:30 - 4:30 PM', 'title' => 'Piano lesson', 'location' => null, 'color' => '#38bdf8'],
        ]],
        ['day' => 'Wed', 'date' => 'Oct 7', 'isToday' => false, 'events' => [
            ['time' => '6:00 - 7:00 PM', 'title' => 'Soccer practice', 'location' => 'Riverside Park', 'color' => '#38bdf8'],
        ]],
        ['day' => 'Thu', 'date' => 'Oct 8', 'isToday' => false, 'events' => [
            ['time' => '5:30 - 6:30 PM', 'title' => 'Book club', 'location' => 'Library', 'color' => '#fbbf24'],
        ]],
        ['day' => 'Fri', 'date' => 'Oct 9', 'isToday' => false, 'events' => [
            ['time' => '6:00 - 8:00 PM', 'title' => 'Movie night', 'location' => null, 'color' => '#34d399'],
        ]],
        ['day' => 'Sat', 'date' => 'Oct 10', 'isToday' => true, 'events' => [
            ['time' => '9:00 - 10:30 AM', 'title' => 'Soccer', 'location' => 'Riverside Park', 'color' => '#38bdf8'],
            ['time' => '1:30 - 2:00 PM', 'title' => 'Haircut', 'location' => null, 'color' => '#a78bfa'],
            ['time' => '6:00 - 8:00 PM', 'title' => 'Grandma\'s', 'location' => null, 'color' => '#fbbf24'],
        ]],
        ['day' => 'Sun', 'date' => 'Oct 11', 'isToday' => false, 'events' => [
            ['time' => __('All day'), 'title' => 'Market', 'location' => 'Main Street', 'color' => '#34d399'],
            ['time' => '10:00 - 11:00 AM', 'title' => 'Swim', 'location' => null, 'color' => '#38bdf8'],
        ]],
    ];

    $columns = [
        ['name' => 'Morning routine', 'assignee' => 'Riley', 'isHousehold' => false, 'timeOfDay' => TimeOfDay::Morning, 'steps' => ['Make the bed' => 'Riley', 'Brush teeth' => 'Riley', 'Get dressed' => null, 'Pack backpack' => null, 'Feed the cat' => null]],
        ['name' => 'Morning routine', 'assignee' => 'Sam', 'isHousehold' => false, 'timeOfDay' => TimeOfDay::Morning, 'steps' => ['Make the bed' => 'Sam', 'Brush teeth' => null, 'Get dressed' => null, 'Practice piano' => null]],
        ['name' => 'Weekend chores', 'assignee' => __('Household'), 'isHousehold' => true, 'timeOfDay' => TimeOfDay::Anytime, 'steps' => ['Water the plants' => 'Jordan', 'Take out recycling' => null, 'Vacuum the living room' => null, 'Wipe down the kitchen' => null]],
    ];

    $lists = [
        ['name' => 'Groceries', 'type' => ChecklistType::Shopping],
        ['name' => 'Riley\'s to-dos', 'type' => ChecklistType::Todo],
        ['name' => 'Birthday wishlist', 'type' => ChecklistType::Wishlist],
    ];

    $groceries = ['Oat milk' => null, 'Kidney beans' => 'Jordan', 'Tortillas' => null, 'Cilantro' => 'Sam', 'Apples' => null, 'Coffee' => null];
@endphp

<div {{ $attributes->class('[--padding:--spacing(3)] [--radius:var(--radius-2xl)] rounded-(--radius) bg-zinc-900 p-(--padding) shadow-2xl ring-1 ring-zinc-950/10 sm:[--padding:--spacing(4)] dark:bg-zinc-950 dark:shadow-none dark:ring-white/10') }}>
    <div class="overflow-hidden rounded-[calc(var(--radius)-var(--padding))]">
        <x-welcome.screen width="1280" height="760" interactive>
            <div x-data="{ tab: 'routines' }" class="flex h-full bg-white text-zinc-950 dark:bg-zinc-800 dark:text-white">
                <x-kiosk.sidebar>
                    <div class="p-2">
                        <x-kiosk.weather location="Madison" temp="64" high="71" low="52" icon="02d" description="few clouds" />
                    </div>

                    @foreach ($tabs as $key => $tab)
                        <x-kiosk.sidebar.item
                            :icon="$tab['icon']"
                            href="#"
                            :data-current="$key === 'routines'"
                            x-on:click.prevent="tab = '{{ $key }}'"
                            x-bind:data-current="tab === '{{ $key }}'"
                            x-bind:aria-current="tab === '{{ $key }}' && 'page'"
                        >{{ $tab['label'] }}</x-kiosk.sidebar.item>
                    @endforeach

                    <div class="mt-auto flex h-20 flex-col items-center justify-center p-2 text-zinc-700 dark:text-zinc-300">
                        <flux:icon icon="arrow-path" />
                        <flux:text>{{ __('Refresh') }}</flux:text>
                    </div>
                </x-kiosk.sidebar>

                <div class="flex min-w-0 flex-1 flex-col overflow-hidden" inert>
                    <div x-show="tab === 'calendar'" class="flex h-full flex-col overflow-hidden" style="display: none">
                        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                            <flux:heading size="xl">Sat, Oct 10 7:42 AM</flux:heading>

                            <div class="flex items-center gap-2">
                                <flux:button icon="funnel" icon:variant="outline" />

                                <flux:radio.group variant="segmented">
                                    <flux:radio value="day" label="Day" />
                                    <flux:radio value="week" label="Week" checked />
                                    <flux:radio value="month" label="Month" />
                                </flux:radio.group>

                                <flux:button.group>
                                    <flux:button icon="chevron-left" />
                                    <flux:button>{{ __('Today') }}</flux:button>
                                    <flux:button icon="chevron-right" />
                                </flux:button.group>
                            </div>
                        </div>

                        <div class="grid min-h-0 flex-1 grid-cols-7 divide-x divide-zinc-100 dark:divide-zinc-700">
                            @foreach ($week as $day)
                                <div class="flex flex-col">
                                    <div @class([
                                        'border-b border-zinc-100 px-3 py-2 dark:border-zinc-700',
                                        'bg-zinc-50 dark:bg-zinc-800' => ! $day['isToday'],
                                        'bg-blue-100 dark:bg-blue-900' => $day['isToday'],
                                    ])>
                                        <div class="text-xs font-medium text-zinc-500 uppercase dark:text-zinc-400">{{ $day['day'] }}</div>
                                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $day['date'] }}</div>
                                    </div>

                                    <div class="flex flex-1 flex-col gap-2 p-3">
                                        @foreach ($day['events'] as $event)
                                            <div class="rounded-md border border-zinc-200 bg-white p-2 text-sm shadow-xs dark:border-zinc-700 dark:bg-zinc-950" style="border-left: 4px solid {{ $event['color'] }}">
                                                <div class="mb-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $event['time'] }}</div>
                                                <div class="leading-snug font-medium text-zinc-900 dark:text-zinc-100">{{ $event['title'] }}</div>

                                                @if ($event['location'])
                                                    <div class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $event['location'] }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="tab === 'routines'" class="flex h-full flex-col overflow-hidden">
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

                    <div x-show="tab === 'lists'" class="flex h-full flex-col overflow-hidden" style="display: none">
                        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                            <flux:heading size="xl">Sat, Oct 10 7:42 AM</flux:heading>

                            <flux:button variant="subtle" icon="trash">{{ __('Clear completed') }}</flux:button>
                        </div>

                        <div class="flex shrink-0 gap-2 border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                            @foreach ($lists as $list)
                                <div @class([
                                    'flex shrink-0 items-center gap-2 rounded-full border px-4 py-2',
                                    'border-transparent bg-(--color-accent) text-(--color-accent-foreground)' => $loop->first,
                                    'border-zinc-200 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300' => ! $loop->first,
                                ])>
                                    <flux:icon :name="$list['type']->getIcon()" class="size-5" />
                                    <span>{{ $list['name'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="min-h-0 flex-1 overflow-hidden p-5">
                            <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
                                <flux:heading size="xl">{{ $lists[0]['name'] }}</flux:heading>

                                <div class="flex gap-2">
                                    <flux:input :placeholder="__('Add an item')" class:input="text-lg" />
                                    <flux:button variant="primary" icon="plus">{{ __('Add') }}</flux:button>
                                </div>

                                <ul class="flex flex-col gap-2">
                                    @foreach ($groceries as $item => $completedBy)
                                        <li class="flex items-center gap-2">
                                            <div
                                                @if ($completedBy) data-completed @endif
                                                class="group flex flex-1 items-center gap-3 rounded-lg border p-4 not-data-completed:border-zinc-200 not-data-completed:bg-white data-completed:border-transparent data-completed:bg-zinc-100 dark:not-data-completed:border-zinc-700 dark:not-data-completed:bg-zinc-900 dark:data-completed:bg-zinc-800/50"
                                            >
                                                <flux:icon name="check-circle" variant="solid" class="hidden size-6 shrink-0 text-(--color-accent) group-data-completed:block" />
                                                <span class="size-6 shrink-0 rounded-full border-2 border-zinc-300 group-data-completed:hidden dark:border-zinc-600"></span>

                                                <span class="flex-1 text-lg text-zinc-800 group-data-completed:text-zinc-400 group-data-completed:line-through dark:text-zinc-100 dark:group-data-completed:text-zinc-500">
                                                    {{ $item }}
                                                </span>

                                                @if ($completedBy)
                                                    <flux:text size="sm" variant="subtle" class="shrink-0">{{ $completedBy }}</flux:text>
                                                @endif
                                            </div>

                                            <flux:button variant="subtle" icon="x-mark" />
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'meals'" class="flex h-full items-center justify-center p-6" style="display: none">
                        <div class="w-full max-w-3xl rounded-lg border border-zinc-200 bg-white p-8 text-center dark:border-zinc-700 dark:bg-zinc-900">
                            <flux:icon name="cooking-pot" class="mx-auto mb-4 size-10 text-zinc-400" />
                            <flux:heading size="xl">{{ __('Meal Planning') }}</flux:heading>
                            <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
                                {{ __('Meal plans and dinner ideas will live here.') }}
                            </flux:text>
                        </div>
                    </div>
                </div>
            </div>
        </x-welcome.screen>
    </div>
</div>
