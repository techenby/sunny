<?php

use App\Actions\CreateRecipe;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::guest')] class extends Component {
    public Recipe $recipe;

    public function mount(string $shareToken): void
    {
        $this->recipe = Recipe::where('share_token', $shareToken)->firstOrFail();
    }

    #[Computed]
    public function existingRecipe(): ?Recipe
    {
        if (! Auth::check()) {
            return null;
        }

        $team = Auth::user()->currentTeam;

        if ($this->recipe->team_id === $team->id) {
            return $this->recipe;
        }

        return Recipe::where('team_id', $team->id)
            ->where('source', route('recipes.shared', $this->recipe->share_token))
            ->first();
    }

    public function addToMyTeam(): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'));

            return;
        }

        $team = Auth::user()->currentTeam;
        $shareUrl = route('recipes.shared', $this->recipe->share_token);

        if ($this->recipe->team_id === $team->id) {
            $this->redirect(route('recipes.show', $this->recipe), navigate: true);

            return;
        }

        $existing = Recipe::where('team_id', $team->id)->where('source', $shareUrl)->first();

        if ($existing) {
            $this->redirect(route('recipes.show', $existing), navigate: true);

            return;
        }

        $data = [
            ...$this->recipe->toRecipeData(),
            'source' => $shareUrl,
        ];

        $recipe = (new CreateRecipe)->handle($team, $data);

        $this->redirect(route('recipes.show', $recipe), navigate: true);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ $recipe->name }}</flux:heading>

        @auth
            @if ($this->existingRecipe)
                <flux:button :href="route('recipes.show', $this->existingRecipe)" icon="arrow-right" wire:navigate>{{ __('Already in My Recipes') }}</flux:button>
            @else
                <flux:button wire:click="addToMyTeam" icon="plus">{{ __('Add to My Team') }}</flux:button>
            @endif
        @endauth
    </div>

    @include('pages.recipes.detail')
</div>
