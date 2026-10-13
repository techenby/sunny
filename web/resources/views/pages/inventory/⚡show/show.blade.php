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

    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button :href="route('inventory.index', ['parentId' => $item->parent_id])" icon="arrow-left" variant="ghost" wire:navigate />
            <flux:avatar size="sm" :src="$item->thumb_url" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />
            <div>
                <flux:heading size="xl">{{ $item->name }}</flux:heading>
                <flux:text>
                    {{ $item->type->name }}
                    @if ($this->breadcrumbs->isNotEmpty())
                        · {{ __('in') }} <flux:link :href="route('inventory.show', ['item' => $this->breadcrumbs->last()])" wire:navigate>{{ $this->breadcrumbs->last()->name }}</flux:link>
                    @endif
                </flux:text>
            </div>
        </div>
        <div class="flex items-center gap-1">
            @unless ($item->trashed())
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add Item') }}</flux:button>
            @endunless
            <flux:dropdown align="end">
                <flux:button icon="ellipsis-vertical" variant="ghost" />
                <flux:menu>
                    <flux:menu.item wire:click="edit" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                    <flux:menu.item wire:click="showQrCode" icon="qr-code">{{ __('QR Code') }}</flux:menu.item>
                    <flux:modal.trigger name="duplicate-item">
                        <flux:menu.item icon="square-2-stack">{{ __('Duplicate') }}</flux:menu.item>
                    </flux:modal.trigger>
                    @if ($this->otherTeams->isNotEmpty())
                        <flux:modal.trigger name="move-item">
                            <flux:menu.item icon="arrow-up-tray">{{ __('Move to Team') }}</flux:menu.item>
                        </flux:modal.trigger>
                    @endif
                    <flux:menu.item wire:click="delete" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this item?') }}">{{ __('Delete') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div @class(['space-y-6', 'lg:col-span-2' => $item->metadata, 'lg:col-span-3' => ! $item->metadata])>
            @if ($item->photo_url)
                <img src="{{ $item->photo_url }}" alt="{{ $item->name }}" class="w-full rounded-lg object-cover max-h-96" />
            @endif

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Contents') }}</flux:heading>
                    @if ($this->children->total() > 0)
                        <flux:text>{{ trans_choice(':count item|:count items', $this->children->total()) }}</flux:text>
                    @endif
                </div>

                @if ($this->children->isEmpty())
                    <flux:card class="text-center">
                        <flux:text>{{ __('Nothing in here yet.') }}</flux:text>
                        @unless ($item->trashed())
                            <flux:button wire:click="create" icon="plus" variant="ghost" size="sm" class="mt-2">{{ __('Add an item here') }}</flux:button>
                        @endunless
                    </flux:card>
                @else
                    <flux:table :paginate="$this->children">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Name') }}</flux:table.column>
                            <flux:table.column>{{ __('Modified') }}</flux:table.column>
                            <flux:table.column>{{ __('Contents') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->children as $child)
                                <flux:table.row :key="$child->id">
                                    <flux:table.cell>
                                        <flux:link :href="route('inventory.show', ['item' => $child])" wire:navigate inset="top bottom" class="!flex !items-center gap-3">
                                            <flux:avatar size="xs" :src="$child->thumb_url" :icon="$child->type->getIcon()" :color="$child->type->getIconColor()" icon:variant="outline" />
                                            <span>{{ $child->truncated_name }}</span>
                                        </flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $child->updated_at->diffForHumans() }}</flux:table.cell>
                                    <flux:table.cell>{{ $child->children_count }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </div>

        @if ($item->metadata)
            <div>
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Details') }}</flux:heading>

                    <dl>
                        @foreach ($item->metadata as $key => $value)
                            <dt class="[:where(&)]:font-normal [:where(&)]:text-sm [:where(&)]:text-zinc-500 [:where(&)]:dark:text-white/70 mt-2 mb-1">{{ ucfirst($key) }}</dt>
                            <dd class="font-medium [:where(&)]:text-zinc-800 [:where(&)]:dark:text-white text-sm [&:has(+[data-flux-subheading])]:mb-2 [[data-flux-subheading]+&]:mt-2">{{ $value }}</dd>
                        @endforeach
                    </dl>
                </flux:card>
            </div>
        @endif
    </div>

    @include('pages.inventory.modals.item-form')
    @include('pages.inventory.modals.qr-code')
    @include('pages.inventory.modals.move-item')
    @include('pages.inventory.modals.duplicate-item')
</div>
