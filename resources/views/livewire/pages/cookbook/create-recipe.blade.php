@push('head')
<script src="https://cdnjs.cloudflare.com/ajax/libs/mousetrap/1.6.5/mousetrap.min.js"></script>
@endpush

<flux:main x-data x-init="Mousetrap.bind(['command+s','ctrl+s'], () => { $wire.save() })" class="space-y-6 max-w-prose">
    <header class="flex">
        <flux:heading size="xl" level="1">Create New Recipe</flux:heading>
        <flux:spacer />
        <flux:button type="submit" form="recipe-form">Save</flux:button>
    </header>

    <form wire:submit="save" id="recipe-form" class="space-y-6">
        <flux:input wire:model="form.name" type="text" label="Name" />

        <flux:field>
            <flux:label>Image</flux:label>

            @if ($this->form->image)
            <div id="preview-image" class="relative mb-3">
                <img src="{{ $this->previewUrl }}" alt="" class="object-cover h-48 w-96 rounded-md">
                <div class="absolute bottom-1 left-1">
                    <flux:button icon="x-mark" size="xs" inset variant="primary" wire:click="clear" />
                </div>
            </div>
            @endif
            <flux:input type="file" wire:model="form.image"/>

            <flux:error name="form.image" />
        </flux:field>

        <flux:input wire:model="form.source" type="text" label="Source" />
        <flux:input wire:model="form.servings" type="text" label="Servings" />

        <div class="grid grid-cols-3 gap-6">
            <flux:input wire:model="form.prep_time" type="text" label="Prep Time" />
            <flux:input wire:model="form.cook_time" type="text" label="Cook Time" />
            <flux:input wire:model="form.total_time" type="text" label="Total Time" />
        </div>

        <flux:textarea wire:model="form.description" label="Description" />
        <flux:editor wire:model="form.ingredients" label="Ingredients" />
        <flux:editor wire:model="form.instructions" label="Instructions" />
        <flux:textarea wire:model="form.notes" label="Notes" />
        <flux:textarea wire:model="form.nutrution" label="Nutrition" />
    </form>
</flux:main>
