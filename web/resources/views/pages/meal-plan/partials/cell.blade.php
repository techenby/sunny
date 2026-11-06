{{-- One slot on one day. $day is a date string on the meal plan and a weekday number on the template. --}}
@php($slotId = $cell['slot']->id)

<div class="group flex min-h-16 flex-col gap-1">
    @foreach ($cell['items'] as $index => $item)
        <div wire:key="item-{{ $slotId }}-{{ $day }}-{{ $item->getMorphClass() }}-{{ $item->id }}" class="flex items-start gap-1 rounded-md bg-zinc-100 px-2 py-1 text-sm dark:bg-zinc-700/60">
            <span class="min-w-0 flex-1 break-words">
                @if ($item->recipe)
                    <flux:link :href="route('recipes.show', $item->recipe)" variant="ghost" wire:navigate>{{ $item->label() }}</flux:link>
                @else
                    {{ $item->label() }}
                @endif
            </span>

            @if ($editable)
                <flux:dropdown position="bottom" align="end">
                    <flux:button size="xs" variant="subtle" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Meal options')" />

                    <flux:menu>
                        @unless ($loop->first)
                            <flux:menu.item icon="chevron-up" wire:click="moveMeal({{ $slotId }}, @js($day), {{ $index }}, -1)">{{ __('Move up') }}</flux:menu.item>
                        @endunless
                        @unless ($loop->last)
                            <flux:menu.item icon="chevron-down" wire:click="moveMeal({{ $slotId }}, @js($day), {{ $index }}, 1)">{{ __('Move down') }}</flux:menu.item>
                        @endunless
                        <flux:menu.item icon="trash" variant="danger" wire:click="removeMeal({{ $slotId }}, @js($day), {{ $index }})">{{ __('Remove') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>
    @endforeach

    @if ($editable)
        <div class="mt-auto flex items-center gap-1">
            <flux:button size="xs" variant="subtle" icon="plus" wire:click="startAdding({{ $slotId }}, @js($day))" :aria-label="__('Add a meal')">
                {{ __('Add') }}
            </flux:button>

            @if ($cell['overridden'] ?? false)
                <flux:tooltip :content="__('Changed from the weekly template. Select to undo.')">
                    <flux:button size="xs" variant="subtle" icon="arrow-uturn-left" wire:click="resetSlot({{ $slotId }}, @js($day))" :aria-label="__('Reset to template')" />
                </flux:tooltip>
            @endif
        </div>
    @endif
</div>
