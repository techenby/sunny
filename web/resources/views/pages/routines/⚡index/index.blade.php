<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Routines') }}</flux:heading>
        <flux:button variant="primary" wire:click="create">{{ __('Add Routine') }}</flux:button>
    </div>

    <flux:text>
        {{ __('Repeating checklists that show up on the kiosk each day they are due. Chores are just routines you name that way.') }}
    </flux:text>

    @if ($this->routines->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
            <flux:icon name="arrow-path-rounded-square" class="mx-auto mb-4 size-10 text-zinc-400" />
            <flux:heading size="lg">{{ __('No routines yet') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Add a morning routine, a chore rotation, or anything else that repeats.') }}
            </flux:text>
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->routines as $routine)
                <flux:card
                    size="sm"
                    wire:key="routine-{{ $routine->id }}"
                    @class([
                        'flex flex-col gap-3',
                        'opacity-60' => ! $routine->is_active,
                    ])
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-start gap-3">
                            <flux:icon :name="$routine->time_of_day->getIcon()" class="mt-0.5 size-5 text-zinc-400" />

                            <div>
                                <flux:link :href="route('routines.show', $routine)" wire:navigate variant="ghost">
                                    <flux:heading>{{ $routine->name }}</flux:heading>
                                </flux:link>

                                <flux:text size="sm">
                                    {{ $routine->time_of_day->getLabel() }} &middot; {{ $routine->scheduleSummary() }}
                                </flux:text>
                            </div>
                        </div>

                        <flux:dropdown>
                            <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" />

                            <flux:menu>
                                <flux:menu.item icon="pencil" wire:click="edit({{ $routine->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item icon="list-bullet" :href="route('routines.show', $routine)" wire:navigate>{{ __('Steps') }}</flux:menu.item>
                                <flux:menu.item
                                    :icon="$routine->is_active ? 'pause' : 'play'"
                                    wire:click="toggleActive({{ $routine->id }})"
                                >
                                    {{ $routine->is_active ? __('Pause') : __('Resume') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $routine->id }})">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$routine->user ? 'blue' : 'zinc'">
                            {{ $routine->user?->name ?? __('Household') }}
                        </flux:badge>

                        <flux:badge size="sm" color="zinc">
                            {{ trans_choice('{0}No steps|{1}:count step|[2,*]:count steps', $routine->steps_count, ['count' => $routine->steps_count]) }}
                        </flux:badge>

                        @unless ($routine->is_active)
                            <flux:badge size="sm" color="amber">{{ __('Paused') }}</flux:badge>
                        @endunless
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    @include('pages.routines.modals.routine-form')
</div>
