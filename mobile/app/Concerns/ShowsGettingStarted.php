<?php

namespace App\Concerns;

use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyConnector;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use App\Models\Onboarding;
use App\Models\Recipe;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Dialog;

trait ShowsGettingStarted
{
    /**
     * @return array{done: int, total: int, jobs: list<array{key: string, title: string, detail: string|null, done: bool, next: bool}>}|null
     */
    #[Computed]
    public function gettingStarted(): ?array
    {
        $onboarding = Onboarding::current();
        $team = app(SunnyTeam::class)->current();

        if ($onboarding === null || $team === null || $onboarding->hidden_at !== null || Onboarding::needsSetUp()) {
            return null;
        }

        $place = $this->starterPlace($onboarding);
        $starterRecipes = collect($onboarding->starterRecords('recipes'))->pluck('id');

        $jobs = [
            ['key' => 'set-up', 'title' => 'Set up your home', 'detail' => null, 'done' => $onboarding->set_up_at !== null],
            $place !== null
                ? ['key' => 'place', 'title' => 'Add to '.$place->name, 'detail' => 'Some Storage or a Thing', 'done' => Item::query()->forCurrentServer()->where('parent_id', $place->id)->exists()]
                : ['key' => 'place', 'title' => 'Put something away', 'detail' => 'A place, some Storage or a Thing', 'done' => Item::query()->forActiveTeam()->exists()],
            ['key' => 'recipe', 'title' => 'Save a recipe', 'detail' => null, 'done' => Recipe::query()->forActiveTeam()->whereKeyNot($starterRecipes)->exists()],
            ['key' => 'invite', 'title' => 'Invite your family', 'detail' => null, 'done' => $onboarding->invited_at !== null],
        ];

        $done = count(array_filter($jobs, fn (array $job): bool => $job['done']));

        if ($done === count($jobs)) {
            return null;
        }

        $next = collect($jobs)->search(fn (array $job): bool => ! $job['done']);

        return [
            'done' => $done,
            'total' => count($jobs),
            'jobs' => array_map(fn (array $job, int $index): array => [...$job, 'next' => $index === $next], $jobs, array_keys($jobs)),
        ];
    }

    public function startJob(string $key): void
    {
        $onboarding = Onboarding::current();

        if ($onboarding === null) {
            return;
        }

        match ($key) {
            'set-up' => $this->navigate('/setup'),
            'place' => ($place = $this->starterPlace($onboarding)) !== null
                ? $this->navigate('/inventory/'.$place->id)
                : $this->navigate('/inventory/create'),
            'recipe' => $this->navigate('/recipes/create'),
            'invite' => $this->inviteFamily($onboarding),
            default => null,
        };
    }

    public function hideGettingStarted(): void
    {
        Onboarding::current()?->update(['hidden_at' => now()]);
        $this->refreshGettingStarted();
    }

    protected function refreshGettingStarted(): void
    {
        unset($this->gettingStarted);
    }

    private function inviteFamily(Onboarding $onboarding): void
    {
        $team = app(SunnyTeam::class)->current();
        $url = app(SunnyConnector::class)->siteUrl().'/settings/teams/'.rawurlencode((string) $team?->slug);

        if ($team?->slug === null || ! Browser::inApp($url)) {
            Dialog::toast('Unable to open the Sunny website. Please try again.');

            return;
        }

        $onboarding->update(['invited_at' => now()]);
        $this->refreshGettingStarted();
    }

    private function starterPlace(Onboarding $onboarding): ?Item
    {
        return collect($onboarding->starterRecords('items'))
            ->first(fn (Item $item): bool => $item->type === ItemType::Location);
    }
}
