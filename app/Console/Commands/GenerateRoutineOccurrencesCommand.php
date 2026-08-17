<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use App\Actions\Routines\GenerateRoutineOccurrences;
use Illuminate\Console\Command;

#[Description('Pre-generate routine occurrences so upcoming days are ready before anyone opens the board')]
#[Signature('routines:generate {--days=7 : How many days ahead to generate}')]
class GenerateRoutineOccurrencesCommand extends Command
{
    public function handle(GenerateRoutineOccurrences $generate): int
    {
        $days = max(1, (int) $this->option('days'));

        $created = $generate->warm($days);

        $this->components->info(__(':count occurrence(s) generated across :days day(s).', [
            'count' => $created,
            'days' => $days,
        ]));

        return self::SUCCESS;
    }
}
