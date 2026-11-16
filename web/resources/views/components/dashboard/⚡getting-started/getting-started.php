<?php

use App\Actions\Onboarding\SetUpHome;
use App\Enums\ItemType;
use App\Models\Team;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** @return list<array{key: string, done: bool, icon: string, color: string, heading: string, text: string, action: string, href: string}> */
    #[Computed]
    public function jobs(): array
    {
        $team = $this->team();
        $place = $team->items()
            ->where('type', ItemType::Location)
            ->whereDoesntHave('children')
            ->oldest()
            ->oldest('id')
            ->first();

        $inventoryStarted = $team->items()->where(fn (Builder $query) => $query->whereNotNull('parent_id')->orWhere('type', '!=', ItemType::Location))->exists();

        $setUp = $team->items()->exists() || $team->checklists()->exists() || $team->routines()->exists() || $team->recipes()->exists();

        return [
            [
                'key' => 'account',
                'done' => true,
                'icon' => 'home',
                'color' => 'zinc',
                'heading' => $setUp ? __('Set up your home') : __('Create your account'),
                'text' => $setUp ? __('Your starter home is in. Rename or remove anything.') : __('You’re in. Start wherever you like.'),
                'action' => '',
                'href' => '',
            ],
            [
                'key' => 'inventory',
                'done' => $inventoryStarted,
                'icon' => 'map-pin',
                'color' => 'red',
                'heading' => match (true) {
                    $inventoryStarted => __('Put something away'),
                    $place !== null => __('Put something in the :place', ['place' => $place->name]),
                    default => __('Add your first place'),
                },
                'text' => $place || $inventoryStarted ? __('Add a shelf, a box or a thing, so you can find it later.') : __('Start with a room, like Garage. Storage and things go inside it.'),
                'action' => $place ? __('Add to :place', ['place' => $place->name]) : __('Add a place'),
                'href' => $place ? route('inventory.index', ['parentId' => $place->id]) : route('inventory.index'),
            ],
            [
                'key' => 'recipe',
                'done' => $team->recipes()->where(fn (Builder $query) => $query->whereNull('source')->orWhere('source', '!=', SetUpHome::SAMPLE_RECIPE_URL))->exists(),
                'icon' => 'book-open',
                'color' => 'amber',
                'heading' => __('Save a recipe'),
                'text' => __('One your family makes all the time. Paste a link or type it in.'),
                'action' => __('Add a recipe'),
                'href' => route('recipes.create'),
            ],
            [
                'key' => 'invite',
                'done' => $team->members()->count() > 1,
                'icon' => 'user-plus',
                'color' => 'blue',
                'heading' => __('Invite your family'),
                'text' => __('Share everything with the people you live with, from the Teams page.'),
                'action' => __('Go to Teams'),
                'href' => route('teams.edit', $team->slug),
            ],
        ];
    }

    #[Computed]
    public function doneCount(): int
    {
        return collect($this->jobs)->where('done', true)->count();
    }

    #[Computed]
    public function nextJob(): ?string
    {
        return collect($this->jobs)->firstWhere('done', false)['key'] ?? null;
    }

    #[Computed]
    public function visible(): bool
    {
        $user = Auth::user();

        return $user->onboarded_at !== null
            && $user->onboarding_dismissed_at === null
            && $this->doneCount < count($this->jobs);
    }

    public function dismiss(): void
    {
        Auth::user()->dismissOnboarding();

        unset($this->visible);
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }
};
