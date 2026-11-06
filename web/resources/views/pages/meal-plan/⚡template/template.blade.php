<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button :href="route('meal-plan.index')" icon="arrow-left" variant="ghost" wire:navigate :aria-label="__('Back to meal plan')" />

            <div>
                <flux:heading size="xl">{{ __('Weekly Template') }}</flux:heading>
                <flux:text>{{ __('These meals repeat every week. Changing a meal on a specific date only changes that date.') }}</flux:text>
            </div>
        </div>

        <flux:button icon="adjustments-horizontal" wire:click="openSlots">{{ __('Meal slots') }}</flux:button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[64rem] table-fixed border-separate border-spacing-0">
            <thead>
                <tr>
                    <th class="w-28"></th>
                    @foreach ($this->weekdays as $weekday => $name)
                        <th wire:key="head-{{ $weekday }}" class="px-2 py-2 text-start">
                            <flux:heading>{{ $name }}</flux:heading>
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
                        @foreach ($this->weekdays as $weekday => $name)
                            <td wire:key="cell-{{ $slot->id }}-{{ $weekday }}" class="border-t border-zinc-200 p-2 align-top dark:border-zinc-700">
                                @include('pages.meal-plan.partials.cell', [
                                    'cell' => ['slot' => $slot, 'items' => $this->items->get($slot->id . ':' . $weekday, collect())],
                                    'day' => $weekday,
                                    'editable' => true,
                                ])
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($this->mealSlots->isEmpty())
        <flux:text>{{ __('Add a meal slot, like Breakfast or Dinner, to start planning.') }}</flux:text>
    @endif

    @include('pages.meal-plan.modals.add-meal')

    <flux:modal name="meal-slots" class="w-full md:max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Meal slots') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Removing a slot clears it from the template and upcoming days. Past days keep what was planned.') }}</flux:text>
            </div>

            <ul class="space-y-2">
                @foreach ($this->mealSlots as $slot)
                    <li wire:key="slot-setting-{{ $slot->id }}" class="flex items-center gap-2">
                        <flux:input
                            wire:model="slotNames.{{ $slot->id }}"
                            wire:blur="renameSlot({{ $slot->id }})"
                            wire:keydown.enter="renameSlot({{ $slot->id }})"
                            :aria-label="__('Slot name')"
                        />
                        <flux:button icon="chevron-up" variant="ghost" wire:click="moveSlot({{ $slot->id }}, -1)" :disabled="$loop->first" :aria-label="__('Move up')" />
                        <flux:button icon="chevron-down" variant="ghost" wire:click="moveSlot({{ $slot->id }}, 1)" :disabled="$loop->last" :aria-label="__('Move down')" />
                        <flux:button
                            icon="trash"
                            variant="ghost"
                            wire:click="removeSlot({{ $slot->id }})"
                            wire:confirm="{{ __('Remove :name? It will be cleared from the template and upcoming days.', ['name' => $slot->name]) }}"
                            :aria-label="__('Remove slot')"
                        />
                    </li>
                @endforeach
            </ul>
            <flux:error name="slotNames.*" />

            <form wire:submit="addSlot" class="flex gap-2">
                <flux:input wire:model="newSlot" :placeholder="__('Add a slot, like Snack')" />
                <flux:button type="submit" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
            </form>
        </div>
    </flux:modal>
</div>
