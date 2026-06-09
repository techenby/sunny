@teleport('body')
<flux:modal name="feed-form" flyout variant="floating" class="md:w-96">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ $form->editingFeed ? __('Edit Feed') : __('Add Feed') }}</flux:heading>

        <flux:input wire:model="form.name" :label="__('Name')" type="text" required />

        <flux:input wire:model="form.url" :label="__('URL')" type="text" required />

        <flux:select wire:model="form.color" :label="__('Color')" placeholder="Select type" variant="listbox" searchable>
            @foreach (\App\Enums\CalendarColor::cases() as $color)
                <flux:select.option :value="$color->value">
                    <div class="flex items-center gap-2">
                        <div class="size-4 rounded-full border border-zinc-200 dark:border-zinc-700" style="background: {{ $color->value }}"></div> {{ ucfirst($color->name) }}
                    </div>
                </flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" class="mr-2">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ $form->editingFeed ? __('Update') : __('Create') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
