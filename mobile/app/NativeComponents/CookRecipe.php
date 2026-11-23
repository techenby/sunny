<?php

namespace App\NativeComponents;

use App\Layouts\HidesTabBar;
use App\Support\Amount;
use App\Support\CookTimerAlerts;
use App\Support\Toast;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Facades\Haptics;
use NativePHP\LocalNotifications\Events\PermissionGranted;
use SRWieZ\NativePHP\Mobile\Screen\Facades\Screen;

class CookRecipe extends Component implements HidesTabBar
{
    public const TAB_PREP = 0;

    public const TAB_COOK = 1;

    private const LONGEST_TIMER = 4 * 60 * 60;

    public int $tab = self::TAB_PREP;

    public ?int $servings = null;

    /** @var array<int, string> */
    public array $checked = [];

    public int $step = 1;

    public ?int $timerStep = null;

    public ?int $timerEndsAt = null;

    public int $timerSeconds = 0;

    /** @var array<int, int> */
    public array $timerPositions = [];

    public bool $staysAwake = false;

    public bool $notifies = false;

    public function mount(): void
    {
        foreach (Cache::get($this->cacheKey(), []) as $property => $value) {
            $this->{$property} = $value;
        }

        $this->servings ??= $this->baseServings;
        $this->step = max(1, min(max(1, $this->stepCount), $this->step));
        $this->staysAwake = $this->recipe !== null && Screen::keepAwake();
    }

    public function onResume(): void
    {
        $this->staysAwake = $this->recipe !== null && Screen::keepAwake();
    }

    public function unmount(): void
    {
        Screen::allowSleep();

        parent::unmount();
    }

    public function onBackPressed(): void
    {
        $this->leave();
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function recipe(): ?array
    {
        return Recipes::find((int) $this->param('id'));
    }

    #[Computed]
    public function baseServings(): ?int
    {
        $count = preg_match('/\d+/', (string) ($this->recipe['servings'] ?? ''), $matches) === 1 ? (int) $matches[0] : 0;

        return $count > 0 ? $count : null;
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function ingredients(): array
    {
        $factor = $this->baseServings !== null && $this->servings !== null ? $this->servings / $this->baseServings : 1.0;
        $rows = [];

        foreach ($this->recipe['ingredients'] ?? [] as $index => $row) {
            $rows[] = $this->isHeading($row)
                ? [...$row, 'index' => $index]
                : [...$row, 'index' => $index, 'checked' => $this->isChecked($index), 'display' => Amount::scale($row, $factor)];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function steps(): array
    {
        $number = 0;
        $rows = [];

        foreach ($this->recipe['instructions'] ?? [] as $row) {
            if ($this->isHeading($row)) {
                $rows[] = [...$row, 'number' => null];

                continue;
            }

            $number++;
            $durations = $this->durations($row);
            $position = $durations === [] ? 0 : ($this->timerPositions[$number] ?? 0) % count($durations);

            $rows[] = [
                ...$row,
                'number' => $number,
                'durations' => $durations,
                'next' => $durations[$position] ?? null,
                'upcoming' => array_slice($durations, $position + 1),
            ];
        }

        return $rows;
    }

    #[Computed]
    public function ingredientCount(): int
    {
        return collect($this->recipe['ingredients'] ?? [])->reject(fn (array $row): bool => $this->isHeading($row))->count();
    }

    #[Computed]
    public function checkedCount(): int
    {
        return collect($this->recipe['ingredients'] ?? [])->keys()->filter(fn (int $index): bool => $this->isChecked($index))->count();
    }

    #[Computed]
    public function stepCount(): int
    {
        return collect($this->recipe['instructions'] ?? [])->reject(fn (array $row): bool => $this->isHeading($row))->count();
    }

    /**
     * The child of the scroll view to bring into view. The view only moves
     * when this changes, so each tab asks for a different child: the top
     * marker on Prep, the header on step 1, and the step's own card after that.
     */
    #[Computed]
    public function scrollTarget(): int
    {
        if ($this->tab === self::TAB_PREP) {
            return 0;
        }

        if ($this->step <= 1) {
            return 1;
        }

        $position = collect($this->steps)->search(fn (array $row): bool => $row['number'] === $this->step);

        return $position === false ? 1 : $position + 2;
    }

    /** @return array{step: int, text: string, remaining: int, total: int, progress: float}|null */
    #[Computed]
    public function timer(): ?array
    {
        if ($this->timerStep === null || $this->timerEndsAt === null) {
            return null;
        }

        $remaining = max(0, $this->timerEndsAt - now()->timestamp);
        $step = collect($this->steps)->firstWhere('number', $this->timerStep);

        return [
            'step' => $this->timerStep,
            'text' => (string) ($step['text'] ?? ''),
            'remaining' => $remaining,
            'total' => $this->timerSeconds,
            'progress' => $this->timerSeconds > 0 ? 1 - $remaining / $this->timerSeconds : 1.0,
        ];
    }

    public function showTab(int $tab): void
    {
        $this->tab = $tab === self::TAB_COOK ? self::TAB_COOK : self::TAB_PREP;
        $this->remember();
    }

    public function changeServings(int $by): void
    {
        if ($this->servings !== null) {
            $this->servings = max(1, min(999, $this->servings + $by));
            $this->remember();
        }
    }

    public function toggleIngredient(int $index): void
    {
        $row = $this->recipe['ingredients'][$index] ?? null;

        if ($row === null || $this->isHeading($row)) {
            return;
        }

        if ($this->isChecked($index)) {
            unset($this->checked[$index]);
        } else {
            $this->checked[$index] = $row['text'];
        }

        $this->remember();
    }

    public function goToStep(int $step): void
    {
        $this->step = max(1, min(max(1, $this->stepCount), $step));
        $this->tab = self::TAB_COOK;
        $this->remember();
    }

    public function nextStep(): void
    {
        if ($this->step >= $this->stepCount) {
            $this->leave();

            return;
        }

        $this->goToStep($this->step + 1);
    }

    public function previousStep(): void
    {
        $this->goToStep($this->step - 1);
    }

    public function toggleTimer(int $step): void
    {
        if ($this->timerStep === $step) {
            $this->stopTimer();

            return;
        }

        $seconds = collect($this->steps)->firstWhere('number', $step)['next'] ?? null;

        if ($seconds === null) {
            return;
        }

        $this->timerStep = $step;
        $this->timerSeconds = $seconds;
        $this->timerEndsAt = now()->timestamp + $seconds;
        $this->remember();

        app(CookTimerAlerts::class)->start(
            (int) $this->param('id'),
            (string) ($this->recipe['name'] ?? 'Recipe'),
            $step,
            now()->addSeconds($seconds),
        );
    }

    public function stopTimer(): void
    {
        $this->clearTimer();
        $this->remember();

        app(CookTimerAlerts::class)->clear((int) $this->param('id'));
    }

    #[On(PermissionGranted::class)]
    public function notificationsAllowed(bool $granted): void
    {
        $this->notifies = $granted;
        $this->remember();
    }

    #[Poll(1000)]
    public function tick(): void
    {
        if ($this->timerStep === null || $this->timerEndsAt === null || now()->timestamp < $this->timerEndsAt) {
            return;
        }

        $step = $this->timerStep;
        $this->timerPositions[$step] = ($this->timerPositions[$step] ?? 0) + 1;
        $this->clearTimer();
        $this->remember();

        app(CookTimerAlerts::class)->clear((int) $this->param('id'));

        if (! $this->notifies) {
            Haptics::vibrate();
            Toast::info("Step {$step} timer is done", long: true);
        }
    }

    public function isChecked(int $index): bool
    {
        $text = $this->recipe['ingredients'][$index]['text'] ?? null;

        return $text !== null && ($this->checked[$index] ?? null) === $text;
    }

    /** @param  array<string, mixed>  $row */
    public function isHeading(array $row): bool
    {
        return ($row['type'] ?? null) === 'heading';
    }

    public static function clock(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds % 60)
            : sprintf('%d:%02d', $minutes, $seconds % 60);
    }

    public static function minutes(int $seconds): string
    {
        return $seconds >= 3600
            ? rtrim(rtrim(number_format($seconds / 3600, 1), '0'), '.').' hr'
            : max(1, (int) round($seconds / 60)).' min';
    }

    public function render(): View
    {
        return view('native.cook-recipe');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<int>
     */
    private function durations(array $row): array
    {
        return collect($row['timers'] ?? [])
            ->pluck('seconds')
            ->filter(fn (mixed $seconds): bool => is_int($seconds) && $seconds > 0 && $seconds <= self::LONGEST_TIMER)
            ->values()
            ->all();
    }

    /**
     * Opened from a link or a notification, cook mode is the only screen on
     * the stack, and iOS ignores a back press there. Go to the recipe instead.
     */
    private function leave(): void
    {
        if ($this->nativeRouter?->isRootScreen()) {
            $this->replace('/recipes/'.$this->param('id'));

            return;
        }

        $this->back();
    }

    private function clearTimer(): void
    {
        $this->timerStep = null;
        $this->timerEndsAt = null;
        $this->timerSeconds = 0;
    }

    private function remember(): void
    {
        unset($this->ingredients, $this->steps, $this->checkedCount, $this->timer, $this->scrollTarget);

        Cache::put($this->cacheKey(), [
            'tab' => $this->tab,
            'servings' => $this->servings,
            'checked' => $this->checked,
            'step' => $this->step,
            'timerStep' => $this->timerStep,
            'timerEndsAt' => $this->timerEndsAt,
            'timerSeconds' => $this->timerSeconds,
            'timerPositions' => $this->timerPositions,
            'notifies' => $this->notifies,
        ], now()->addHours(12));
    }

    private function cacheKey(): string
    {
        return 'cook-recipe.'.$this->param('id');
    }
}
