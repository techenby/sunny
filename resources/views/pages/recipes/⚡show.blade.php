<?php

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
        <div class="flex gap-2">
            <flux:dropdown align="end">
                <flux:button icon="share" :variant="$recipe->isShared() ? 'primary' : 'filled'">{{ __('Share') }}</flux:button>
                <flux:popover class="w-80">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:heading size="sm">{{ __('Public Link') }}</flux:heading>
                                <flux:text size="sm">{{ __('Anyone with the link can view this recipe.') }}</flux:text>
                            </div>
                            <flux:switch wire:click="toggleSharing" :checked="$recipe->isShared()" />
                        </div>

                        @if ($recipe->isShared())
                            <div x-data="{ copied: false }">
                                <flux:input
                                    readonly
                                    :value="route('recipes.shared', $recipe->share_token)"
                                    copyable
                                />
                            </div>
                        @endif
                    </div>
                </flux:popover>
            </flux:dropdown>
            <flux:button wire:click="remix" icon="document-duplicate">{{ __('Remix') }}</flux:button>
            <flux:button :href="route('recipes.edit', $recipe)" icon="pencil" wire:navigate>{{ __('Edit') }}</flux:button>
        </div>
    </div>

    @include('pages.recipes.detail')
</div>
