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

new class extends Component {
    use WithPagination;
    use WithSearching;
    use WithSorting;

    public ?int $copyRecipeId = null;

    public ?int $copyToTeamId = null;

    #[Computed]
    public function recipes(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam->recipes()
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
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
            'copyToTeamId' => ['required', 'integer', 'exists:team_user,team_id,user_id,' . Auth::id()],
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

<section class="w-full">
    <div class="mb-4 flex items-center justify-between">
        <flux:heading size="xl">{{ __('Cookbook') }}</flux:heading>
        <flux:button variant="primary" :href="route('recipes.create')" wire:navigate>{{ __('Add Recipe') }}</flux:button>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search recipes...')" icon="magnifying-glass" class="max-w-sm" />
    </div>

    <flux:table :paginate="$this->recipes">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Source') }}</flux:table.column>
            <flux:table.column>{{ __('Time') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->recipes as $recipe)
                <flux:table.row :key="$recipe->id">
                    <flux:table.cell variant="strong">
                        <flux:link href="{{ route('recipes.show', $recipe) }}" wire:navigate>
                            {{ $recipe->name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($recipe->source)
                            @if ($recipe->isSourceUrl())
                                <flux:link href="{{ $recipe->source }}" target="_blank">
                                    {{ $recipe->shortenedSource() }}
                                </flux:link>
                            @else
                                {{ $recipe->source }}
                            @endif
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($recipe->total_time)
                            {{ $recipe->total_time }}
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />

                            <flux:menu>
                                <flux:menu.item :href="route('recipes.show', $recipe)" icon="eye" wire:navigate>{{ __('View') }}</flux:menu.item>
                                <flux:menu.item wire:click="remix({{ $recipe->id }})" icon="document-duplicate">{{ __('Remix') }}</flux:menu.item>
                                @if ($this->otherTeams->isNotEmpty())
                                    <flux:menu.item wire:click="openCopyModal({{ $recipe->id }})" icon="arrow-up-tray">{{ __('Copy to Team') }}</flux:menu.item>
                                @endif
                                <flux:menu.item :href="route('recipes.edit', $recipe)" icon="pencil" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item wire:click="delete({{ $recipe->id }})" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this recipe?') }}">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row key="empty-recipe">
                    <flux:table.cell colspan="4" class="text-center">
                        <flux:text variant="subtle" size="xl">{{ __('No recipes found') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @teleport('body')
    <flux:modal name="copy-recipe" class="md:w-96">
        <form wire:submit="copyToTeam" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Copy to Team') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Copy this recipe to another team.') }}</flux:text>
            </div>
            <flux:select wire:model="copyToTeamId" :label="__('Team')" :placeholder="__('Select a team...')">
                @foreach ($this->otherTeams as $team)
                    <flux:select.option :value="$team->id">{{ $team->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Copy') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endteleport
</section>
