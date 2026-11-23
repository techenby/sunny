<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesCalendarFeedForm;
use App\Http\Integrations\Sunny\SunnyCalendar;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Layouts\HidesTabBar;
use App\Support\CalendarAgenda;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;

class EditCalendarFeed extends Component implements HidesTabBar
{
    use ChecksSunnySync;
    use ManagesCalendarFeedForm;

    public function mount(): void
    {
        if ($this->feed !== null) {
            $this->name = $this->feed['name'];
            $this->url = $this->feed['url'];
            $this->fillColor($this->feed['color']);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function feed(): ?array
    {
        $teamId = app(SunnyTeam::class)->current()?->id;
        $feeds = $teamId === null ? [] : (app(SunnyCalendar::class)->feeds($teamId)['feeds'] ?? []);

        return collect($feeds)->firstWhere('id', (int) $this->param('id'));
    }

    #[Computed]
    public function failure(): ?string
    {
        if (($this->feed['status'] ?? null) !== 'failing') {
            return null;
        }

        return collect([
            CalendarAgenda::problem($this->feed['last_error']),
            $this->feed['last_failed_at'] ? 'last failed '.CalendarAgenda::ago(CarbonImmutable::parse($this->feed['last_failed_at'])) : null,
        ])->filter()->implode(' · ');
    }

    public function save(): void
    {
        if ($this->feed === null) {
            $this->error = 'This calendar could not be found.';

            return;
        }

        $this->submitFeed($this->feed['id']);
    }

    public function render(): View
    {
        return view('native.edit-calendar-feed');
    }
}
