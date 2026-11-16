<?php

use App\Actions\Onboarding\SetUpHome;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::onboarding')] class extends Component
{
    /** @var list<string> */
    public array $places = ['Kitchen pantry', 'Garage', 'Hall closet'];

    /** @var list<string> */
    public array $customPlaces = [];

    public string $newPlace = '';

    /** @var list<string> */
    public array $starters = ['groceries', 'bedtime'];

    public function mount(): void
    {
        if (! Auth::user()->needsOnboarding()) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    /** @return list<string> */
    #[Computed]
    public function placeOptions(): array
    {
        return [...SetUpHome::PLACES, ...$this->customPlaces];
    }

    #[Computed]
    public function summary(): ?string
    {
        $parts = collect([
            trans_choice(':count place|:count places', count($this->places)),
            trans_choice(':count list|:count lists', count(array_intersect($this->starters, ['groceries']))),
            trans_choice(':count routine|:count routines', count(array_intersect($this->starters, ['bedtime', 'morning']))),
            trans_choice(':count recipe|:count recipes', count(array_intersect($this->starters, ['recipe']))),
        ])->reject(fn (string $part): bool => Str::startsWith($part, '0 '));

        return $parts->isEmpty() ? null : $parts->join(', ', __(' and '));
    }

    public function addPlace(): void
    {
        $this->validate(['newPlace' => ['required', 'string', 'max:255']], attributes: ['newPlace' => __('place')]);

        $place = trim($this->newPlace);

        if (collect($this->placeOptions)->doesntContain(fn (string $option): bool => Str::lower($option) === Str::lower($place))) {
            $this->customPlaces[] = $place;
            unset($this->placeOptions);
        }

        if (! in_array($place, $this->places, true)) {
            $this->places[] = $place;
        }

        $this->reset('newPlace');
        $this->modal('add-place')->close();
    }

    public function setUp(SetUpHome $setUpHome): void
    {
        $this->validate([
            'places' => ['array'],
            'places.*' => ['string', 'max:255'],
            'starters' => ['array'],
            'starters.*' => [Rule::in(SetUpHome::STARTERS)],
        ]);

        $setUpHome->handle(Auth::user()->currentTeam, array_values($this->places), array_values($this->starters));

        $this->finish();
    }

    public function startEmpty(): void
    {
        $this->finish();
    }

    private function finish(): void
    {
        Auth::user()->markAsOnboarded();

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-10">
    <div class="flex flex-col gap-2">
        <flux:text class="font-medium text-(--color-accent-content)">{{ __('Welcome to SunnyHome') }}</flux:text>
        <flux:heading size="xl" level="1" class="text-3xl!">
            {{ __('What’s in your home, :name?', ['name' => Str::before(auth()->user()->name, ' ')]) }}
        </flux:heading>
        <flux:text size="lg">
            {{ __('Tick what fits and we’ll set up :team for you, so you don’t start from a blank page. You can rename or remove anything later.', ['team' => auth()->user()->currentTeam->name]) }}
        </flux:text>
    </div>

    <section class="flex flex-col gap-3" data-test="places">
        <div class="flex items-baseline justify-between gap-4">
            <flux:heading size="lg" level="2">{{ __('Places you keep things') }}</flux:heading>
            <flux:text size="sm">{{ trans_choice(':count picked|:count picked', count($places)) }}</flux:text>
        </div>
        <flux:text>{{ __('A place is a room or area. Inside it you’ll add storage, like a shelf or a box, and the things in it.') }}</flux:text>

        <flux:checkbox.group wire:model.live="places" variant="pills" class="mt-1">
            @foreach ($this->placeOptions as $place)
                <flux:checkbox :value="$place" :label="$place" wire:key="place-{{ Str::slug($place) }}" class="px-3! py-2!" />
            @endforeach

            <flux:modal.trigger name="add-place">
                <flux:button size="sm" variant="ghost" icon="pencil" class="rounded-full! border border-dashed border-zinc-300 dark:border-white/20">
                    {{ __('Add your own') }}
                </flux:button>
            </flux:modal.trigger>
        </flux:checkbox.group>
    </section>

    <section class="flex flex-col gap-3" data-test="starters">
        <div class="flex items-baseline justify-between gap-4">
            <flux:heading size="lg" level="2">{{ __('A few things to start you off') }}</flux:heading>
            <flux:text size="sm">{{ trans_choice(':count picked|:count picked', count($starters)) }}</flux:text>
        </div>
        <flux:text>{{ __('Ready-made examples you can use as they are or change to suit your home.') }}</flux:text>

        <flux:checkbox.group wire:model.live="starters" variant="cards" class="mt-1 grid! gap-3 sm:grid-cols-2">
            @foreach ([
                'groceries' => ['icon' => 'shopping-cart', 'color' => 'purple', 'title' => __('Groceries list'), 'description' => __('Shared, with a few staples'), 'peek' => array_slice(SetUpHome::GROCERIES, 0, 3), 'more' => count(SetUpHome::GROCERIES) - 3],
                'bedtime' => ['icon' => 'arrow-path-rounded-square', 'color' => 'green', 'title' => __('Bedtime routine'), 'description' => __('Every evening'), 'peek' => array_slice(SetUpHome::BEDTIME, 0, 3), 'more' => count(SetUpHome::BEDTIME) - 3],
                'morning' => ['icon' => 'arrow-path-rounded-square', 'color' => 'green', 'title' => __('Morning routine'), 'description' => __('Weekday mornings'), 'peek' => array_slice(SetUpHome::MORNING, 0, 2), 'more' => count(SetUpHome::MORNING) - 2],
                'recipe' => ['icon' => 'book-open', 'color' => 'amber', 'title' => __('A sample recipe'), 'description' => __('Apple cider donuts'), 'peek' => [__('18 min'), __('Makes 12')], 'more' => 0],
            ] as $value => $starter)
                <flux:checkbox :value="$value" wire:key="starter-{{ $value }}" class="items-start">
                    <div class="flex flex-1 gap-3">
                        <flux:avatar size="sm" :icon="$starter['icon']" :color="$starter['color']" icon:variant="outline" class="shrink-0" />

                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <flux:heading>{{ $starter['title'] }}</flux:heading>
                            <flux:text size="sm">{{ $starter['description'] }}</flux:text>

                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                @foreach ($starter['peek'] as $peek)
                                    <flux:badge size="sm">{{ __($peek) }}</flux:badge>
                                @endforeach

                                @if ($starter['more'] > 0)
                                    <flux:text size="sm" variant="subtle">{{ __(':count more', ['count' => $starter['more']]) }}</flux:text>
                                @endif
                            </div>
                        </div>
                    </div>

                    <flux:checkbox.indicator />
                </flux:checkbox>
            @endforeach
        </flux:checkbox.group>
    </section>

    <section class="flex flex-col gap-4 rounded-xl bg-zinc-50 p-4 sm:flex-row sm:items-center sm:px-5 dark:bg-white/5" data-test="setup-footer">
        <flux:text class="flex-1">
            @if ($this->summary)
                {{ __('We’ll create :summary.', ['summary' => $this->summary]) }}
            @else
                {{ __('Nothing picked yet. You can add everything yourself later.') }}
            @endif
        </flux:text>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
            <flux:button variant="ghost" wire:click="startEmpty" data-test="start-empty">{{ __('Start empty instead') }}</flux:button>
            <flux:button variant="primary" icon:trailing="arrow-right" wire:click="setUp" data-test="set-up">{{ __('Set up my home') }}</flux:button>
        </div>
    </section>

    <flux:modal name="add-place" class="w-full max-w-sm">
        <form wire:submit="addPlace" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Add a place') }}</flux:heading>
                <flux:text class="mt-1">{{ __('A room or area, like Under the stairs.') }}</flux:text>
            </div>

            <flux:input wire:model="newPlace" :label="__('Name')" autofocus />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
