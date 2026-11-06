<?php

declare(strict_types=1);

namespace App\Actions\MealPlanning;

use App\Models\MealPlanItem;
use App\Models\MealPlanSlot;
use App\Models\MealSlot;
use App\Models\MealTemplateItem;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Works out what a team is eating on each date in a range: saved rows where
 * a slot was overridden or the day is already history, and the weekly
 * template everywhere else.
 */
class ResolveMealPlan
{
    /**
     * The seven days of the week containing a date, starting on the team's
     * chosen first day of the week.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function week(Team $team, CarbonInterface $date): array
    {
        $start = CarbonImmutable::parse($date)->startOfDay()->startOfWeek((int) $team->week_start);

        return [$start, $start->addDays(6)];
    }

    /**
     * @return Collection<int, array{
     *     date: CarbonImmutable,
     *     saved: bool,
     *     slots: Collection<int, array{
     *         slot: MealSlot,
     *         items: EloquentCollection<int, MealPlanItem>|EloquentCollection<int, MealTemplateItem>,
     *         overridden: bool,
     *     }>,
     * }>
     */
    public function handle(Team $team, CarbonInterface $from, CarbonInterface $through): Collection
    {
        // Every date is midnight where the team lives, so it compares cleanly
        // with today() and the saved history date whatever timezone it came in.
        $from = CarbonImmutable::parse($from->toDateString(), $team->timezone);
        $through = CarbonImmutable::parse($through->toDateString(), $team->timezone);
        $savedThrough = $team->mealsSavedThrough();

        $planSlots = $team->mealPlanSlots()
            ->whereBetween('date', [$from->toDateString(), $through->toDateString()])
            ->with('items.recipe')
            ->get()
            ->keyBy(fn (MealPlanSlot $planSlot): string => $planSlot->date->toDateString() . ':' . $planSlot->meal_slot_id);

        $template = $team->mealTemplateItems()
            ->with('recipe')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (MealTemplateItem $item): string => $item->weekday . ':' . $item->meal_slot_id);

        // Removed slots still show on the days where something was saved in them.
        $slots = MealSlot::withTrashed()
            ->where('team_id', $team->id)
            ->where(fn (Builder $query) => $query
                ->whereNull('deleted_at')
                ->orWhereIn('id', $planSlots->pluck('meal_slot_id')))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return collect($from->toPeriod($through)->toArray())->map(function (CarbonInterface $date) use ($savedThrough, $planSlots, $template, $slots): array {
            $date = CarbonImmutable::parse($date);
            $saved = $date->lte($savedThrough);

            return [
                'date' => $date,
                'saved' => $saved,
                'slots' => $slots
                    ->map(function (MealSlot $slot) use ($date, $saved, $planSlots, $template): ?array {
                        $planSlot = $planSlots->get($date->toDateString() . ':' . $slot->id);

                        if ($slot->trashed() && ! $planSlot) {
                            return null;
                        }

                        return [
                            'slot' => $slot,
                            'items' => match (true) {
                                $planSlot !== null => $planSlot->items,
                                $saved => new EloquentCollection,
                                default => $template->get($date->dayOfWeek . ':' . $slot->id) ?? new EloquentCollection,
                            },
                            'overridden' => $planSlot !== null && ! $saved,
                        ];
                    })
                    ->filter()
                    ->values(),
            ];
        })->values();
    }
}
