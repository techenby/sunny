<?php

use App\Actions\Recipes\CopyRecipeToTeam;
use App\Actions\Recipes\DeleteRecipe;
use App\Actions\Recipes\RemixRecipe;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Head\Facades\Head;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
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
            'copyToTeamId' => [
                'required',
                'integer',
                Rule::notIn([$this->recipe->team_id, Auth::user()->current_team_id]),
                Rule::exists('team_members', 'team_id')->where('user_id', Auth::id()),
            ],
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

    public function render()
    {
        Head::title($this->recipe->name);

        return $this->view();
    }
}; ?>

<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('recipes.index')" wire:navigate class="cursor-pointer">{{ __('Recipes') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $recipe->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl">{{ $recipe->name }}</flux:heading>

            @include('pages.recipes.meta', ['editable' => true])
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @if ($recipe->ingredients || $recipe->instructions)
                <flux:button :href="route('recipes.cook', $recipe)" icon="play" wire:navigate>{{ __('Cook') }}</flux:button>
            @endif
            <flux:button icon="arrow-path-rounded-square" wire:click="remix">{{ __('Remix') }}</flux:button>
            <flux:button icon="pencil-square" :href="route('recipes.edit', $recipe)" wire:navigate>{{ __('Edit') }}</flux:button>
            <x-ui.actions size="base">
                <flux:modal.trigger name="share-recipe">
                    <flux:menu.item icon="share">{{ __('Share') }}</flux:menu.item>
                </flux:modal.trigger>
                @if ($this->otherTeams->isNotEmpty())
                    <flux:modal.trigger name="copy-recipe">
                        <flux:menu.item icon="arrow-up-tray">{{ __('Copy to Team') }}</flux:menu.item>
                    </flux:modal.trigger>
                @endif
                <flux:menu.separator />
                <flux:menu.item wire:click="delete" variant="danger" icon="trash" wire:confirm="{{ __('“:name” will be deleted for everyone on your team.', ['name' => $recipe->name]) }}">{{ __('Delete') }}</flux:menu.item>
            </x-ui.actions>
        </div>

        @include('pages::recipes.modals.share-recipe')
        @include('pages::recipes.modals.copy-to-team')
    </div>

    @include('pages.recipes.detail', ['editable' => true])
</div>
