<flux:modal name="import-items" flyout variant="floating" class="md:w-96">
    <form wire:submit="import" class="space-y-6">
        <flux:heading size="lg">{{ __('Import Items') }}</flux:heading>

        <flux:text>{{ __('Upload your Amazon Order History CSV file.') }}</flux:text>

        <flux:file-upload wire:model="file" label="Upload file">
            <flux:file-upload.dropzone
                heading="Drop files or click to browse"
                text="CSV or TXT"
                inline
            />
        </flux:file-upload>

        <div class="mt-3 flex flex-col gap-2">
            @if ($file)
            <flux:file-item :heading="$file->getClientOriginalName()" :size="$file->getSize()">
                <x-slot name="actions">
                    <flux:file-item.remove />
                </x-slot>
            </flux:file-item>
            @endif
        </div>

        <flux:separator />

        <flux:heading size="sm">{{ __('Filters') }}</flux:heading>

        <flux:checkbox wire:model="filterGifts" label="{{ __('Filter out gifts') }}" description="{{ __('Skip items marked as gifts.') }}" />

        <flux:checkbox wire:model="filterConsumables" label="{{ __('Filter out consumables') }}" description="{{ __('Skip food, drinks, toiletries, and other consumable items.') }}" />

        <flux:date-picker wire:model="startDate" label="{{ __('From') }}" max="today" >
            <x-slot name="trigger">
                <flux:date-picker.input clearable />
            </x-slot>
        </flux:date-picker>
        <flux:date-picker wire:model="endDate" label="{{ __('To') }}" max="today" >
            <x-slot name="trigger">
                <flux:date-picker.input clearable />
            </x-slot>
        </flux:date-picker>

        <div class="flex">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" class="mr-2">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Import') }}</flux:button>
        </div>
    </form>
</flux:modal>
