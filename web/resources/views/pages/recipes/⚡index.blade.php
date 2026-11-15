<?php

use App\Actions\Recipes\CopyRecipeToTeam;
use App\Actions\Recipes\DeleteRecipe;
use App\Actions\Recipes\RemixRecipe;
use App\Livewire\Traits\WithSearching;
use App\Livewire\Traits\WithSorting;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    use WithSearching;
    use WithSorting;

    public ?int $copyRecipeId = null;

    public ?int $copyToTeamId = null;

    #[Computed]
    public function recipes(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam->recipes()
            ->when($this->search, fn ($query) => $query->whereLike('name', '%' . $this->search . '%'))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    /** @return Collection<int, Team> */
    #[Computed]
    public function otherTeams(): Collection
    {
        return Auth::user()->teams->where('id', '!=', Auth::user()->current_team_id)->values();
    }

    public function delete(int $id): void
    {
        $recipe = Auth::user()->currentTeam->recipes()->findOrFail($id);
        $this->authorize('delete', $recipe);

        (new DeleteRecipe)->handle($recipe);

        unset($this->recipes);
    }

    public function remix(int $id): void
    {
        $recipe = Auth::user()->currentTeam->recipes()->findOrFail($id);
        $this->authorize('remix', $recipe);

        (new RemixRecipe)->handle($recipe);

        unset($this->recipes);
    }

    public function openCopyModal(int $recipeId): void
    {
        $this->copyRecipeId = $recipeId;
        $this->copyToTeamId = null;
        $this->modal('copy-recipe')->show();
    }

    public function copyToTeam(): void
    {
        $this->validate([
            'copyRecipeId' => ['required', 'integer'],
            'copyToTeamId' => ['required', 'integer', 'exists:team_members,team_id,user_id,' . Auth::id()],
        ]);

        $recipe = $this->recipes->firstWhere('id', $this->copyRecipeId);
        $this->authorize('copy', $recipe);

        $team = $this->otherTeams->firstWhere('id', $this->copyToTeamId);

        (new CopyRecipeToTeam)->handle($recipe, $team);

        $this->modal('copy-recipe')->close();
        $this->reset('copyRecipeId', 'copyToTeamId');

        Flux::toast(__('Recipe copied successfully.'));
    }
}; ?>

@php
    $showSource = $this->recipes->contains(fn ($recipe) => filled($recipe->source));
    $showTime = $this->recipes->contains(fn ($recipe) => filled($recipe->total_time));
@endphp

<section class="w-full">
    <div class="mb-4 flex items-center justify-between">
        <flux:heading size="xl">{{ __('Recipes') }}</flux:heading>
        <flux:button variant="primary" icon="plus" :href="route('recipes.create')" wire:navigate>{{ __('Add recipe') }}</flux:button>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search recipes...')" icon="magnifying-glass" class="max-w-xs" />
    </div>

    <flux:table :paginate="$this->recipes" class="w-full">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            @if ($showSource)
                <flux:table.column class="w-70 max-md:hidden">{{ __('Source') }}</flux:table.column>
            @endif
            @if ($showTime)
                <flux:table.column class="w-35 max-sm:hidden">{{ __('Time') }}</flux:table.column>
            @endif
            <flux:table.column class="w-12" align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->recipes as $recipe)
                <x-ui.table.row :key="$recipe->id" :href="route('recipes.show', $recipe)">
                    <flux:table.cell>
                        <x-ui.table.name :href="route('recipes.show', $recipe)">
                            <x-slot:thumb>
                                <x-ui.table.thumb :src="$recipe->thumb_url" icon="book-open" />
                            </x-slot:thumb>

                            {{ $recipe->name }}
                        </x-ui.table.name>
                    </flux:table.cell>
                    @if ($showSource)
                        <flux:table.cell class="max-md:hidden">
                            @if (! $recipe->source)
                                <x-ui.table.blank />
                            @elseif ($recipe->isSourceUrl())
                                <a href="{{ $recipe->source }}" target="_blank" rel="noopener" class="flex items-center gap-1 hover:text-zinc-800 dark:hover:text-white">
                                    <span class="truncate">{{ $recipe->shortenedSource() }}</span>
                                    <flux:icon.arrow-up-right variant="micro" class="size-3 shrink-0 text-zinc-400" />
                                </a>
                            @else
                                <div class="truncate">{{ $recipe->source }}</div>
                            @endif
                        </flux:table.cell>
                    @endif
                    @if ($showTime)
                        <flux:table.cell class="max-sm:hidden">
                            @if ($recipe->total_time)
                                <div class="truncate">{{ $recipe->total_time }}</div>
                            @else
                                <x-ui.table.blank />
                            @endif
                        </flux:table.cell>
                    @endif
                    <flux:table.cell align="end">
                        <x-ui.actions :label="__('Actions for :name', ['name' => $recipe->name])">
                            <flux:menu.item :href="route('recipes.show', $recipe)" icon="eye" wire:navigate>{{ __('View') }}</flux:menu.item>
                            <flux:menu.item wire:click="remix({{ $recipe->id }})" icon="document-duplicate">{{ __('Remix') }}</flux:menu.item>
                            @if ($this->otherTeams->isNotEmpty())
                                <flux:menu.item wire:click="openCopyModal({{ $recipe->id }})" icon="arrow-up-tray">{{ __('Copy to Team') }}</flux:menu.item>
                            @endif
                            <flux:menu.item :href="route('recipes.edit', $recipe)" icon="pencil" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                            <flux:menu.item wire:click="delete({{ $recipe->id }})" variant="danger" icon="trash" wire:confirm="{{ __('“:name” will be deleted for everyone on your team.', ['name' => $recipe->name]) }}">{{ __('Delete') }}</flux:menu.item>
                        </x-ui.actions>
                    </flux:table.cell>
                </x-ui.table.row>
            @empty
                <flux:table.row key="empty-recipe">
                    <flux:table.cell colspan="2" class="text-center">
                        <flux:text variant="subtle" size="xl">{{ __('No recipes found') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @include('pages.recipes.modals.copy-to-team')
</section>
