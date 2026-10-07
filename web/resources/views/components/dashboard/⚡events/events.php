<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Models\CalendarFeed;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const DAYS = 3;

    /** @return EloquentCollection<int, CalendarFeed> */
    #[Computed]
    public function feeds(): EloquentCollection
    {
        return $this->team()->calendarFeeds()->get();
    }

    /** @return array<int, array{label: string, events: array<int, array<string, mixed>>}> */
    #[Computed]
    public function days(): array
    {
        $today = $this->team()->today();

        $events = $this->feeds
            ->flatMap(fn (CalendarFeed $feed) => resolve(FetchCalendarEvents::class)->handle($feed, self::DAYS, $today))
            ->sortBy('starts_at')
            ->values();

        return collect(range(0, self::DAYS - 1))
            ->map(function (int $offset) use ($events, $today): array {
                $date = $today->addDays($offset);

                return [
                    'label' => match ($offset) {
                        0 => __('Today'),
                        1 => __('Tomorrow'),
                        default => $date->format('l'),
                    },
                    'events' => $events
                        ->filter(fn (array $event): bool => $event['starts_at']->isSameDay($date))
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /** @param array{starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, all_day: bool} $event */
    public function eventTime(array $event): string
    {
        if ($event['all_day']) {
            return __('All day');
        }

        if (! $event['ends_at'] instanceof CarbonImmutable) {
            return $event['starts_at']->format('g:i A');
        }

        return $event['starts_at']->format('g:i') . ' - ' . $event['ends_at']->format('g:i A');
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <flux:card body="flush" class="flex flex-col">
                <flux:card.header>
                    <flux:card.heading size="lg">{{ __('Coming up') }}</flux:card.heading>
                </flux:card.header>

                <flux:card.body class="flex-1">
                    <flux:skeleton.group animate="shimmer" class="space-y-3">
                        <flux:skeleton.line class="w-1/3" />
                        <flux:skeleton.line />
                        <flux:skeleton.line />
                        <flux:skeleton.line class="w-2/3" />
                    </flux:skeleton.group>
                </flux:card.body>
            </flux:card>
            HTML;
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }
};
