<section class="w-full">
    <div class="mb-4 flex items-center justify-between">
        <flux:heading size="xl">{{ __('Inventory') }}</flux:heading>
        <flux:button variant="primary" wire:click="create">{{ __('Add Item') }}</flux:button>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search inventory...')" icon="magnifying-glass" class="max-w-sm" />
    </div>

    <div class="mb-4 flex items-center gap-2">
        @if ($this->parentId)
            <flux:button variant="ghost" size="sm" icon="arrow-left" wire:click="navigateUp" />
        @endif

        <flux:breadcrumbs>
            <flux:breadcrumbs.item wire:click="$set('parentId', null)" class="cursor-pointer">
                {{ __('All') }}
            </flux:breadcrumbs.item>

            @foreach ($this->breadcrumbs as $breadcrumb)
                <flux:breadcrumbs.item wire:click="navigateDown({{ $breadcrumb->id }})" class="cursor-pointer">
                    {{ $breadcrumb->name }}
                </flux:breadcrumbs.item>
            @endforeach
        </flux:breadcrumbs>
    </div>

    <flux:table :paginate="$this->items">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Modified') }}</flux:table.column>
            <flux:table.column>{{ __('Contents') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell>
                        <flux:link wire:click="navigateDown({{ $item->id }})" inset="top bottom" class="!flex !items-center gap-3">
                            <flux:avatar size="xs" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />
                            <span>{{ $item->name }}</span>
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $item->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $item->children_count }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />

                            <flux:menu>
                                <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item wire:click="delete({{ $item->id }})" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this item?') }}">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row key="empty-item">
                    <flux:table.cell colspan="4" class="text-center">
                        <flux:text variant="subtle" size="xl">{{ __('No items found') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @teleport('body')
    <flux:modal name="item-form" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->editingItem ? __('Edit Item') : __('Add Item') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" type="text" required />

            <flux:select wire:model="form.type" :label="__('Type')" placeholder="Select type" variant="listbox" searchable>
                @foreach (\App\Enums\ItemType::cases() as $type)
                    <flux:select.option :value="$type->value">{{ ucfirst($type->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.parent_id" :label="__('Parent')" variant="listbox" searchable>
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->parentItems as $parentItem)
                    <flux:select.option :value="$parentItem->id">{{ $parentItem->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div>
                <flux:label class="mb-2">{{ __('Metadata') }}</flux:label>

                <div class="space-y-2">
                    @foreach ($form->metadata as $index => $pair)
                        <div class="flex items-start gap-2">
                            <flux:input wire:model="form.metadata.{{ $index }}.key" placeholder="{{ __('Key') }}" size="sm" />
                            <flux:input wire:model="form.metadata.{{ $index }}.value" placeholder="{{ __('Value') }}" size="sm" />
                            <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="removeMetadata({{ $index }})" />
                        </div>
                    @endforeach
                </div>

                <flux:button variant="ghost" size="sm" icon="plus" wire:click="addMetadata" class="mt-2">{{ __('Add field') }}</flux:button>
            </div>

            <div class="flex">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" class="mr-2">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $form->editingItem ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endteleport
</section>