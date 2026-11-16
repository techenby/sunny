@php
    $storage = App\Support\Inventory\StorageSuggestions::storageFor($place->name);
    $thing = App\Support\Inventory\StorageSuggestions::exampleThingFor($place->name);
@endphp

<div class="rounded-xl border border-zinc-200 bg-zinc-50 px-6 py-12 text-center dark:border-white/10 dark:bg-white/5" data-test="empty-place">
    <div class="mx-auto flex max-w-xl flex-col items-center gap-2">
        <flux:heading size="xl">{{ __(':place is ready for its first Storage', ['place' => $place->name]) }}</flux:heading>
        <flux:text size="lg">{{ __('Storage is any shelf, box or drawer in a Place. Put Things in it, and later you can search for anything and see exactly where it lives.') }}</flux:text>
    </div>

    <div class="mt-8 flex flex-col items-center justify-center gap-2 sm:flex-row sm:gap-3">
        @foreach ([
            [App\Enums\ItemType::Location, $place->name, __('Place · you’re here')],
            [App\Enums\ItemType::Bin, $storage[0], App\Enums\ItemType::Bin->label()],
            [App\Enums\ItemType::Item, $thing, App\Enums\ItemType::Item->label()],
        ] as [$type, $name, $label])
            @unless ($loop->first)
                <flux:icon.chevron-right variant="micro" class="hidden text-zinc-400 sm:block" />
                <flux:icon.chevron-down variant="micro" class="text-zinc-400 sm:hidden" />
            @endunless

            <div class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-white py-2 ps-2 pe-4 text-start shadow-xs dark:border-white/10 dark:bg-zinc-800">
                <flux:avatar size="sm" :icon="$type->getIcon()" :color="$type->getIconColor()" icon:variant="outline" />
                <div>
                    <flux:heading>{{ $name }}</flux:heading>
                    <flux:text size="sm">{{ $label }}</flux:text>
                </div>
            </div>
        @endforeach
    </div>

    <flux:text size="sm" class="mt-4 inline-flex items-center gap-1.5">
        <flux:icon.magnifying-glass variant="micro" />
        {{ __('Later, search “:thing” and see :place › :storage', ['thing' => Str::lower($thing), 'place' => $place->name, 'storage' => $storage[0]]) }}
    </flux:text>

    <div class="mt-10 flex flex-col items-center gap-3">
        <flux:heading>{{ __('Add Storage with one click') }}</flux:heading>

        <div class="flex flex-wrap justify-center gap-2">
            @foreach ($storage as $name)
                <flux:button size="sm" icon="plus" wire:click="addStorage(@js($name))" wire:key="storage-{{ Str::slug($name) }}">{{ $name }}</flux:button>
            @endforeach
        </div>

        <flux:text size="sm">{{ __('Or choose Add to name your own Storage, or to add a Thing on its own.') }}</flux:text>
    </div>
</div>
