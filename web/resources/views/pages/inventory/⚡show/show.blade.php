<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('inventory.index')" class="cursor-pointer">
            {{ __('Inventory') }}
        </flux:breadcrumbs.item>

        @if ($this->breadcrumbs->count() > 1)
            <flux:breadcrumbs.item icon="ellipsis-horizontal" />
        @endif

        @if ($parent = $this->breadcrumbs->last())
            <flux:breadcrumbs.item :href="route('inventory.index', ['parentId' => $parent->id])" class="cursor-pointer">
                {{ $parent->name }}
            </flux:breadcrumbs.item>
        @endif

        <flux:breadcrumbs.item>{{ $item->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:avatar size="sm" :src="$item->thumb_url" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />
            <div>
                <flux:heading size="xl">{{ $item->name }}</flux:heading>
                <flux:text>
                    {{ $item->type->label() }}
                    @if ($this->children->total() > 0)
                        · {{ trans_choice(':count thing inside|:count things inside', $this->children->total()) }}
                    @endif
                </flux:text>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button wire:click="edit" icon="pencil">{{ __('Edit') }}</flux:button>
            <flux:button wire:click="showQrCode" icon="qr-code">{{ __('QR code') }}</flux:button>
            <x-ui.actions size="base">
                <flux:modal.trigger name="duplicate-item">
                    <flux:menu.item icon="square-2-stack">{{ __('Make copies') }}</flux:menu.item>
                </flux:modal.trigger>
                @if ($this->otherTeams->isNotEmpty())
                    <flux:modal.trigger name="move-item">
                        <flux:menu.item icon="arrow-up-tray">{{ __('Move to another team') }}</flux:menu.item>
                    </flux:modal.trigger>
                @endif
                @unless ($item->trashed())
                    <flux:menu.separator />
                    <flux:menu.item wire:click="delete" wire:confirm="{{ $item->deletionWarning() }}" variant="danger" icon="trash">{{ __('Delete') }}</flux:menu.item>
                @endunless
            </x-ui.actions>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @php
                $photos = $this->photos;
                $firstPhotoId = $photos->first()?->id;
                $canEditPhotos = ! $item->trashed();
            @endphp

            @if ($photos->isNotEmpty() || $canEditPhotos)
                <div wire:key="item-photos-{{ $photos->pluck('id')->join('-') }}" x-data="{ current: null }" class="space-y-3">
                    @foreach ($photos as $photo)
                        <div wire:key="photo-viewer-{{ $photo->id }}" x-show="(current ?? {{ $firstPhotoId }}) === {{ $photo->id }}" @unless ($loop->first) style="display: none" @endunless class="relative overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                            <img src="{{ $photo->url }}" loading="lazy" alt="{{ __('Photo :number of :count of :name', ['number' => $loop->iteration, 'count' => $photos->count(), 'name' => $item->name]) }}" class="h-72 w-full object-contain sm:h-96" />

                            @if ($photos->count() > 1)
                                <span class="absolute top-3 left-3 rounded-md bg-zinc-900/70 px-2 py-1 text-xs font-medium text-white">
                                    {{ __(':number of :count', ['number' => $loop->iteration, 'count' => $photos->count()]) }}
                                </span>

                                <div class="absolute inset-y-0 left-3 flex items-center">
                                    <flux:button x-on:click="current = {{ $photos->get($loop->index - 1, $photos->last())->id }}" icon="chevron-left" size="sm" class="rounded-full!" :aria-label="__('Previous photo')" />
                                </div>
                                <div class="absolute inset-y-0 right-3 flex items-center">
                                    <flux:button x-on:click="current = {{ $photos->get($loop->index + 1, $photos->first())->id }}" icon="chevron-right" size="sm" class="rounded-full!" :aria-label="__('Next photo')" />
                                </div>
                            @endif

                            @if ($canEditPhotos)
                                <div class="absolute top-3 right-3 flex items-center gap-2">
                                    @unless ($loop->first)
                                        <flux:button wire:click="makePrimary({{ $photo->id }})" icon="star" size="sm">{{ __('Make primary') }}</flux:button>
                                    @endunless
                                    <flux:button wire:click="deletePhoto({{ $photo->id }})" wire:confirm="{{ __('Delete this photo?') }}" icon="trash" size="sm" :aria-label="__('Delete photo')" />
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div wire:sort="movePhoto" wire:sort:config="{ draggable: '[data-photo]' }" class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                        @foreach ($photos as $photo)
                            <button
                                type="button"
                                wire:key="photo-thumb-{{ $photo->id }}"
                                @if ($canEditPhotos) wire:sort:item="{{ $photo->id }}" @endif
                                data-photo
                                x-on:click="current = {{ $photo->id }}"
                                x-bind:class="(current ?? {{ $firstPhotoId }}) === {{ $photo->id }} ? 'ring-2 ring-accent ring-offset-2 ring-offset-white dark:ring-offset-zinc-900' : ''"
                                class="relative aspect-4/3 overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800 {{ $canEditPhotos ? 'cursor-grab' : '' }}"
                                aria-label="{{ __('Show photo :number', ['number' => $loop->iteration]) }}"
                            >
                                <img src="{{ $photo->thumb_url ?? $photo->url }}" alt="" class="size-full object-cover" />

                                @if ($loop->first && $photos->count() > 1)
                                    <flux:badge size="sm" color="amber" variant="solid" icon="star" class="absolute bottom-1.5 left-1.5">{{ __('Primary') }}</flux:badge>
                                @endif
                            </button>
                        @endforeach

                        @if ($canEditPhotos && $photos->count() < \App\Models\ItemPhoto::MAX_PER_ITEM)
                            <label class="flex aspect-4/3 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-zinc-300 text-sm font-medium text-zinc-600 hover:bg-zinc-50 dark:border-white/20 dark:text-zinc-300 dark:hover:bg-white/5">
                                <input type="file" wire:model="newPhotos" accept="image/*" multiple class="sr-only" data-test="add-photos" />
                                <flux:icon.plus variant="micro" wire:loading.remove wire:target="newPhotos" />
                                <flux:icon.loading variant="micro" wire:loading wire:target="newPhotos" />
                                {{ $photos->isEmpty() ? __('Add a photo') : __('Add photo') }}
                            </label>
                        @endif
                    </div>

                    <flux:error name="newPhotos" />
                    <flux:error name="newPhotos.*" />

                    @if ($canEditPhotos && $photos->count() > 1)
                        <flux:text size="sm" class="flex items-center gap-1.5">
                            <flux:icon.arrows-right-left variant="micro" />
                            {{ __('Drag to reorder. The first photo is the thumbnail in lists.') }}
                        </flux:text>
                    @endif
                </div>
            @endif

            @if ($item->type === \App\Enums\ItemType::Item && $this->children->isEmpty())
                @unless ($item->trashed())
                    <flux:text>
                        {{ __('Does this hold other things?') }}
                        <flux:link as="button" variant="ghost" wire:click="create">{{ __('Put something inside') }}</flux:link>
                    </flux:text>
                @endunless
            @else
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="lg">
                        {{ $this->children->total() > 0 ? __('What’s inside (:count)', ['count' => $this->children->total()]) : __('What’s inside') }}
                    </flux:heading>
                    @if ($this->children->isNotEmpty() && ! $item->trashed())
                        <flux:button wire:click="create" icon="plus" variant="primary" size="sm">{{ __('Add something here') }}</flux:button>
                    @endif
                </div>

                @if ($this->children->isEmpty())
                    <flux:card class="text-center">
                        <flux:text>{{ __('Nothing inside yet.') }}</flux:text>
                        @unless ($item->trashed())
                            <flux:button wire:click="create" icon="plus" variant="primary" class="mt-3">{{ __('Put something inside') }}</flux:button>
                        @endunless
                    </flux:card>
                @else
                    <flux:table :paginate="$this->children" class="w-full">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Name') }}</flux:table.column>
                            <flux:table.column class="w-36">{{ __('Modified') }}</flux:table.column>
                            <flux:table.column class="w-32">{{ __('What’s inside') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->children as $child)
                                <x-ui.table.row :key="$child->id" :href="route('inventory.show', ['item' => $child])">
                                    <flux:table.cell>
                                        <x-ui.table.name :href="route('inventory.show', ['item' => $child])">
                                            <x-slot:thumb>
                                                <x-ui.table.thumb :src="$child->thumb_url" :icon="$child->type->getIcon()" :color="$child->type->getIconColor()" size="base" />
                                            </x-slot:thumb>

                                            {{ $child->truncated_name }}
                                        </x-ui.table.name>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $child->updated_at->diffForHumans() }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($child->children_count > 0)
                                            {{ trans_choice(':count thing|:count things', $child->children_count) }}
                                        @else
                                            <x-ui.table.blank />
                                        @endif
                                    </flux:table.cell>
                                </x-ui.table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
            @endif
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="lg">{{ __('Where it is') }}</flux:heading>
                    @unless ($item->trashed())
                        <flux:button wire:click="startMove" icon="arrow-right" size="sm">{{ __('Move') }}</flux:button>
                    @endunless
                </div>

                <ol>
                    @forelse ($this->breadcrumbs as $ancestor)
                        <li>
                            <a href="{{ route('inventory.show', ['item' => $ancestor]) }}" wire:navigate class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-zinc-800/5 dark:hover:bg-white/10">
                                <flux:avatar size="sm" :src="$ancestor->thumb_url" :icon="$ancestor->type->getIcon()" :color="$ancestor->type->getIconColor()" icon:variant="outline" />
                                <flux:heading class="flex-1">{{ $ancestor->name }}</flux:heading>
                                <flux:icon.chevron-right variant="micro" class="text-zinc-400" />
                            </a>
                        </li>
                        @unless ($loop->last)
                            <li aria-hidden="true" class="ml-[15px] h-3 w-0.5 bg-zinc-200 dark:bg-white/20"></li>
                        @endunless
                    @empty
                        <li class="flex items-center gap-3 py-1.5">
                            <flux:avatar size="sm" icon="home" icon:variant="outline" />
                            <div>
                                @if ($item->type === \App\Enums\ItemType::Location)
                                    <flux:heading>{{ __('Home') }}</flux:heading>
                                    <flux:text size="sm">{{ __('One of the places in your home') }}</flux:text>
                                @else
                                    <flux:heading>{{ __('Not in a place yet') }}</flux:heading>
                                    <flux:text size="sm">{{ __('Choose Move to put it away.') }}</flux:text>
                                @endif
                            </div>
                        </li>
                    @endforelse
                    <li aria-hidden="true" class="ml-[15px] h-3 w-0.5 bg-zinc-200 dark:bg-white/20"></li>
                    <li class="flex items-center gap-3 py-1.5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-accent">
                            <span class="size-2.5 rounded-full bg-accent"></span>
                        </span>
                        <div>
                            <flux:heading>{{ $item->name }}</flux:heading>
                            <flux:text size="sm" class="font-medium">{{ __('You’re here') }}</flux:text>
                        </div>
                    </li>
                </ol>
            </flux:card>

            @if ($item->metadata)
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Details') }}</flux:heading>

                    <dl>
                        @foreach ($item->metadata as $key => $value)
                            <dt class="[:where(&)]:font-normal [:where(&)]:text-sm [:where(&)]:text-zinc-500 [:where(&)]:dark:text-white/70 mt-2 mb-1">{{ ucfirst($key) }}</dt>
                            <dd class="font-medium [:where(&)]:text-zinc-800 [:where(&)]:dark:text-white text-sm [&:has(+[data-flux-subheading])]:mb-2 [[data-flux-subheading]+&]:mt-2">{{ $value }}</dd>
                        @endforeach
                    </dl>
                </flux:card>
            @endif
        </div>
    </div>

    @include('pages.inventory.modals.item-form')
    @include('pages.inventory.modals.qr-code')
    @include('pages.inventory.modals.move-item')
    @include('pages.inventory.modals.move-to-place')
    @include('pages.inventory.modals.duplicate-item')
</div>
