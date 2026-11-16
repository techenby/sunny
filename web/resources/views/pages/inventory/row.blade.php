<x-ui.table.row
    :key="$item->id"
    :href="filled($search) ? route('inventory.show', ['item' => $item]) : null"
    action="$wire.navigateDown({{ $item->id }})"
>
    <flux:table.cell>
        <flux:checkbox :value="$item->id"/>
    </flux:table.cell>
    <flux:table.cell>
        @if (filled($search))
            <div class="flex items-center gap-2">
                <x-ui.table.name :href="route('inventory.show', ['item' => $item])">
                    <x-slot:thumb>
                        <x-ui.table.thumb :src="$item->thumb_url" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" size="base" />
                    </x-slot:thumb>

                    {{ $item->truncated_name }}
                </x-ui.table.name>
                @if ($detail = $this->matchedDetail($item))
                    <flux:text size="sm">{{ $detail }}</flux:text>
                @endif
            </div>
        @else
            <x-ui.table.name wire:click="navigateDown({{ $item->id }})">
                <x-slot:thumb>
                    <x-ui.table.thumb :src="$item->thumb_url" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" size="base" />
                </x-slot:thumb>

                {{ $item->truncated_name }}
            </x-ui.table.name>
        @endif
    </flux:table.cell>
    @if (filled($search))
        <flux:table.cell>
            @if ($trail = $this->trails[$item->id] ?? [])
                {{ implode(' › ', $trail) }}
            @elseif ($item->type === App\Enums\ItemType::Location)
                {{ __('Home') }}
            @else
                {{ __('Not in a place yet') }}
            @endif
        </flux:table.cell>
    @else
        <flux:table.cell>{{ $item->updated_at->diffForHumans() }}</flux:table.cell>
        <flux:table.cell>
            @if ($item->children_count > 0)
                {{ trans_choice(':count thing|:count things', $item->children_count) }}
            @else
                <x-ui.table.blank />
            @endif
        </flux:table.cell>
    @endif
    <flux:table.cell align="end">
        @if ($filters['showTrashed'] ?? false)
            <div class="flex items-center gap-1">
                <flux:button wire:click="restore({{ $item->id }})" wire:confirm="{{ __('Bring “:name” back into your inventory?', ['name' => $item->name]) }}" variant="ghost" size="sm" icon="arrow-uturn-left">{{ __('Restore') }}</flux:button>
                <flux:button wire:click="forceDelete({{ $item->id }})" wire:confirm="{{ __('“:name” will be deleted forever for everyone on your team. You won’t be able to bring it back.', ['name' => $item->name]) }}" variant="danger" size="sm" icon="trash">{{ __('Delete forever') }}</flux:button>
            </div>
        @else
            <x-ui.actions :label="__('Actions for :name', ['name' => $item->name])">
                <flux:menu.item :href="route('inventory.show', ['item' => $item])" icon="eye" wire:navigate>{{ __('View') }}</flux:menu.item>
                <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                <flux:menu.item wire:click="showQrCode({{ $item->id }})" icon="qr-code">{{ __('QR code') }}</flux:menu.item>
                <flux:menu.item wire:click="openDuplicateModal({{ $item->id }})" icon="square-2-stack">{{ __('Make copies') }}</flux:menu.item>
                @if ($this->otherTeams->isNotEmpty())
                        <flux:menu.item wire:click="openMoveModal({{ $item->id }})" icon="arrow-up-tray">{{ __('Move to another team') }}</flux:menu.item>
                @endif
                <flux:menu.item wire:click="delete({{ $item->id }})" variant="danger" icon="trash" wire:confirm="{{ $item->deletionWarning() }}">{{ __('Delete') }}</flux:menu.item>
            </x-ui.actions>
        @endif
    </flux:table.cell>
</x-ui.table.row>
