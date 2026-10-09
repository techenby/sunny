@use('App\Enums\ChecklistType')
@use('App\Enums\TimeOfDay')

@php
    $days = [
        __('Today') => [
            ['time' => '9:00 - 10:30 AM', 'title' => 'Soccer', 'location' => 'Riverside Park', 'color' => '#38bdf8'],
            ['time' => '1:30 - 2:00 PM', 'title' => 'Haircut', 'location' => null, 'color' => '#a78bfa'],
            ['time' => '6:00 - 8:00 PM', 'title' => 'Grandma\'s', 'location' => null, 'color' => '#fbbf24'],
        ],
        __('Tomorrow') => [
            ['time' => __('All day'), 'title' => 'Market', 'location' => 'Main Street', 'color' => '#34d399'],
            ['time' => '10:00 - 11:00 AM', 'title' => 'Swim', 'location' => null, 'color' => '#38bdf8'],
        ],
        'Monday' => [
            ['time' => '7:30 - 8:00 AM', 'title' => 'Dentist', 'location' => null, 'color' => '#a78bfa'],
        ],
    ];

    $routines = [
        ['name' => 'Morning routine', 'icon' => TimeOfDay::Morning->getIcon(), 'household' => false, 'steps' => ['Make the bed' => true, 'Brush teeth' => true, 'Pack backpack' => false, 'Feed the cat' => false]],
        ['name' => 'Weekend chores', 'icon' => TimeOfDay::Anytime->getIcon(), 'household' => true, 'steps' => ['Water the plants' => true, 'Take out recycling' => false]],
    ];

    $lists = [
        ['name' => 'Groceries', 'type' => ChecklistType::Shopping, 'household' => true, 'items' => ['Oat milk', 'Kidney beans', 'Tortillas', 'Cilantro']],
        ['name' => 'Riley\'s to-dos', 'type' => ChecklistType::Todo, 'household' => false, 'items' => ['Return library books', 'Sign permission slip']],
    ];
@endphp

<div {{ $attributes->class('[--padding:--spacing(2)] [--radius:var(--radius-2xl)] rounded-(--radius) bg-zinc-950/5 p-(--padding) ring-1 ring-zinc-950/5 ring-inset dark:bg-white/5 dark:ring-white/10') }}>
    <div class="overflow-hidden rounded-[calc(var(--radius)-var(--padding))] shadow-lg ring-1 ring-zinc-950/10 dark:shadow-none dark:ring-white/10">
        <x-welcome.screen width="1000" height="660">
            <div class="flex h-full bg-white text-zinc-950 dark:bg-zinc-800 dark:text-white">
                <div class="flex w-64 shrink-0 flex-col gap-4 border-e border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:sidebar.brand href="#" :logo="asset('icon.svg')" name="Sunny Home" />

                    <flux:sidebar.nav>
                        <flux:sidebar.item icon="home" href="#" current>{{ __('Dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="archive-box" href="#">{{ __('Inventory') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="book-open" href="#">{{ __('Recipes') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-path-rounded-square" href="#">{{ __('Routines') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="queue-list" href="#">{{ __('Lists') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="tv" href="#">{{ __('Kiosk') }}</flux:sidebar.item>
                    </flux:sidebar.nav>
                </div>

                <div class="flex min-w-0 flex-1 flex-col gap-6 p-8">
                    <div>
                        <flux:heading size="xl">Good morning, Riley</flux:heading>
                        <flux:text class="mt-1">Saturday, October 10</flux:text>
                    </div>

                    <x-dashboard.card :heading="__('Coming up')" :action="__('Calendars')" href="#">
                        <div class="@container">
                            <div class="grid gap-5 @2xl:grid-cols-3">
                                @foreach ($days as $label => $events)
                                    <section class="space-y-2">
                                        <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $label }}</flux:heading>

                                        @foreach ($events as $event)
                                            <x-dashboard.event :time="$event['time']" :title="$event['title']" :location="$event['location']" :color="$event['color']" />
                                        @endforeach
                                    </section>
                                @endforeach
                            </div>
                        </div>
                    </x-dashboard.card>

                    <div class="grid grid-cols-2 gap-6">
                        <x-dashboard.card :heading="__('Today\'s routines')" :action="__('All routines')" href="#">
                            <div class="space-y-5">
                                @foreach ($routines as $routine)
                                    <x-dashboard.routine
                                        :name="$routine['name']"
                                        :icon="$routine['icon']"
                                        :household="$routine['household']"
                                        :completed="count(array_filter($routine['steps']))"
                                        :total="count($routine['steps'])"
                                    >
                                        @foreach ($routine['steps'] as $step => $completed)
                                            <li>
                                                <x-dashboard.check-item :$completed>{{ $step }}</x-dashboard.check-item>
                                            </li>
                                        @endforeach
                                    </x-dashboard.routine>
                                @endforeach
                            </div>
                        </x-dashboard.card>

                        <x-dashboard.card :heading="__('Open lists')" :action="__('All lists')" href="#">
                            <div class="space-y-5">
                                @foreach ($lists as $list)
                                    <x-dashboard.list
                                        :name="$list['name']"
                                        :icon="$list['type']->getIcon()"
                                        :color="$list['type']->getIconColor()"
                                        href="#"
                                        :household="$list['household']"
                                    >
                                        <ul>
                                            @foreach ($list['items'] as $item)
                                                <li>
                                                    <x-dashboard.check-item>{{ $item }}</x-dashboard.check-item>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </x-dashboard.list>
                                @endforeach
                            </div>
                        </x-dashboard.card>
                    </div>
                </div>
            </div>
        </x-welcome.screen>
    </div>
</div>
