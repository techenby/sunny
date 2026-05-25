<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Kiosk Configuration') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Preview your kiosk and manage its data sources.') }}</flux:subheading>
    </div>

    <div class="w-[1020px] h-[600px] overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <iframe src="{{ route('kiosk.calendar') }}" frameborder="0" class="h-full w-full"></iframe>
    </div>

    <flux:tab.group>
        <flux:tabs class="px-4">
            <flux:tab name="calendar">Calendar</flux:tab>
            <flux:tab name="routines">Routines</flux:tab>
            <flux:tab name="chores">Chores</flux:tab>
            <flux:tab name="lists">Lists</flux:tab>
            <flux:tab name="meals">Meals</flux:tab>
            <flux:tab name="settings">Settings</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="calendar">
            <div class="space-y-4 rounded-xl bg-white p-6 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:ring-white/10">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Calendar feeds') }}</flux:heading>
                    <flux:modal.trigger name="feed-form">
                        <flux:button variant="primary" size="sm">{{ __('Add Calendar Feed') }}</flux:button>
                    </flux:modal.trigger>
                </div>

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
                                        <flux:button type="button" variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $feed->id }})" />
                                    </flux:tooltip>

                                    <flux:tooltip :content="__('Remove feed')">
                                        <flux:button type="button" variant="ghost" size="sm" icon="trash" wire:click="delete({{ $feed->id }})" wire:confirm="{{ __('Remove this calendar feed?') }}" />
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
        </flux:tab.panel>
        <flux:tab.panel name="routines">...</flux:tab.panel>
        <flux:tab.panel name="chores">...</flux:tab.panel>
        <flux:tab.panel name="lists">...</flux:tab.panel>
        <flux:tab.panel name="meals">...</flux:tab.panel>
        <flux:tab.panel name="settings">...</flux:tab.panel>
    </flux:tab.group>


    @include('pages.kiosk.modals.feed-form')
</div>
