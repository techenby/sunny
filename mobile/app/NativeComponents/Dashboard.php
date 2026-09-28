<?php

namespace App\NativeComponents;

use App\Actions\DestroySession;
use App\Concerns\ChecksSunnySync;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;

class Dashboard extends NativeComponent
{
    use ChecksSunnySync;

    public string $syncError = '';

    public string $activeTeamName = '';

    public bool $showTeamPicker = false;

    public function mount(): void
    {
        $this->onResume();
    }

    public function onResume(): void
    {
        app(SunnySyncCoordinator::class)->dispatchIfDue();
        $this->refreshLocalData();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function teamOptions(): array
    {
        return app(SunnyTeam::class)->choices();
    }

    public function openTeamPicker(): void
    {
        $this->showTeamPicker = count($this->teamOptions) > 1;
    }

    public function closeTeamPicker(): void
    {
        $this->showTeamPicker = false;
    }

    public function selectTeam(int $id): void
    {
        if (array_key_exists($id, app(SunnyTeam::class)->choices())) {
            app(SunnyTeam::class)->select($id);
        }
        $this->showTeamPicker = false;
        $this->refreshLocalData();
    }

    public function sync(): void
    {
        $this->syncError = '';

        try {
            app(SunnySyncCoordinator::class)->sync();
        } catch (AuthenticationException|RequestException|FatalRequestException|JsonException|ValidationException|RuntimeException $exception) {
            $this->syncFailed($exception::class);

            return;
        }

        $this->refreshLocalData();
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
    }

    /**
     * @return array{recipes: int, items: int, locations: int, bins: int}
     */
    #[Computed]
    public function summary(): array
    {
        $itemCounts = Item::forActiveTeam()->toBase()->selectRaw('type, count(*) as aggregate')->groupBy('type')->pluck('aggregate', 'type');

        return [
            'recipes' => Recipe::forActiveTeam()->count(),
            'items' => (int) ($itemCounts[ItemType::Item->value] ?? 0),
            'locations' => (int) ($itemCounts[ItemType::Location->value] ?? 0),
            'bins' => (int) ($itemCounts[ItemType::Bin->value] ?? 0),
        ];
    }

    #[Computed]
    public function teamInitial(): string
    {
        return mb_strtoupper(mb_substr($this->activeTeamName, 0, 1));
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
                'photo' => $recipe['photo_url'],
                'url' => '/recipes/'.$recipe['id'],
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, type: ItemType, supporting: string, url: string}>
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
                'url' => '/inventory/'.$item['id'],
            ])
            ->all();
    }

    public function confirmLogOut(): void
    {
        Dialog::alert('Log out?', 'Recipes and inventory downloaded to this device will be removed.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Log out', 'style' => 'destructive'],
        ])->id('log-out')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'log-out' && $label === 'Log out') {
            $this->logOut();
        }
    }

    public function logOut(): void
    {
        try {
            app(SunnyAuth::class)->logout();
        } catch (RequestException|FatalRequestException) {
            Dialog::toast('Signed out on this device. Sunny could not revoke the remote session.');
        } catch (RuntimeException) {
            Dialog::toast('Unable to clear your saved login. Unlock your device and try again.');

            return;
        }

        $this->replace('/');
    }

    public function render(): View
    {
        return view('native.dashboard');
    }

    private function refreshLocalData(): void
    {
        $teams = app(SunnyTeam::class);
        $this->activeTeamName = $teams->choices()[$teams->current()?->id] ?? '';
        unset($this->recentRecipes, $this->recentItems, $this->teamOptions, $this->summary, $this->teamInitial);
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
            Dialog::toast('Unable to sync. Your previously downloaded data is still available.');
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
