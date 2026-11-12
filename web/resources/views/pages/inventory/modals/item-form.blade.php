@php
    $formParent = $form->parent_id ? $this->parentItems->firstWhere('id', $form->parent_id) : null;
    $noPlaceLabel = $form->type === \App\Enums\ItemType::Location->value ? __('Home') : __('Not in a place yet');
@endphp

@teleport('body')
<flux:modal name="item-form" flyout variant="floating" class="md:w-96">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ $form->editingItem ? __('Edit :name', ['name' => $form->editingItem->name]) : __('Add something') }}</flux:heading>

        <flux:input wire:model="form.name" :label="__('What is it called?')" :placeholder="__('Camping tent')" type="text" required />

        <flux:select wire:model.live="form.parent_id" :label="__('Where does it go?')" :placeholder="$noPlaceLabel" variant="listbox" searchable clearable>
            @foreach ($this->parentItems as $parentItem)
                <flux:select.option :value="$parentItem->id">{{ $parentItem->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:radio.group wire:model.live="form.type" :label="__('What kind is it?')" :description="__('Not sure? Leave it as a thing.')" variant="cards" class="flex-col">
            @foreach (\App\Enums\ItemType::cases() as $type)
                <flux:radio :value="$type->value" :icon="$type->getIcon()" :label="$type->label()" :description="$type->description()" />
            @endforeach
        </flux:radio.group>

        <flux:field>
            <flux:label>{{ __('Photo (optional)') }}</flux:label>

            <flux:file-upload wire:model="form.photo">
                <flux:file-upload.dropzone :heading="__('Drop photo here or click to browse')" :text="__('JPG, PNG up to 5MB')" inline />
            </flux:file-upload>

            <div class="mt-4 flex flex-col gap-2">
            @if ($this->form->photo)
                <flux:file-item :heading="$this->form->photo->getClientOriginalName()" :image="$this->form->photo->isPreviewable() ? $this->form->photo->temporaryUrl() : null" :size="$this->form->photo->getSize()">
                    <x-slot name="actions">
                        <flux:button wire:click="removePhoto" variant="ghost" size="sm" icon="x-mark" :aria-label="__('Remove photo')" />
                    </x-slot>
                </flux:file-item>
            @elseif ($this->form->existingPhotoUrl)
                <flux:file-item :heading="basename($this->form->editingItem->photo_path)" :image="$this->form->existingPhotoUrl">
                    <x-slot name="actions">
                        <flux:button wire:click="removePhoto" variant="ghost" size="sm" icon="x-mark" :aria-label="__('Remove photo')" />
                    </x-slot>
                </flux:file-item>
            @endif
            </div>
            <flux:error name="form.photo" />
        </flux:field>

        <div>
            <flux:label class="mb-2">{{ __('Extra details (optional)') }}</flux:label>

            <div class="space-y-2">
                @foreach ($form->metadata as $index => $pair)
                    <div class="flex items-start gap-2">
                        <flux:input wire:model="form.metadata.{{ $index }}.key" placeholder="{{ __('e.g. Brand') }}" :aria-label="__('Detail :number name', ['number' => $index + 1])" size="sm" />
                        <flux:input wire:model="form.metadata.{{ $index }}.value" placeholder="{{ __('e.g. REI') }}" :aria-label="__('Detail :number', ['number' => $index + 1])" size="sm" />
                        <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="removeMetadata({{ $index }})" :aria-label="__('Remove detail :number', ['number' => $index + 1])" />
                    </div>
                @endforeach
            </div>

            <flux:button variant="ghost" size="sm" icon="plus" wire:click="addMetadata" class="mt-2">{{ __('Add a detail, like color or size') }}</flux:button>
        </div>

        <div class="flex">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" class="mr-2">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">
                @if ($form->editingItem)
                    {{ __('Save changes') }}
                @elseif ($formParent)
                    {{ __('Save to :place', ['place' => $formParent->name]) }}
                @else
                    {{ __('Save') }}
                @endif
            </flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
