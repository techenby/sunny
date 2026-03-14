<flux:modal name="import-items" flyout variant="floating">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ __('Import Items') }}</flux:heading>

        <flux:text>{{ __('Upload your Amazon Order History CSV file. Food, drinks, supplements, and consumables will be automatically filtered out.') }}</flux:text>

        <flux:file-upload wire:model="file" multiple label="Upload file">
            <flux:file-upload.dropzone
                heading="Drop files or click to browse"
                text="CSV or TXT"
                inline
            />
        </flux:file-upload>

        <div class="mt-3 flex flex-col gap-2">
            @if ($file)
            <flux:file-item heading="Profile_pic.jpg">
                <x-slot name="actions">
                    <flux:file-item.remove />
                </x-slot>
            </flux:file-item>
            @endif
        </div>
    </form>
</flux:modal>
