<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Meal Plan') }}</flux:heading>
            <flux:text>
                {{ $this->range[0]->translatedFormat('M j') }} – {{ $this->range[1]->translatedFormat('M j, Y') }}
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button.group>
                <flux:button icon="chevron-left" wire:click="previousWeek" :aria-label="__('Previous week')" />
                <flux:button wire:click="thisWeek">{{ __('This week') }}</flux:button>
                <flux:button icon="chevron-right" wire:click="nextWeek" :aria-label="__('Next week')" />
            </flux:button.group>

            <flux:button icon="shopping-cart" wire:click="startShopping">{{ __('Shopping list') }}</flux:button>
            <flux:button icon="calendar-days" :href="route('meal-plan.template')" wire:navigate>{{ __('Weekly template') }}</flux:button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[64rem] table-fixed border-separate border-spacing-0">
            <thead>
                <tr>
                    <th class="w-28"></th>
                    @foreach ($this->days as $day)
                        @php($isToday = $day['date']->isSameDay($this->today))
                        <th wire:key="head-{{ $day['date']->toDateString() }}" @class([
                            'rounded-t-lg px-2 py-2 text-start align-bottom',
                            'bg-amber-100 dark:bg-amber-400/20' => $isToday,
                        ]) @if ($isToday) aria-current="date" @endif>
                            <flux:text size="sm" class="font-medium">{{ $day['date']->translatedFormat('D') }}</flux:text>
                            <flux:heading>{{ $day['date']->translatedFormat('M j') }}</flux:heading>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($this->mealSlots as $slot)
                    <tr wire:key="slot-{{ $slot->id }}">
                        <th scope="row" class="border-t border-zinc-200 px-2 py-3 text-start align-top dark:border-zinc-700">
                            <flux:heading size="sm">{{ $slot->name }}</flux:heading>
                        </th>
                        @foreach ($this->days as $day)
                            @php($cell = $day['slots']->firstWhere('slot.id', $slot->id))
                            <td wire:key="cell-{{ $slot->id }}-{{ $day['date']->toDateString() }}" @class([
                                'border-t border-zinc-200 p-2 align-top dark:border-zinc-700',
                                'bg-amber-100 dark:bg-amber-400/20' => $day['date']->isSameDay($this->today),
                            ])>
                                @if ($cell)
                                    @include('pages.meal-plan.partials.cell', [
                                        'cell' => $cell,
                                        'day' => $day['date']->toDateString(),
                                        'editable' => ! $slot->trashed(),
                                    ])
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($this->mealSlots->isEmpty())
        <flux:text>
            {{ __('There are no meal slots yet.') }}
            <flux:link :href="route('meal-plan.template')" wire:navigate>{{ __('Add one in the weekly template.') }}</flux:link>
        </flux:text>
    @endif

    @include('pages.meal-plan.modals.add-meal')
    @include('pages.meal-plan.modals.shopping')
</div>
