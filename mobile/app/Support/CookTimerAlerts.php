<?php

namespace App\Support;

use Carbon\CarbonInterface;
use NativePHP\LocalNotifications\Facades\LocalNotifications;
use Throwable;
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\ActivityIcon;
use Vos\LiveActivities\Facades\LiveActivities;
use Vos\LiveActivities\LiveActivity;

/**
 * What a cook mode timer shows outside the app: a notification when it ends
 * and a countdown on the lock screen while it runs. Neither keeps PHP running,
 * so both are set up front and cleared the next time cook mode is open.
 */
class CookTimerAlerts
{
    public function start(int $recipeId, string $recipeName, int $step, CarbonInterface $endsAt): void
    {
        $this->clear($recipeId);

        LocalNotifications::requestPermission();

        LocalNotifications::send($this->key($recipeId))
            ->title("Step {$step} timer is done")
            ->body($recipeName)
            ->delay(max(1, (int) now()->diffInSeconds($endsAt)))
            ->url("/recipes/{$recipeId}/cook");

        rescue(function () use ($recipeId, $recipeName, $step, $endsAt): void {
            $capabilities = LiveActivities::capabilities();

            if (! $capabilities->supported || ! $capabilities->enabled) {
                return;
            }

            $activity = LiveActivity::make($this->key($recipeId), ActivityContent::make($recipeName)
                ->subtitle("Step {$step} timer")
                ->countdown($endsAt)
                ->icon(ActivityIcon::Utensils)
                ->color('#E85A48'));

            if (filled($scheme = config('nativephp.deeplink_scheme'))) {
                $activity->withLink("{$scheme}://recipes/{$recipeId}/cook");
            }

            $activity->start();
        }, report: false);
    }

    public function clear(int $recipeId): void
    {
        LocalNotifications::cancel($this->key($recipeId));

        rescue(function () use ($recipeId): void {
            foreach (LiveActivity::all() as $activity) {
                if ($activity->key === $this->key($recipeId)) {
                    $activity->end();
                }
            }
        }, report: fn (Throwable $e): bool => false);
    }

    private function key(int $recipeId): string
    {
        return "cook-timer-{$recipeId}";
    }
}
