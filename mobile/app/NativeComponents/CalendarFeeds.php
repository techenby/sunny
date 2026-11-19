<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Http\Integrations\Sunny\SunnyCalendar;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Support\CalendarAgenda;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\Computed;
use RuntimeException;

class CalendarFeeds extends Component
{
    use ChecksSunnySync;

    public bool $loading = false;

    public string $status = '';

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
                $feed['last_fetched_at'] !== null => 'Updated '.CalendarAgenda::ago(CarbonImmutable::parse($feed['last_fetched_at'])),
                default => 'Not fetched yet',
            },
        ]);

        return [
            'failing' => $feeds->where('failing', true)->values()->all(),
            'working' => $feeds->where('failing', false)->values()->all(),
        ];
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
