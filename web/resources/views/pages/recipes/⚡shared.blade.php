<?php

use App\Actions\Recipes\SaveSharedRecipeToTeam;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Laravel\Head\Facades\Head;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::guest')] class extends Component
{
    public Recipe $recipe;

    public function mount(string $shareToken): void
    {
        $this->recipe = Recipe::where('share_token', $shareToken)->firstOrFail();
    }

    public function addToMyTeam(): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'));

            return;
        }

        $recipe = (new SaveSharedRecipeToTeam)->handle($this->recipe, Auth::user()->currentTeam);

        $this->redirect(route('recipes.show', $recipe), navigate: true);
    }

    public function render()
    {
        Head::title($this->recipe->name)
            ->description(__('A recipe shared with you on Sunny. Add it to your household\'s recipes in one click.'));

        return $this->view();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $recipe->name }}</flux:heading>

            @include('pages.recipes.meta')
        </div>

        @auth
            <flux:button wire:click="addToMyTeam" icon="plus" :disabled="Auth::user()->current_team_id === $recipe->team_id">{{ __('Add to My Team') }}</flux:button>
        @endauth
    </div>

    @include('pages.recipes.detail', ['showRemixes' => false])
</div>
