<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\DescribesCalendarFeedFailures;
use App\Http\Integrations\Sunny\SunnyCalendar;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Icons\Android;
use App\Icons\Ios;
use App\Support\CalendarAgenda;
use App\Support\Toast;
use App\Ui\Menu;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Elements\TopBarAction;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use RuntimeException;
use Throwable;

class CalendarFeeds extends Component
{
    use ChecksSunnySync;
    use DescribesCalendarFeedFailures;

    public const int STALE_AFTER_MINUTES = 60;

    public bool $loading = false;

    public string $status = '';

    public ?int $removingId = null;

    public function mount(): void
    {
        $this->refresh();
    }

    public function onResume(): void
    {
        $this->refreshLocalSyncedData();
        $this->refresh();
    }

    public function refresh(): void
    {
        $teamId = app(SunnyTeam::class)->current()?->id;

        if ($teamId === null) {
            return;
        }

        try {
            $token = app(SunnyTokenStore::class)->get();
        } catch (RuntimeException) {
            $token = null;
        }

        $this->loading = true;
        $this->status = '';

        AsyncTask::dispatch(static fn (): string => app(SunnyCalendar::class)->refreshFeeds($teamId, $token))
            ->finished(fn (string $status) => $this->loaded($status))
            ->failed(fn () => $this->loaded(app(SunnyCalendar::class)->refreshFeeds($teamId, $token)));
    }

    /**
     * @return array{failing: list<array<string, mixed>>, working: list<array<string, mixed>>}|null
     */
    #[Computed]
    public function feeds(): ?array
    {
        $teamId = app(SunnyTeam::class)->current()?->id;
        $snapshot = $teamId === null ? null : app(SunnyCalendar::class)->feeds($teamId);

        if ($snapshot === null) {
            return null;
        }

        $feeds = collect($snapshot['feeds'])->map(fn (array $feed): array => [
            'id' => $feed['id'],
            'name' => $feed['name'],
            'url' => $feed['url'],
            'color' => CalendarAgenda::color($feed['color']),
            'failing' => $feed['status'] === 'failing',
            'problem' => CalendarAgenda::problem($feed['last_error']),
            'detail' => match (true) {
                $feed['status'] === 'failing' => 'Last failed '.($feed['last_failed_at'] ? CalendarAgenda::ago(CarbonImmutable::parse($feed['last_failed_at'])) : 'recently'),
                $feed['last_fetched_at'] === null => 'Not fetched yet',
                CarbonImmutable::parse($feed['last_fetched_at'])->lessThan(now()->subMinutes(self::STALE_AFTER_MINUTES)) => 'Updated '.CalendarAgenda::ago(CarbonImmutable::parse($feed['last_fetched_at'])),
                default => null,
            },
        ]);

        return [
            'failing' => $feeds->where('failing', true)->values()->all(),
            'working' => $feeds->where('failing', false)->values()->all(),
        ];
    }

    /**
     * @param  array{id: int, name: string}  $feed
     * @return list<TopBarAction>
     */
    public function feedMenu(array $feed): array
    {
        return [
            Menu::item('calendar-feed-'.$feed['id'].'-edit', 'Edit', Ios::Pencil, Android::Edit, 'editFeed('.$feed['id'].')'),
            Menu::delete('calendar-feed-'.$feed['id'].'-remove', 'Remove calendar', 'confirmRemove('.$feed['id'].')', 'Remove '.$feed['name']),
        ];
    }

    public function editFeed(int $id): void
    {
        $this->navigate('/calendar/feeds/'.$id.'/edit');
    }

    public function confirmRemove(int $id): void
    {
        $feed = collect([...($this->feeds['failing'] ?? []), ...($this->feeds['working'] ?? [])])->firstWhere('id', $id);

        if ($feed === null) {
            return;
        }

        $this->removingId = $id;

        Dialog::alert('Remove “'.$feed['name'].'”?', 'Its events will stop showing for everyone on your team.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Remove', 'style' => 'destructive'],
        ])->id('remove-calendar')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id !== 'remove-calendar') {
            return;
        }

        if ($label === 'Remove') {
            $this->remove();
        }

        $this->removingId = null;
    }

    public function remove(): void
    {
        $team = app(SunnyTeam::class)->current();

        if ($this->removingId === null || $team === null) {
            return;
        }

        try {
            app(SunnyCalendar::class)->deleteFeed($team, $this->removingId);
        } catch (Throwable $exception) {
            Toast::error($this->feedFailureMessage($exception, 'remove it'));

            return;
        }

        unset($this->feeds);
        $this->refresh();
    }

    public function render(): View
    {
        return view('native.calendar-feeds');
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->feeds);
    }

    private function loaded(string $status): void
    {
        $this->loading = false;
        $this->status = $status === 'ok' ? '' : $status;
        unset($this->feeds);
    }
}
