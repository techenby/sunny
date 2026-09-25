@teleport('body')
<flux:modal name="checklist-form" flyout variant="floating" class="md:w-96">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ $form->editingChecklist ? __('Edit List') : __('Add List') }}</flux:heading>

        <flux:input wire:model="form.name" :label="__('Name')" :placeholder="__('Groceries')" type="text" required />

        <flux:select wire:model="form.type" :label="__('Type')" variant="listbox">
            @foreach (\App\Enums\ChecklistType::cases() as $type)
                <flux:select.option :value="$type->value">{{ $type->getLabel() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="form.user_id" :label="__('Belongs to')" variant="listbox">
            <flux:select.option value="">{{ __('Household') }}</flux:select.option>
            @foreach ($this->members as $member)
                <flux:select.option :value="$member->id">{{ $member->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" class="mr-2">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ $form->editingChecklist ? __('Update') : __('Create') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
