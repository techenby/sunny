<flux:card>
    <div class="grid gap-6 md:grid-cols-2">
        <flux:input
            wire:model="form.name"
            :label="__('Name')"
            type="text"
            required
        />

        <flux:input wire:model="form.source" :label="__('Source (URL or text)')" type="text">
            <x-slot name="iconTrailing">
                <flux:tooltip content="Import from URL">
                    <flux:button wire:click="import" size="sm" variant="subtle" icon="arrow-down-tray" class="-mr-1" />
                </flux:tooltip>
            </x-slot>
        </flux:input>
    </div>
</flux:card>

<flux:card>
    <flux:heading size="lg" class="mb-4">{{ __('Time & Servings') }}</flux:heading>

    <div class="grid gap-6 md:grid-cols-4">
        <flux:input
            wire:model="form.servings"
            :label="__('Servings')"
            type="text"
            placeholder="e.g., 4 people"
        />

        <flux:input
            wire:model="form.prep_time"
            :label="__('Prep Time')"
            type="text"
            placeholder="e.g., 30 min"
        />

        <flux:input
            wire:model="form.cook_time"
            :label="__('Cook Time')"
            type="text"
            placeholder="e.g., 1 hour"
        />

        <flux:input
            wire:model="form.total_time"
            :label="__('Total Time')"
            type="text"
            placeholder="e.g., 1 hour 30 min"
        />
    </div>
</flux:card>

<flux:card class="space-y-2">
    <flux:textarea
        :label="__('Description')"
        wire:model="form.description"
        :placeholder="__('Brief description of the recipe...')"
        rows="3"
    />

    <flux:editor
        wire:model="form.ingredients"
        :label="__('Ingredients')"
    />

    <flux:editor
        wire:model="form.instructions"
        :label="__('Instructions')"
    />

    <flux:textarea
        wire:model="form.notes"
        :label="__('Notes')"
        :placeholder="__('Additional notes, tips, or variations...')"
        rows="4"
    />

    <flux:textarea
        wire:model="form.nutrition"
        :label="__('Nutrition')"
        :placeholder="__('Nutritional information...')"
        rows="4"
    />
</flux:card>
