<div class="flex h-full flex-col overflow-hidden" wire:poll.600s>
    <div class="flex shrink-0 items-baseline gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <x-ui.clock :timezone="$this->team->timezone" />
        <flux:heading size="lg">
            {{ $this->days->first()['date']->translatedFormat('M j') }} – {{ $this->days->last()['date']->translatedFormat('M j') }}
        </flux:heading>
    </div>

    @if ($this->mealSlots->isEmpty())
        <div class="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center">
            <flux:icon name="cooking-pot" class="size-12 text-zinc-400" />
            <flux:heading size="lg">{{ __('No meals planned') }}</flux:heading>
            <flux:text>{{ __('Open Sunny on your phone or computer to plan the week.') }}</flux:text>
        </div>
    @else
        <div class="min-h-0 flex-1 overflow-auto p-4">
            <table class="w-full min-w-[56rem] table-fixed border-separate border-spacing-0">
                <thead>
                    <tr>
                        <th class="w-28"></th>
                        @foreach ($this->days as $day)
                            @php($isToday = $day['date']->isSameDay($this->team->today()))
                            <th wire:key="head-{{ $day['date']->toDateString() }}" @class([
                                'rounded-t-xl px-3 py-3 text-start',
                                'bg-(--color-accent) text-(--color-accent-foreground)' => $isToday,
                            ]) @if ($isToday) aria-current="date" @endif>
                                <div @class(['text-sm font-medium', 'text-zinc-500 dark:text-zinc-400' => ! $isToday])>{{ $day['date']->translatedFormat('D') }}</div>
                                <div class="text-lg font-semibold">{{ $day['date']->translatedFormat('j') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->mealSlots as $slot)
                        <tr wire:key="slot-{{ $slot->id }}">
                            <th scope="row" class="border-t border-zinc-200 px-3 py-4 text-start align-top dark:border-zinc-700">
                                <flux:heading>{{ $slot->name }}</flux:heading>
                            </th>
                            @foreach ($this->days as $day)
                                @php($cell = $day['slots']->firstWhere('slot.id', $slot->id))
                                <td wire:key="cell-{{ $slot->id }}-{{ $day['date']->toDateString() }}" @class([
                                    'border-t border-zinc-200 px-3 py-4 align-top text-lg dark:border-zinc-700',
                                    'bg-(--color-accent)/10' => $day['date']->isSameDay($this->team->today()),
                                ])>
                                    @foreach ($cell['items'] ?? [] as $item)
                                        <div wire:key="item-{{ $item->getMorphClass() }}-{{ $item->id }}" class="break-words leading-snug [&:not(:first-child)]:mt-2">{{ $item->label() }}</div>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
