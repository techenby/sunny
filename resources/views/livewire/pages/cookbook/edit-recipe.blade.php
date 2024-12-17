<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">Editing {{ $recipe->name }}</flux:heading>
        <flux:spacer />
    </header>

    <form class="max-w-lg space-y-6">
        <flux:input wire:model="form.name" type="text" label="Name" />
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
