<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('Recipes') }}</flux:heading>
        <flux:spacer />
        <flux:modal.trigger name="recipe-form">
            <flux:button>Create</flux:button>
        </flux:modal.trigger>
    </header>
</flux:main>
