@teleport('body')
<flux:modal name="share-recipe" class="md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Share Recipe') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Anyone with the link can view this recipe.') }}</flux:text>
        </div>

        <div class="flex items-center justify-between">
            <flux:text>{{ __('Public Link') }}</flux:text>
            <flux:switch wire:click="toggleSharing" :checked="$recipe->isShared()" />
        </div>

        @if ($recipe->isShared())
            <flux:input
                readonly
                :value="route('recipes.shared', $recipe->share_token)"
                copyable
            />
        @endif
    </div>
</flux:modal>
@endteleport
