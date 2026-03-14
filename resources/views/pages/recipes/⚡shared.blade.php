<?php

use App\Actions\CreateRecipe;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::guest')] class extends Component {
    public Recipe $recipe;

    public function mount(string $shareToken): void
    {
        $this->recipe = Recipe::where('share_token', $shareToken)->firstOrFail();
    }

    public function addToMyTeam(): void
    {
        $source = route('recipes.shared', $this->recipe->share_token);

        if (! Auth::check()) {
            $this->redirect(route('login'));

            return;
        // check if user is owner of recipe
        } else if ($this->recipe->team_id === Auth::user()->current_team_id) {
            $this->redirect(route('recipes.show', $this->recipe), navigate: true);

            return;
        // check if user already saved it
        } else if ($toRecipe = Auth::user()->currentTeam->recipes()->where('source', $source)->first()) {
            $this->redirect(route('recipes.show', $toRecipe), navigate: true);

            return;
        }

        $recipe = (new CreateRecipe)->handle(Auth::user()->currentTeam, [
            ...$this->recipe->only([
                'name', 'source', 'servings', 'prep_time', 'cook_time', 'total_time',
                'description', 'ingredients', 'instructions', 'notes', 'nutrition',
            ]),
            'source' => $source,
        ]);

        $this->redirect(route('recipes.show', $recipe), navigate: true);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ $recipe->name }}</flux:heading>

        @auth
            <flux:button wire:click="addToMyTeam" icon="plus" :disabled="Auth::user()->current_team_id === $recipe->team_id">{{ __('Add to My Team') }}</flux:button>
        @endauth
    </div>

    @include('pages.recipes.detail')
</div>
