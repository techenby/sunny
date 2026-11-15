<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Models\CalendarFeed;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
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

        return collect($this->dayLabels())
            ->map(fn (string $label, int $offset): array => [
                'label' => $label,
                'events' => $events
                    ->filter(fn (array $event): bool => $event['starts_at']->isSameDay($today->addDays($offset)))
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /** @return array<int, string> */
    public function dayLabels(): array
    {
        $today = $this->team()->today();

        return collect(range(0, self::DAYS - 1))
            ->map(fn (int $offset): string => match ($offset) {
                0 => __('Today'),
                1 => __('Tomorrow'),
                default => $today->addDays($offset)->format('l'),
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
        return Blade::render(<<<'BLADE'
            <x-dashboard.card :heading="__('Coming up')" :action="__('Calendars')" :href="route('kiosk.configure.calendar')">
                <div class="@container">
                    <div class="grid gap-5 @2xl:grid-cols-3">
                        @foreach ($labels as $label)
                            <x-dashboard.day :$label />
                        @endforeach
                    </div>
                </div>
            </x-dashboard.card>
            BLADE, ['labels' => $this->dayLabels()]);
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }
};
