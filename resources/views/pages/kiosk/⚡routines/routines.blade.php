<div class="flex h-full flex-col overflow-hidden" wire:poll.600s>
    <div class="flex shrink-0 flex-col gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading>{{ __('Routines') }}</flux:heading>

    </div>

    <flux:kanban class="p-4">
        @foreach ($this->routines as $routine)
            <flux:kanban.column>
                <flux:kanban.column.header :heading="$routine->name" :count="count($routine->tasks)">
                    <x-slot:heading>
                        <flux:icon :icon="$routine->cadence === \App\Enums\RoutineCadence::Weekly ? 'calendar-days' : 'sun'" variant="micro" class="inline" />
                        <span>{{ $routine->name }}</span>
                    </x-slot:heading>
                </flux:kanban.column.header>

                <flux:kanban.column.cards>
                    @foreach ($routine->tasks as $task)
                        <flux:kanban.card :heading="$task->name" as="button" wire:click="toggle('{{ $task->id }}')" />
                    @endforeach
                </flux:kanban.column.cards>
            </flux:kanban.column>
        @endforeach
    </flux:kanban>
</div>
