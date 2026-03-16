<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('inventory.index')" class="cursor-pointer">
            {{ __('Inventory') }}
        </flux:breadcrumbs.item>

        @foreach ($this->breadcrumbs as $breadcrumb)
            <flux:breadcrumbs.item :href="route('inventory.index', ['parentId' => $breadcrumb->id])" class="cursor-pointer">
                {{ $breadcrumb->name }}
            </flux:breadcrumbs.item>
        @endforeach

        <flux:breadcrumbs.item>
            {{ $item->name }}
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <flux:button :href="route('inventory.index', ['parentId' => $item->parent_id])" icon="arrow-left" variant="ghost" wire:navigate />
            <flux:heading size="xl" class="flex items-center gap-3">
                <flux:avatar size="sm" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />
                {{ $item->name }}
            </flux:heading>
        </div>
        <flux:dropdown align="end">
            <flux:button icon="ellipsis-vertical" variant="ghost" />
            <flux:menu>
                <flux:menu.item wire:click="edit" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                <flux:menu.item wire:click="showQrCode" icon="qr-code">{{ __('QR Code') }}</flux:menu.item>
                @if ($this->otherTeams->isNotEmpty())
                    <flux:modal.trigger name="move-item">
                        <flux:menu.item icon="arrow-up-tray">{{ __('Move to Team') }}</flux:menu.item>
                    </flux:modal.trigger>
                @endif
                <flux:menu.item wire:click="delete" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this item?') }}">{{ __('Delete') }}</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($item->photo_path)
                <img src="{{ Storage::temporaryUrl($item->photo_path, now()->addMinutes(30)) }}" alt="{{ $item->name }}" class="w-full rounded-lg object-cover max-h-96" />
            @endif

            <flux:card>
                <flux:heading size="lg" class="mb-2">{{ __('Metadata') }}</flux:heading>

                @if ($item->metadata !== null)
                <dl>
                    @foreach ($item->metadata as $key => $value)
                        <dt class="[:where(&)]:font-normal [:where(&)]:text-sm [:where(&)]:text-zinc-500 [:where(&)]:dark:text-white/70 mt-2 mb-1">{{ ucfirst($key) }}</dt>
                        <dd class="font-medium [:where(&)]:text-zinc-800 [:where(&)]:dark:text-white text-sm [&:has(+[data-flux-subheading])]:mb-2 [[data-flux-subheading]+&]:mt-2">{{ $value }}</dd>
                    @endforeach
                </dl>
                @endif
            </flux:card>
        </div>
    </div>

    @include('pages.inventory.modals.item-form')
    @include('pages.inventory.modals.qr-code')
    @include('pages.inventory.modals.move-item')
</div>
