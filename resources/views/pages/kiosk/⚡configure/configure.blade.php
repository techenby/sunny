<div class="grid min-h-dvh gap-6 p-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
    <div class="min-h-[34rem] overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <iframe src="{{ route('kiosk.calendar') }}" frameborder="0" class="h-full w-full"></iframe>
    </div>

    <aside class="space-y-6">
        <form wire:submit="saveFeed" class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">
                    {{ $editingFeedId ? __('Edit feed') : __('Add feed') }}
                </flux:heading>

                @if ($editingFeedId)
                    <flux:button type="button" variant="ghost" size="sm" wire:click="resetFeedForm">
                        {{ __('Cancel') }}
                    </flux:button>
                @endif
            </div>

            <flux:input wire:model="feedName" :label="__('Name')" required />

            <flux:input wire:model="feedUrl" :label="__('Calendar URL')" type="url" required />

            <flux:select wire:model="feedColor" :label="__('Color')" variant="listbox">
                @foreach (App\Enums\CalendarColor::cases() as $color)
                    <flux:select.option :value="$color->value">{{ $color->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center gap-3">
                <span class="size-4 rounded-full ring-1 ring-black/10" style="background: {{ $feedColor }}"></span>

                <flux:button type="submit" variant="primary">
                    {{ $editingFeedId ? __('Update feed') : __('Add feed') }}
                </flux:button>
            </div>
        </form>

        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Calendar feeds') }}</flux:heading>

            <div class="space-y-2">
                @forelse ($this->feeds as $feed)
                    <div wire:key="calendar-feed-{{ $feed->id }}" class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $feed->color }}"></span>
                                    <span class="truncate font-medium">{{ $feed->name }}</span>
                                </div>

                                <flux:text class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $feed->url }}
                                </flux:text>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <flux:tooltip :content="__('Edit feed')">
                                    <flux:button type="button" variant="ghost" size="sm" icon="pencil" wire:click="editFeed({{ $feed->id }})" />
                                </flux:tooltip>

                                <flux:tooltip :content="__('Remove feed')">
                                    <flux:button type="button" variant="ghost" size="sm" icon="trash" wire:click="deleteFeed({{ $feed->id }})" wire:confirm="{{ __('Remove this calendar feed?') }}" />
                                </flux:tooltip>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        {{ __('No calendar feeds yet.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </aside>
</div>
