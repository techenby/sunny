<?php

use App\Actions\Recipes\CopyRecipeToTeam;
use App\Actions\Recipes\DeleteRecipe;
use App\Actions\Recipes\RemixRecipe;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Recipe $recipe;

    public ?int $copyToTeamId = null;

    /** @return Collection<int, Team> */
    #[Computed]
    public function otherTeams(): Collection
    {
        return Auth::user()->teams->where('id', '!=', Auth::user()->current_team_id)->values();
    }

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

    public function copyToTeam(): void
    {
        $this->validate([
            'copyToTeamId' => ['required', 'integer', 'exists:team_members,team_id,user_id,' . Auth::id()],
        ]);

        $this->authorize('copy', $this->recipe);

        $team = $this->otherTeams->firstWhere('id', $this->copyToTeamId);

        (new CopyRecipeToTeam)->handle($this->recipe, $team);

        $this->modal('copy-recipe')->close();
        $this->reset('copyToTeamId');

        Flux::toast(__('Recipe copied successfully.'));
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
                @if ($this->otherTeams->isNotEmpty())
                    <flux:modal.trigger name="copy-recipe">
                        <flux:menu.item icon="arrow-up-tray">{{ __('Copy to Team') }}</flux:menu.item>
                    </flux:modal.trigger>
                @endif
                <flux:menu.item icon="pencil" :href="route('recipes.edit', $recipe)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                <flux:menu.item wire:click="delete" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this recipe?') }}">{{ __('Delete') }}</flux:menu.item>
            </flux:menu>
        </flux:dropdown>

        @include('pages::recipes.modals.share-recipe')
        @include('pages::recipes.modals.copy-to-team')
    </div>

    @include('pages.recipes.detail')
</div>
