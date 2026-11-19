<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesCalendarFeedForm;
use App\Http\Integrations\Sunny\SunnyCalendar;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Support\CalendarAgenda;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use Throwable;

class EditCalendarFeed extends Component
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

    public function confirmRemove(): void
    {
        if ($this->feed === null) {
            return;
        }

        Dialog::alert('Remove “'.$this->feed['name'].'”?', 'Its events will stop showing for everyone on your team.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Remove', 'style' => 'destructive'],
        ])->id('remove-calendar')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'remove-calendar' && $label === 'Remove') {
            $this->remove();
        }
    }

    public function remove(): void
    {
        $team = app(SunnyTeam::class)->current();

        if ($this->feed === null || $team === null || $this->saving) {
            return;
        }

        $this->error = '';
        $this->saving = true;

        try {
            app(SunnyCalendar::class)->deleteFeed($team, $this->feed['id']);
        } catch (Throwable $exception) {
            $this->error = $this->feedFailureMessage($exception, 'remove it');

            return;
        } finally {
            $this->saving = false;
        }

        $this->back();
    }

    public function render(): View
    {
        return view('native.edit-calendar-feed');
    }
}
