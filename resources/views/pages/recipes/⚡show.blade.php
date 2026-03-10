<?php

use App\Actions\DeleteRecipe;
use App\Actions\RemixRecipe;
use App\Models\Recipe;
use Livewire\Component;

new class extends Component {
    public Recipe $recipe;

    public function remix(): void
    {
        $this->authorize('remix', $this->recipe);

        $recipe = (new RemixRecipe)->handle($this->recipe);

        $this->redirect(route('recipes.show', $recipe), navigate: true);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->recipe);

        (new DeleteRecipe)->handle($this->recipe);

        $this->redirect(route('recipes.index'), navigate: true);
    }

    public function toggleSharing(): void
    {
        $this->authorize('share', $this->recipe);

        if ($this->recipe->isShared()) {
            $this->recipe->disableSharing();
        } else {
            $this->recipe->enableSharing();
        }

        $this->recipe->refresh();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <flux:button :href="route('recipes.index')" icon="arrow-left" variant="ghost" wire:navigate />
            <flux:heading size="xl">{{ $recipe->name }}</flux:heading>
        </div>
        <flux:dropdown align="end">
            <flux:button icon="ellipsis-vertical" variant="ghost" />
            <flux:menu>
                <flux:modal.trigger name="share-recipe">
                    <flux:menu.item icon="share">{{ __('Share') }}</flux:menu.item>
                </flux:modal.trigger>
                <flux:menu.item icon="document-duplicate" wire:click="remix">{{ __('Remix') }}</flux:menu.item>
                <flux:menu.item icon="pencil" :href="route('recipes.edit', $recipe)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                <flux:menu.item wire:click="delete" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this recipe?') }}">{{ __('Delete') }}</flux:menu.item>
            </flux:menu>
        </flux:dropdown>

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
    </div>

    @include('pages.recipes.detail')
</div>
