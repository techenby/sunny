<?php

namespace App\NativeComponents;

use App\Actions\DestroySession;
use App\Concerns\AnswersInvitations;
use App\Concerns\ChecksSunnySync;
use App\Concerns\ShowsCalendarEvents;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Onboarding;
use App\Support\CalendarAgenda;
use App\Support\CrashReports;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;

class Dashboard extends Component
{
    use AnswersInvitations;
    use ChecksSunnySync;
    use ShowsCalendarEvents;

    public string $syncError = '';

    public function mount(): void
    {
        $this->onResume();
        $this->askAboutCrashReports();
    }

    public function onResume(): void
    {
        Onboarding::current()?->report();
        app(SunnySyncCoordinator::class)->dispatchIfDue();
        $this->refreshLocalData();
        $this->refreshCalendar();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function teamOptions(): array
    {
        return app(SunnyTeam::class)->choices();
    }

    public function sync(): void
    {
        $this->syncError = '';
        $this->refreshCalendar(force: true);

        try {
            app(SunnySyncCoordinator::class)->sync();
        } catch (AuthenticationException|RequestException|FatalRequestException|JsonException|ValidationException|RuntimeException $exception) {
            $this->syncFailed($exception::class);

            return;
        }

        $this->refreshLocalData();
        $this->refreshCalendar();
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status, ?string $exceptionClass = null): void
    {
        if ($status === 'failed') {
            $this->syncFailed($exceptionClass ?? RuntimeException::class);

            return;
        }

        $this->syncError = '';
        $this->refreshLocalData();
        $this->refreshCalendar();
    }

    /**
     * @return list<array{id: int, name: string, supporting: string, photo: string|null, url: string}>
     */
    #[Computed]
    public function recentRecipes(): array
    {
        return collect(Recipes::recent(8))
            ->map(fn (array $recipe): array => [
                'id' => $recipe['id'],
                'name' => $recipe['name'],
                'supporting' => $recipe['total_time'] ?: $this->activity($recipe),
                'photo' => $recipe['thumb_path'] ?? $recipe['photo_url'],
                'url' => '/recipes/'.$recipe['id'],
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, type: ItemType, supporting: string, photo: string|null, url: string}>
     */
    #[Computed]
    public function recentItems(): array
    {
        return collect(Inventory::recent())
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'name' => $item['name'],
                'type' => $item['type'],
                'supporting' => $item['type']->label().' · '.$this->activity($item),
                'photo' => $item['thumb_path'] ?? $item['photo_url'],
                'url' => '/inventory/'.$item['id'],
            ])
            ->all();
    }

    /**
     * @return list<array{name: string, date: string, events: list<array<string, mixed>>}>|null
     */
    #[Computed]
    public function comingUp(): ?array
    {
        $today = app(SunnyTeam::class)->current()?->today();

        if ($this->calendarSnapshot === null || $today === null) {
            return null;
        }

        $days = CalendarAgenda::byDay($this->calendarSnapshot['events'], $today);

        return array_map(fn (CarbonImmutable $day): array => [
            'name' => CalendarAgenda::dayName($day, $today),
            'date' => $day->format('D, M j'),
            'events' => CalendarAgenda::rows($days[$day->toDateString()] ?? [], $day),
        ], [$today, $today->addDay()]);
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'crash-reports' && $label === 'Share' && ! app(CrashReports::class)->share(true)) {
            Toast::error('Unable to save your choice. You can turn on crash reports in Settings.');
        }
    }

    public function render(): View
    {
        return view('native.dashboard');
    }

    private function askAboutCrashReports(): void
    {
        $crashReports = app(CrashReports::class);

        if (! $crashReports->shouldAsk()) {
            return;
        }

        $crashReports->markAsked();

        Dialog::alert('Help improve Sunny?', 'Share crash reports so we can find and fix problems. They include technical details about the error and your device. You can change this anytime in Settings.', [
            ['label' => 'Not now', 'style' => 'cancel'],
            ['label' => 'Share'],
        ])->id('crash-reports')->show();
    }

    protected function calendarRange(): ?array
    {
        $team = app(SunnyTeam::class)->current();

        return $team === null ? null : [$team->id, $team->today()->toDateString(), 2];
    }

    private function refreshLocalData(): void
    {
        unset($this->recentRecipes, $this->recentItems, $this->teamOptions, $this->invitations, $this->calendarSnapshot, $this->calendarFeeds, $this->comingUp);
    }

    protected function invitationsChanged(): void
    {
        $this->refreshLocalData();
    }

    protected function refreshLocalSyncedData(): void
    {
        $this->refreshLocalData();
    }

    /**
     * @param  class-string<\Throwable>  $exception
     */
    private function syncFailed(string $exception): void
    {
        if (is_a($exception, AuthenticationException::class, true) || is_a($exception, UnauthorizedException::class, true)) {
            rescue(fn () => app(DestroySession::class)->handle(), report: false);
            $this->replace('/login');

            return;
        }

        if (app(SunnyStore::class)->lastSyncedAt()) {
            Toast::info('Unable to sync. Your previously downloaded data is still available.', long: true);
        } else {
            $this->syncError = 'Unable to download your data. Check your connection and tap here to retry.';
        }
    }

    /**
     * "Added …" for a record that has never changed since it was created, "Updated …" otherwise.
     *
     * @param  array{created_at: string, updated_at: string}  $record
     */
    private function activity(array $record): string
    {
        $verb = $record['created_at'] === $record['updated_at'] ? 'Added' : 'Updated';

        return $verb.' '.Carbon::parse($record['updated_at'])->diffForHumans();
    }
}
