<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\MealPlanning\SaveMealPlanHistory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Save the weekly template as real meal plan entries for days that have ended')]
#[Signature('meals:save-history')]
class SaveMealPlanHistoryCommand extends Command
{
    public function handle(SaveMealPlanHistory $save): int
    {
        $days = $save->all();

        $this->components->info(__(':count day(s) of meals saved.', ['count' => $days]));

        return self::SUCCESS;
    }
}
