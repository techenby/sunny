<flux:modal name="import-items" flyout variant="floating" class="md:w-96">
    <form wire:submit="import" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Import Items') }}</flux:heading>

            <flux:text>{{ __('Upload your Amazon Order History CSV file.') }}</flux:text>
        </div>

        <flux:file-upload wire:model="importForm.file" label="Upload file">
            <flux:file-upload.dropzone
                heading="Drop files or click to browse"
                text="CSV or TXT"
                inline
            />
        </flux:file-upload>

        @if ($importForm->file)
        <flux:file-item :heading="$importForm->file->getClientOriginalName()" :size="$importForm->file->getSize()">
            <x-slot name="actions">
                <flux:file-item.remove />
            </x-slot>
        </flux:file-item>
        @endif

        <flux:heading size="sm">{{ __('Filters') }}</flux:heading>

        <flux:checkbox wire:model="importForm.filterGifts" label="{{ __('Filter out gifts') }}" description="{{ __('Skip items marked as gifts.') }}" />

        <flux:checkbox wire:model="importForm.filterConsumables" label="{{ __('Filter out consumables') }}" description="{{ __('Skip food, drinks, toiletries, and other consumable items.') }}" />

        <flux:date-picker wire:model="importForm.startDate" label="{{ __('From') }}" max="today" >
            <x-slot name="trigger">
                <flux:date-picker.input clearable />
            </x-slot>
        </flux:date-picker>
        <flux:date-picker wire:model="importForm.endDate" label="{{ __('To') }}" max="today" >
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
