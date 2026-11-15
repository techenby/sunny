<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('routines.index')" wire:navigate class="cursor-pointer">{{ __('Routines') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $routine->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $routine->name }}</flux:heading>

                @unless ($routine->is_active)
                    <flux:badge size="sm" color="amber">{{ __('Paused') }}</flux:badge>
                @endunless
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:button icon="pencil" wire:click="edit">{{ __('Edit') }}</flux:button>

            <x-ui.actions size="base" :label="__('Actions for :name', ['name' => $routine->name])">
                <flux:menu.item :icon="$routine->is_active ? 'pause' : 'play'" wire:click="toggleActive">
                    {{ $routine->is_active ? __('Pause') : __('Resume') }}
                </flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item icon="trash" variant="danger" wire:click="delete" wire:confirm="{{ __('“:name” will be deleted for everyone on your team.', ['name' => $routine->name]) }}">
                    {{ __('Delete') }}
                </flux:menu.item>
            </x-ui.actions>
        </div>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <flux:heading size="lg">{{ __('Steps') }}</flux:heading>

            @if ($this->steps->isEmpty())
                <flux:text>
                    {{ __('This routine has no steps yet, so it will not show anything on the kiosk.') }}
                </flux:text>
            @else
                <ul class="space-y-2">
                    @foreach ($this->steps as $index => $step)
                        <li wire:key="step-{{ $step->id }}" class="flex items-center gap-2">
                            <flux:text variant="subtle" class="w-6 shrink-0 text-right tabular-nums">{{ $index + 1 }}</flux:text>

                            <flux:input
                                wire:model="names.{{ $step->id }}"
                                wire:blur="rename({{ $step->id }})"
                                wire:keydown.enter="rename({{ $step->id }})"
                                :aria-label="__('Step name')"
                            />

                            <flux:button
                                icon="chevron-up"
                                icon:variant="outline"
                                variant="ghost"
                                class="text-zinc-500! disabled:text-zinc-400! disabled:opacity-100! dark:text-zinc-400! dark:disabled:text-zinc-600!"
                                wire:click="moveUp({{ $step->id }})"
                                :disabled="$loop->first"
                                :aria-label="__('Move up')"
                            />
                            <flux:button
                                icon="chevron-down"
                                icon:variant="outline"
                                variant="ghost"
                                class="text-zinc-500! disabled:text-zinc-400! disabled:opacity-100! dark:text-zinc-400! dark:disabled:text-zinc-600!"
                                wire:click="moveDown({{ $step->id }})"
                                :disabled="$loop->last"
                                :aria-label="__('Move down')"
                            />
                            <flux:button
                                icon="trash"
                                icon:variant="outline"
                                variant="ghost"
                                class="text-zinc-500! hover:text-red-600! dark:text-zinc-400! dark:hover:text-red-400!"
                                wire:click="removeStep({{ $step->id }})"
                                :aria-label="__('Remove step')"
                            />
                        </li>
                    @endforeach
                </ul>
            @endif

            <form wire:submit="addStep" class="flex gap-2">
                <flux:input wire:model="newStep" :placeholder="__('Add a step')" />
                <flux:button type="submit" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
            </form>
        </div>

        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>

            <dl class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Time of day') }}</dt>
                    <dd class="mt-1 flex items-center gap-2 text-zinc-800 dark:text-white">
                        <flux:icon :name="$routine->time_of_day->getIcon()" variant="outline" class="size-5 text-zinc-400" />
                        {{ $routine->time_of_day->getLabel() }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Repeats') }}</dt>
                    <dd class="mt-1 text-zinc-800 dark:text-white">{{ $routine->scheduleSummary() }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Assigned to') }}</dt>
                    <dd class="mt-1 text-zinc-800 dark:text-white">{{ $routine->user?->name ?? __('Household') }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Last done') }}</dt>
                    <dd class="mt-1 text-zinc-800 dark:text-white">{{ $this->lastDone }}</dd>
                </div>
            </dl>
        </flux:card>
    </div>

    @include('pages.routines.modals.routine-form')
</div>
