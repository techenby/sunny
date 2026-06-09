<div class="space-y-6" wire:poll.60s>
    <div class="flex items-center justify-between">
        <flux:heading size="lg">{{ __('Calendar feeds') }}</flux:heading>
        <flux:modal.trigger name="feed-form">
            <flux:button variant="primary" size="sm">{{ __('Add Calendar Feed') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="space-y-2">
        @forelse ($this->feeds as $feed)
            <flux:card size="sm" wire:key="calendar-feed-{{ $feed->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <flux:heading class="flex items-center gap-2">
                            <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $feed->color }}"></span>
                            <span class="truncate font-medium">{{ $feed->name }}</span>
                        </flux:heading>

                        <flux:text variant="subtle" class="truncate max-w-64" title="{{ $feed->url }}">{{ $feed->url }}</flux:text>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <flux:tooltip :content="__('Edit feed')">
                            <flux:button type="button" variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $feed->id }})" />
                        </flux:tooltip>

                        <flux:tooltip :content="__('Remove feed')">
                            <flux:button type="button" variant="ghost" size="sm" icon="trash" wire:click="delete({{ $feed->id }})" wire:confirm="{{ __('Remove this calendar feed?') }}" />
                        </flux:tooltip>
                    </div>
                </div>
            </flux:card>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No calendar feeds yet.') }}
            </div>
        @endforelse
    </div>

    @include('pages.kiosk.modals.feed-form')
</div>
