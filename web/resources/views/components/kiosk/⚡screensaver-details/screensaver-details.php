<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Actions\Kiosk\FetchWeather;
use App\Enums\CalendarColor;
use App\Livewire\Traits\WithKioskTeam;
use App\Models\CalendarFeed;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use WithKioskTeam;

    /**
     * @return array{
     *     location: string|null,
     *     temp: float,
     *     high: float,
     *     low: float,
     *     description: string|null,
     *     icon: string|null
     * }|null
     */
    #[Computed]
    public function weather(): ?array
    {
        return resolve(FetchWeather::class)->handle($this->team);
    }

    /**
     * @return array{title: string, color: string, startsAt: string, label: string, time: string}|null
     */
    #[Computed]
    public function nextEvent(): ?array
    {
        $today = $this->team->today();
        $now = CarbonImmutable::now($this->team->timezone);

        $event = $this->team->calendarFeeds()->get()
            ->flatMap(fn (CalendarFeed $feed) => resolve(FetchCalendarEvents::class)->handle($feed, 2, $today))
            ->reject(fn (array $event): bool => $event['all_day'] || $event['response_status'] === 'DECLINED')
            ->filter(fn (array $event): bool => $event['starts_at']->gte($now))
            ->sortBy('starts_at')
            ->first();

        if ($event === null) {
            return null;
        }

        return [
            'title' => $event['title'],
            'color' => $event['feed_color'] instanceof CalendarColor ? $event['feed_color']->value : $event['feed_color'],
            'startsAt' => $event['starts_at']->toIso8601String(),
            'label' => $event['starts_at']->isSameDay($today) ? __('Next up') : __('Tomorrow'),
            'time' => $event['starts_at']->format('g:i A'),
        ];
    }

    public function placeholder(): string
    {
        return '<div></div>';
    }
};
