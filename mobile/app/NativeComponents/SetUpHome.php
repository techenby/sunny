<?php

namespace App\NativeComponents;

use App\Actions\SetUpHome as SetUpHomeAction;
use App\Http\Integrations\Sunny\EmailVerificationRequired;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Onboarding;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Throwable;

class SetUpHome extends Component
{
    /** @var list<string> */
    public array $places = ['Kitchen pantry', 'Garage', 'Hall closet'];

    /** @var list<string> */
    public array $ownPlaces = [];

    public bool $addingPlace = false;

    public string $newPlace = '';

    /** @var list<string> */
    public array $starters = ['groceries', 'bedtime'];

    public bool $saving = false;

    public string $error = '';

    public function mount(): void
    {
        if (Onboarding::current() === null) {
            $this->replace('/dashboard');

            return;
        }

        app(SunnySyncCoordinator::class)->dispatchIfDue();
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status, ?string $exceptionClass = null): void
    {
        if ($status === 'failed' && $exceptionClass === EmailVerificationRequired::class) {
            $this->replace('/verify-email');
        }
    }

    #[Computed]
    public function firstName(): ?string
    {
        return Onboarding::current()?->first_name ?: null;
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function placeOptions(): array
    {
        return [...SetUpHomeAction::PLACES, ...$this->ownPlaces];
    }

    #[Computed]
    public function summary(): string
    {
        $parts = collect([
            trans_choice(':count place|:count places', count($this->places)),
            trans_choice(':count list|:count lists', count(array_intersect($this->starters, ['groceries']))),
            trans_choice(':count routine|:count routines', count(array_intersect($this->starters, ['bedtime', 'morning']))),
            trans_choice(':count recipe|:count recipes', count(array_intersect($this->starters, ['recipe']))),
        ])->reject(fn (string $part): bool => str_starts_with($part, '0 '));

        return $parts->isEmpty() ? 'Nothing picked yet' : $parts->join(', ', ' and ');
    }

    public function togglePlace(int $index): void
    {
        $place = $this->placeOptions[$index] ?? null;

        if ($place === null) {
            return;
        }

        $this->places = in_array($place, $this->places, true)
            ? array_values(array_diff($this->places, [$place]))
            : [...$this->places, $place];
    }

    public function startOwnPlace(): void
    {
        $this->addingPlace = true;
        $this->newPlace = '';
    }

    public function addOwnPlace(): void
    {
        $name = trim($this->newPlace);
        $this->addingPlace = false;
        $this->newPlace = '';

        if ($name === '' || mb_strlen($name) > 255 || in_array(mb_strtolower($name), array_map(mb_strtolower(...), $this->placeOptions), true)) {
            return;
        }

        $this->ownPlaces = [...$this->ownPlaces, $name];
        $this->places = [...$this->places, $name];
        unset($this->placeOptions);
    }

    public function toggleStarter(string $starter): void
    {
        if (! in_array($starter, SetUpHomeAction::STARTERS, true)) {
            return;
        }

        $this->starters = in_array($starter, $this->starters, true)
            ? array_values(array_diff($this->starters, [$starter]))
            : [...$this->starters, $starter];
    }

    public function setUp(): void
    {
        $onboarding = Onboarding::current();

        if ($this->saving || $onboarding === null) {
            return;
        }

        $this->error = '';
        $this->saving = true;

        try {
            if (app(SunnyTeam::class)->current() === null) {
                app(SunnySyncCoordinator::class)->sync();
            }

            $team = app(SunnyTeam::class)->current();

            if ($team === null) {
                $this->error = 'Sunny couldn’t download your home yet. Check your connection and try again.';

                return;
            }

            app(SetUpHomeAction::class)->handle($onboarding, $team, $this->places, $this->starters);
        } catch (EmailVerificationRequired) {
            $this->replace('/verify-email');

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'Unable to set up your home on this phone. Try again.';

            return;
        } finally {
            $this->saving = false;
        }

        $this->replace('/dashboard');
    }

    public function skip(): void
    {
        Onboarding::current()?->skip();
        $this->replace('/dashboard');
    }

    public function render(): View
    {
        return view('native.set-up-home');
    }
}
